<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\DialysisCenter;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\AdminActivityLogger;
use App\Service\AdminDashboardService;

#[Route('/admin')]
class AdminController extends AbstractController
{
    private const APPOINTMENT_STATUSES = ['Pending', 'Confirmed', 'Cancelled', 'Completed'];

    #[Route('', name: 'app_admin_dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $em, AdminDashboardService $dashboard): Response
    {
        return $this->render('admin/index.html.twig', [
            'overview' => $dashboard->overview(),
            'appointments' => $em->getRepository(Appointment::class)->findBy([], ['createdAt' => 'DESC'], 8),
        ]);
    }

    #[Route('/records', name: 'app_admin_records', methods: ['GET'])]
    public function records(Request $request, EntityManagerInterface $em): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('status', 'all');
        $query = $em->createQueryBuilder()
            ->select('a')
            ->from(Appointment::class, 'a')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        if ($search !== '') {
            $query->andWhere($query->expr()->orX(
                'a.patientName LIKE :search',
                'a.patientEmail LIKE :search',
                'a.center LIKE :search',
            ))->setParameter('search', '%'.$search.'%');
        }
        if (\in_array($status, self::APPOINTMENT_STATUSES, true)) {
            $query->andWhere('a.status = :status')->setParameter('status', $status);
        } else {
            $status = 'all';
        }

        $page = max(1, $request->query->getInt('page', 1));
        $countQuery = clone $query;
        $total = (int) $countQuery->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
        $appointments = $query->setFirstResult(($page - 1) * 25)
            ->setMaxResults(25)
            ->getQuery()
            ->getResult();

        return $this->render('admin/records.html.twig', [
            'appointments' => $appointments,
            'search' => $search,
            'status_filter' => $status,
            'page' => $page,
            'page_count' => max(1, (int) ceil($total / 25)),
            'total' => $total,
        ]);
    }

    #[Route('/appointments/{id}/status', name: 'app_admin_appointment_status', methods: ['POST'])]
    public function updateAppointmentStatus(int $id, Request $request, EntityManagerInterface $em, AdminActivityLogger $activityLogger): Response
    {
        $appointment = $em->getRepository(Appointment::class)->find($id);
        if (!$appointment) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('admin-appointment-'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');
        } else {
            $status = (string) $request->request->get('status');
            if (!\in_array($status, self::APPOINTMENT_STATUSES, true)) {
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
                    'Changed appointment #'.$appointment->id.' to '.$status,
                );
                $this->addFlash('success', 'Appointment status updated.');
            }
        }

        return $request->request->get('return_to') === 'records'
            ? $this->redirectToRoute('app_admin_records')
            : $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/centers', name: 'app_admin_centers', methods: ['GET'])]
    public function centers(EntityManagerInterface $em): Response
    {
        return $this->render('admin/centers.html.twig', [
            'centers' => $em->getRepository(DialysisCenter::class)->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/centers/new', name: 'app_admin_center_new', methods: ['GET', 'POST'])]
    public function newCenter(Request $request, EntityManagerInterface $em, AdminActivityLogger $activityLogger): Response
    {
        return $this->centerForm(new DialysisCenter(), true, $request, $em, $activityLogger);
    }

    #[Route('/centers/{id}/edit', name: 'app_admin_center_edit', methods: ['GET', 'POST'])]
    public function editCenter(int $id, Request $request, EntityManagerInterface $em, AdminActivityLogger $activityLogger): Response
    {
        $center = $em->getRepository(DialysisCenter::class)->find($id);
        if (!$center) {
            throw $this->createNotFoundException();
        }

        return $this->centerForm($center, false, $request, $em, $activityLogger);
    }

    #[Route('/centers/{id}/status', name: 'app_admin_center_status', methods: ['POST'])]
    public function toggleCenterStatus(int $id, Request $request, EntityManagerInterface $em, AdminActivityLogger $activityLogger): Response
    {
        $center = $em->getRepository(DialysisCenter::class)->find($id);
        if (!$center) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('admin-center-status-'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');
        } else {
            $center->status = $center->status === 'Active' ? 'Inactive' : 'Active';
            $em->flush();
            $actor = $this->getUser();
            $activityLogger->record(
                $actor instanceof User ? $actor : null,
                'center.status',
                'center',
                (string) $center->id,
                $center->name.' was '.$center->status,
            );
            $this->addFlash('success', $center->name.' is now '.$center->status.'.');
        }

        return $this->redirectToRoute('app_admin_centers');
    }

    private function centerForm(
        DialysisCenter $center,
        bool $isNew,
        Request $request,
        EntityManagerInterface $em,
        AdminActivityLogger $activityLogger,
    ): Response {
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin-center', (string) $request->request->get('_token'))) {
                $errors[] = 'Your session expired. Please try again.';
            }

            $values = [
                'name' => trim((string) $request->request->get('name')),
                'address' => trim((string) $request->request->get('address')),
                'operatingHours' => trim((string) $request->request->get('operating_hours')),
                'dialysisTypes' => trim((string) $request->request->get('dialysis_types')),
                'imagePath' => trim((string) $request->request->get('image_path')),
            ];
            $limits = ['name' => 150, 'address' => 255, 'operatingHours' => 60, 'dialysisTypes' => 255];

            foreach ($limits as $field => $limit) {
                if ($values[$field] === '' || mb_strlen($values[$field]) > $limit) {
                    $errors[] = 'Please provide a valid '.match ($field) {
                        'operatingHours' => 'operating hours',
                        'dialysisTypes' => 'dialysis services',
                        default => strtolower($field),
                    }.'.';
                }
            }

            $unsafeImagePath = str_contains('/'.$values['imagePath'].'/', '/../');
            if (mb_strlen($values['imagePath']) > 255 || ($values['imagePath'] !== '' && (!preg_match('~^images/[A-Za-z0-9._/-]+$~', $values['imagePath']) || $unsafeImagePath))) {
                $errors[] = 'Image path must be a relative path under the public images folder, such as images/center.png.';
            }

            if (!$errors) {
                $center->name = $values['name'];
                $center->address = $values['address'];
                $center->operatingHours = $values['operatingHours'];
                $center->dialysisTypes = $values['dialysisTypes'];
                $center->imagePath = $values['imagePath'] !== '' ? $values['imagePath'] : null;

                if ($isNew) {
                    $em->persist($center);
                }
                $em->flush();
                $actor = $this->getUser();
                $activityLogger->record(
                    $actor instanceof User ? $actor : null,
                    $isNew ? 'center.create' : 'center.update',
                    'center',
                    (string) $center->id,
                    ($isNew ? 'Added ' : 'Updated ').$center->name,
                );
                $this->addFlash('success', $isNew ? 'Dialysis center added.' : 'Dialysis center updated.');

                return $this->redirectToRoute('app_admin_centers');
            }

            $center->name = $values['name'];
            $center->address = $values['address'];
            $center->operatingHours = $values['operatingHours'];
            $center->dialysisTypes = $values['dialysisTypes'];
            $center->imagePath = $values['imagePath'] !== '' ? $values['imagePath'] : null;
        }

        return $this->render('admin/center_form.html.twig', [
            'center' => $center,
            'is_new' => $isNew,
            'errors' => $errors,
        ]);
    }
}
