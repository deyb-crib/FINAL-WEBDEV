<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\User;
use App\Service\AdminActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    private const STATUSES = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];

    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }
        if ($this->isGranted('ROLE_HOSPITAL_ADMIN')) {
            return $this->redirectToRoute('app_hospital_admin_dashboard');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $appointments = $em->getRepository(Appointment::class)->findBy(
            ['user' => $user],
            ['appointmentDate' => 'ASC', 'appointmentTime' => 'ASC']
        );
        $now = new \DateTimeImmutable();
        $upcoming = array_values(array_filter(
            $appointments,
            fn (Appointment $appointment): bool => $this->isUpcoming($appointment, $now)
        ));
        $history = array_values(array_filter(
            $appointments,
            fn (Appointment $appointment): bool => !\in_array($appointment, $upcoming, true)
        ));
        usort($history, static fn (Appointment $a, Appointment $b): int =>
            [$b->appointmentDate?->getTimestamp(), $b->appointmentTime] <=> [$a->appointmentDate?->getTimestamp(), $a->appointmentTime]
        );

        $latest = $appointments;
        usort($latest, static fn (Appointment $a, Appointment $b): int =>
            ($b->createdAt <=> $a->createdAt) ?: ($b->id <=> $a->id)
        );

        $view = $request->query->get('view') === 'history' ? 'history' : 'upcoming';
        $status = (string) $request->query->get('status', '');
        if (!\in_array($status, self::STATUSES, true)) {
            $status = '';
        }
        $search = trim((string) $request->query->get('q', ''));
        $list = $view === 'history' ? $history : $upcoming;
        if ($status !== '') {
            $list = array_values(array_filter($list, static fn (Appointment $appointment): bool => $appointment->status === $status));
        }
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $list = array_values(array_filter($list, static function (Appointment $appointment) use ($needle): bool {
                $haystack = mb_strtolower(implode(' ', [
                    $appointment->center,
                    $appointment->location,
                    $appointment->dialysisType,
                    $appointment->patientName,
                    $appointment->status,
                    $appointment->appointmentDate?->format('F j, Y') ?? '',
                ]));

                return str_contains($haystack, $needle);
            }));
        }

        return $this->render('dashboard/index.html.twig', [
            'appointments' => $list,
            'upcoming_count' => \count($upcoming),
            'history_count' => \count($history),
            'pending_count' => \count(array_filter($appointments, static fn (Appointment $appointment): bool => $appointment->status === 'Pending')),
            'total_count' => \count($appointments),
            'next_appointment' => $upcoming[0] ?? null,
            'latest_appointment' => $latest[0] ?? null,
            'can_cancel_ids' => array_map(
                static fn (Appointment $appointment): int => $appointment->id,
                array_values(array_filter(
                    $appointments,
                    fn (Appointment $appointment): bool => $this->isCancellable($appointment, $now)
                ))
            ),
            'view' => $view,
            'status_filter' => $status,
            'search' => $search,
        ]);
    }

    #[Route('/dashboard/appointments', name: 'app_user_appointments', methods: ['GET'])]
    public function appointments(Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }
        if ($this->isGranted('ROLE_HOSPITAL_ADMIN')) {
            return $this->redirectToRoute('app_hospital_admin_dashboard');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $allAppointments = $em->getRepository(Appointment::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC', 'id' => 'DESC']
        );
        $statusCounts = array_fill_keys(self::STATUSES, 0);
        foreach ($allAppointments as $appointment) {
            if (isset($statusCounts[$appointment->status])) {
                ++$statusCounts[$appointment->status];
            }
        }

        $status = (string) $request->query->get('status', '');
        if ($status !== '' && !\in_array($status, self::STATUSES, true)) {
            $status = '';
        }
        $search = trim((string) $request->query->get('q', ''));
        $appointments = array_values(array_filter($allAppointments, static function (Appointment $appointment) use ($status, $search): bool {
            if ($status !== '' && $appointment->status !== $status) {
                return false;
            }
            if ($search === '') {
                return true;
            }

            $needle = mb_strtolower($search);
            $haystack = mb_strtolower(implode(' ', [
                $appointment->center,
                $appointment->location,
                $appointment->dialysisType,
                $appointment->status,
                $appointment->appointmentDate?->format('F j, Y') ?? '',
            ]));

            return str_contains($haystack, $needle);
        }));

        $now = new \DateTimeImmutable();

        return $this->render('dashboard/appointments.html.twig', [
            'appointments' => $appointments,
            'total_count' => \count($allAppointments),
            'status_counts' => $statusCounts,
            'status_filter' => $status,
            'search' => $search,
            'can_cancel_ids' => array_map(
                static fn (Appointment $appointment): int => $appointment->id,
                array_values(array_filter(
                    $allAppointments,
                    fn (Appointment $appointment): bool => $this->isCancellable($appointment, $now)
                ))
            ),
        ]);
    }

    #[Route('/dashboard/appointments/{id}/cancel', name: 'app_appointment_cancel', methods: ['POST'])]
    public function cancel(int $id, Request $request, EntityManagerInterface $em, AdminActivityLogger $activityLogger): Response
    {
        if ($this->isGranted('ROLE_HOSPITAL_ADMIN') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $appointment = $em->getRepository(Appointment::class)->findOneBy(['id' => $id, 'user' => $user]);
        if (!$appointment) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('cancel'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');
        } elseif (!$this->isCancellable($appointment, new \DateTimeImmutable())) {
            $this->addFlash('error', 'Only pending appointments can be cancelled, and only before their scheduled time.');
        } else {
            $appointment->status = 'Cancelled';
            $em->flush();
            $activityLogger->record(
                $user,
                'appointment.cancel',
                'appointment',
                (string) $appointment->id,
                'Cancelled an appointment at '.$appointment->center,
            );
            $this->addFlash('success', 'Appointment cancelled.');
        }

        if ($request->request->get('_return_to') === 'appointments') {
            return $this->redirectToRoute('app_user_appointments');
        }

        return $this->redirectToRoute('app_dashboard');
    }

    private function isUpcoming(Appointment $appointment, \DateTimeImmutable $now): bool
    {
        $scheduledAt = $this->scheduledAt($appointment);

        return $scheduledAt !== null
            && $scheduledAt >= $now
            && \in_array($appointment->status, ['Pending', 'Confirmed'], true);
    }

    private function isCancellable(Appointment $appointment, \DateTimeImmutable $now): bool
    {
        $scheduledAt = $this->scheduledAt($appointment);

        return $appointment->status === 'Pending'
            && $scheduledAt !== null
            && $scheduledAt >= $now;
    }

    private function scheduledAt(Appointment $appointment): ?\DateTimeImmutable
    {
        if (!$appointment->appointmentDate || !preg_match('/^\d{2}:\d{2}$/', $appointment->appointmentTime)) {
            return null;
        }

        return $appointment->appointmentDate->setTime(
            (int) substr($appointment->appointmentTime, 0, 2),
            (int) substr($appointment->appointmentTime, 3, 2),
        );
    }
}
