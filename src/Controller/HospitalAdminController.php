<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\DialysisCenter;
use App\Entity\User;
use App\Service\AdminActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/hospital-admin')]
class HospitalAdminController extends AbstractController
{
    private const STATUSES = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
    private const PAGE_SIZE = 25;

    #[Route('', name: 'app_hospital_admin_dashboard', methods: ['GET'])]
    public function dashboard(EntityManagerInterface $em): Response
    {
        $centers = $this->allowedCenters($em);
        $appointments = $this->appointmentsForCenters($em, $centers, 10);
        $counts = array_fill_keys(self::STATUSES, 0);
        $centerIds = $this->centerIds($centers);
        if ($centerIds) {
            $statusRows = $em->createQueryBuilder()
                ->select('a.status, COUNT(a.id) AS total')
                ->from(Appointment::class, 'a')
                ->where('IDENTITY(a.dialysisCenter) IN (:centerIds)')
                ->setParameter('centerIds', $centerIds)
                ->groupBy('a.status')
                ->getQuery()
                ->getArrayResult();
            foreach ($statusRows as $row) {
                if (isset($counts[$row['status']])) {
                    $counts[$row['status']] = (int) $row['total'];
                }
            }
        }

        return $this->render('hospital_admin/dashboard.html.twig', [
            'centers' => $centers,
            'appointments' => $appointments,
            'total_count' => array_sum($counts),
            'status_counts' => $counts,
            'active_nav' => 'dashboard',
        ]);
    }

    #[Route('/records', name: 'app_hospital_admin_records', methods: ['GET'])]
    public function records(Request $request, EntityManagerInterface $em): Response
    {
        $centers = $this->allowedCenters($em);
        $centerIds = $this->centerIds($centers);
        $search = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('status', 'all');
        $page = max(1, $request->query->getInt('page', 1));
        $query = $em->createQueryBuilder()
            ->select('a')
            ->from(Appointment::class, 'a')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC');
        if ($centerIds) {
            $query->where('IDENTITY(a.dialysisCenter) IN (:centerIds)')->setParameter('centerIds', $centerIds);
        } else {
            $query->where('a.id = 0');
        }

        if ($search !== '') {
            $query->andWhere($query->expr()->orX(
                'a.patientName LIKE :search',
                'a.patientEmail LIKE :search',
                'a.center LIKE :search',
            ))->setParameter('search', '%'.$search.'%');
        }
        if (\in_array($status, self::STATUSES, true)) {
            $query->andWhere('a.status = :status')->setParameter('status', $status);
        } else {
            $status = 'all';
        }

        $countQuery = clone $query;
        $total = (int) $countQuery->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
        $appointments = $query->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()
            ->getResult();

        return $this->render('hospital_admin/records.html.twig', [
            'appointments' => $appointments,
            'centers' => $centers,
            'search' => $search,
            'status_filter' => $status,
            'page' => $page,
            'page_count' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'total' => $total,
            'active_nav' => 'records',
        ]);
    }

