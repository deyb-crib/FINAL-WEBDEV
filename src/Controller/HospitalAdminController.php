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

        if (!$user->isHospitalAdmin()) {
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