    #[Route('/appointments/{id}/status', name: 'app_hospital_admin_appointment_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateStatus(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        AdminActivityLogger $activityLogger,
    ): Response {
        $centerIds = $this->centerIds($this->allowedCenters($em));
        if (!$centerIds) {
            throw $this->createNotFoundException();
        }
        $appointment = $em->createQueryBuilder()
            ->select('a')
            ->from(Appointment::class, 'a')
            ->where('a.id = :id')
            ->andWhere('IDENTITY(a.dialysisCenter) IN (:centerIds)')
            ->setParameter('id', $id)
            ->setParameter('centerIds', $centerIds)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$appointment instanceof Appointment) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('hospital-appointment-'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');
        } else {
            $status = (string) $request->request->get('status');
            if (!\in_array($status, self::STATUSES, true)) {
                $this->addFlash('error', 'Please select a valid appointment status.');
            } elseif ($appointment->status === $status) {
                $this->addFlash('success', 'Appointment status is unchanged.');
            } else {
                $appointment->status = $status;
                $em->flush();
                $actor = $this->getUser();
                $activityLogger->record(
                    $actor instanceof User ? $actor : null,
                    'appointment.status',
                    'appointment',
                    (string) $appointment->id,
                    'Changed appointment #'.$appointment->id.' to '.$status.' for '.$appointment->center,
                );
                $this->addFlash('success', 'Appointment status updated.');
            }
        }

        return $this->redirectToRoute('app_hospital_admin_records');
    }

    #[Route('/staff', name: 'app_hospital_admin_staff', methods: ['GET', 'POST'])]
    public function staff(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        AdminActivityLogger $activityLogger,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_HOSPITAL_ADMIN');

        $centers = $this->allowedCenters($em);
        $errors = [];
        $name = '';
        $email = '';
        $selectedCenterIds = [];

        if ($request->isMethod('POST')) {
            $p = $request->request;
            $name = trim((string) $p->get('name'));
            $email = mb_strtolower(trim((string) $p->get('email')));
            $password = (string) $p->get('password');
            $submittedCenterIds = $p->all()['assigned_centers'] ?? [];

            if (!$this->isCsrfTokenValid('hospital-staff-form', (string) $p->get('_token'))) {
                $errors[] = 'Your session expired. Please try again.';
            }
            if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
                $errors[] = 'Name must be between 2 and 100 characters.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
                $errors[] = 'Please enter a valid email address.';
            } elseif ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
                $errors[] = 'An account with this email already exists.';
            }
            if (mb_strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }
            if (!\is_array($submittedCenterIds)) {
                $errors[] = 'Please select valid dialysis centers.';
                $submittedCenterIds = [];
            }

            $centerById = [];
            foreach ($centers as $center) {
                $centerById[(string) $center->id] = $center;
            }
            foreach ($submittedCenterIds as $centerId) {
                if ((!is_string($centerId) && !is_int($centerId)) || !ctype_digit((string) $centerId)) {
                    $errors[] = 'Please select valid dialysis centers.';
                    continue;
                }

                $centerId = (string) $centerId;
                if (!isset($centerById[$centerId])) {
                    $errors[] = 'You can only assign staff to your own dialysis centers.';
                    continue;
                }
                $selectedCenterIds[] = (int) $centerId;
            }
            $selectedCenterIds = array_values(array_unique($selectedCenterIds));
            if (!$selectedCenterIds) {
                $errors[] = 'Assign the staff member to at least one of your dialysis centers.';
            }

            if (!$errors) {
                $staff = (new User())
                    ->setName($name)
                    ->setEmail($email)
                    ->setHospitalStaff(true);
                $staff->setPassword($hasher->hashPassword($staff, $password));
                foreach ($selectedCenterIds as $centerId) {
                    $staff->assignCenter($centerById[(string) $centerId]);
                }
                $em->persist($staff);
                $em->flush();

                $actor = $this->getUser();
                $activityLogger->record(
                    $actor instanceof User ? $actor : null,
                    'user.create',
                    'user',
                    (string) $staff->getId(),
                    'Created hospital staff account '.$staff->getEmail(),
                );
                $this->addFlash('success', 'Hospital staff account created.');

                return $this->redirectToRoute('app_hospital_admin_staff');
            }
        }

        return $this->render('hospital_admin/staff.html.twig', [
            'centers' => $centers,
            'staff_users' => $this->staffUsersForCenters($em, $centers),
            'errors' => $errors,
            'name' => $name,
            'email' => $email,
            'selected_center_ids' => $selectedCenterIds,
            'active_nav' => 'staff',
        ]);
    }

    #[Route('/staff/{id}/status', name: 'app_hospital_admin_staff_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateStaffStatus(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        AdminActivityLogger $activityLogger,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_HOSPITAL_ADMIN');

        $staff = $em->getRepository(User::class)->find($id);
        if (!$staff instanceof User || !$staff->isHospitalStaff()) {
            throw $this->createNotFoundException();
        }

        $allowedCenterIds = $this->centerIds($this->allowedCenters($em));
        $staffCenterIds = $this->centerIds($staff->getAssignedCenters()->toArray());
        if (!$allowedCenterIds || !$staffCenterIds || array_diff($staffCenterIds, $allowedCenterIds)) {
            throw $this->createNotFoundException();
        }

        $actor = $this->getUser();
        if (!$this->isCsrfTokenValid('hospital-staff-status-'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');
        } elseif ($actor instanceof User && $staff->getId() === $actor->getId()) {
            $this->addFlash('error', 'You cannot deactivate your own account.');
        } else {
            $staff->setActive(!$staff->isActive());
            $em->flush();

            $activityLogger->record(
                $actor instanceof User ? $actor : null,
                $staff->isActive() ? 'user.activate' : 'user.deactivate',
                'user',
                (string) $staff->getId(),
                ($staff->isActive() ? 'Activated ' : 'Deactivated ').$staff->getEmail(),
            );
            $this->addFlash('success', $staff->getEmail().' is now '.($staff->isActive() ? 'active.' : 'inactive.'));
        }

        return $this->redirectToRoute('app_hospital_admin_staff');
    }

    /**
     * @return list<DialysisCenter>
     */
    private function allowedCenters(EntityManagerInterface $em): array
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            return $em->getRepository(DialysisCenter::class)->findBy([], ['name' => 'ASC']);
        }

        if (!$user->isHospitalAdmin() && !$user->isHospitalStaff()) {
            throw $this->createAccessDeniedException();
        }

        return $user->getAssignedCenters()->toArray();
    }

    /**
     * @param list<DialysisCenter> $centers
     *
     * @return list<Appointment>
     */
    private function appointmentsForCenters(
        EntityManagerInterface $em,
        array $centers,
        ?int $limit = null,
    ): array {
        $centerIds = $this->centerIds($centers);
        if (!$centerIds) {
            return [];
        }

        $query = $em->createQueryBuilder()
            ->select('a')
            ->from(Appointment::class, 'a')
            ->where('IDENTITY(a.dialysisCenter) IN (:centerIds)')
            ->setParameter('centerIds', $centerIds)
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        if ($limit !== null) {
            $query->setMaxResults($limit);
        }

        return $query->getQuery()->getResult();
    }

    /**
     * @param list<DialysisCenter> $centers
     *
     * @return list<User>
     */
    private function staffUsersForCenters(EntityManagerInterface $em, array $centers): array
    {
        $centerIds = $this->centerIds($centers);
        if (!$centerIds) {
            return [];
        }

        $staffUsers = $em->createQueryBuilder()
            ->select('DISTINCT u')
            ->from(User::class, 'u')
            ->join('u.assignedCenters', 'assignedCenter')
            ->where('u.roles LIKE :staffRole')
            ->andWhere('assignedCenter.id IN (:centerIds)')
            ->setParameter('staffRole', '%"ROLE_HOSPITAL_STAFF"%')
            ->setParameter('centerIds', $centerIds)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $staffUsers,
            fn (User $staff): bool => !array_diff($this->centerIds($staff->getAssignedCenters()->toArray()), $centerIds),
        ));
    }

    /**
     * @param list<DialysisCenter> $centers
     *
     * @return list<int>
     */
    private function centerIds(array $centers): array
    {
        return array_values(array_map(
            static fn (DialysisCenter $center): int => (int) $center->id,
            $centers,
        ));
    }
}
