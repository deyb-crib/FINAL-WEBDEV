<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\DialysisCenter;
use App\Entity\User;
use App\Service\AdminActivityLogger;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookingController extends AbstractController
{
    private const TYPES = [
        'Hemodialysis', 'Peritoneal Dialysis', 'Home Hemodialysis',
        'Continuous Ambulatory Peritoneal Dialysis', 'Automated Peritoneal Dialysis',
    ];
    private const SLOTS = [
        '08:00' => 'Morning', '10:00' => 'Morning',
        '13:00' => 'Afternoon', '15:00' => 'Afternoon',
        '18:00' => 'Evening',
    ];

    #[Route('/booking/availability', name: 'app_booking_availability', methods: ['GET'])]
    public function availability(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $center = trim((string) $request->query->get('center'));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get('date'));
        if ($center === '' || !$date) {
            return new JsonResponse(['bookedTimes' => []]);
        }

        $times = $em->createQuery(
            'SELECT a.appointmentTime FROM App\Entity\Appointment a
             WHERE a.center = :c AND a.appointmentDate = :d AND a.status <> :x'
        )
            ->setParameter('c', $center)
            ->setParameter('d', $date, Types::DATE_IMMUTABLE)
            ->setParameter('x', 'Cancelled')
            ->getSingleColumnResult();

        return new JsonResponse(['bookedTimes' => $times]);
    }

    #[Route('/booking/create', name: 'app_booking_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, AdminActivityLogger $activityLogger): Response
    {
        $p = $request->request;
        if (!$this->isCsrfTokenValid('booking', (string) $p->get('csrf_token'))) {
            $this->addFlash('error', 'Your session expired. Please try booking again.');
            return $this->redirectToRoute('app_dashboard');
        }

        $errors = [];
        $get = static fn (string $k): string => trim((string) $p->get($k));

        $center = $em->getRepository(DialysisCenter::class)->findOneBy(['name' => $get('center'), 'status' => 'Active']);
        if (!$center) {
            $errors[] = 'The selected dialysis center was not found.';
        }
        $type = $get('dialysis_type');
        if (!\in_array($type, self::TYPES, true)) {
            $errors[] = 'Please choose a dialysis type.';
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $get('date'));
        if (!$date || $date < new \DateTimeImmutable('today')) {
            $errors[] = 'Please choose a valid date that is not in the past.';
        }
        $session = $get('session');
        $time = $get('time');
        if (!isset(self::SLOTS[$time]) || self::SLOTS[$time] !== $session) {
            $errors[] = 'Please choose a valid session and time.';
        }
        $name = $get('patient_name');
        $emergencyName = $get('emergency_name');
        $nameOk = static fn (string $v): bool => mb_strlen($v) >= 2 && mb_strlen($v) <= 100 && preg_match("/^[\\p{L} .'-]+$/u", $v);
        $phoneOk = static fn (string $v): bool => (bool) preg_match('/^[0-9+() .-]{7,30}$/', $v);
        if (!$nameOk($name) || !$nameOk($emergencyName)) {
            $errors[] = 'Please enter valid names.';
        }
        if (!$phoneOk($get('patient_contact')) || !$phoneOk($get('emergency_contact'))) {
            $errors[] = 'Please enter valid contact numbers.';
        }
        if (!filter_var($get('patient_email'), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        $notes = $get('notes');
        if (mb_strlen($notes) > 1000) {
            $errors[] = 'Notes are too long (max 1000 characters).';
        }

        if (!$errors) {
            $taken = $em->getRepository(Appointment::class)->count([
                'center' => $center->name, 'appointmentDate' => $date, 'appointmentTime' => $time,
            ]);
            // cancelled bookings free the slot again
            $cancelled = $em->getRepository(Appointment::class)->count([
                'center' => $center->name, 'appointmentDate' => $date, 'appointmentTime' => $time, 'status' => 'Cancelled',
            ]);
            if ($taken - $cancelled > 0) {
                $errors[] = 'That time slot is already fully booked. Please pick another.';
            }
        }

        if ($errors) {
            $this->addFlash('error', implode(' ', $errors));
            return $this->redirectToRoute('app_dashboard');
        }

        $a = new Appointment();
        $a->user = $this->getUser();
        $a->center = $center->name;
        $a->location = $center->address;
        $a->dialysisType = $type;
        $a->appointmentDate = $date;
        $a->session = $session;
        $a->appointmentTime = $time;
        $a->patientName = $name;
        $a->patientContact = $get('patient_contact');
        $a->patientEmail = $get('patient_email');
        $a->emergencyName = $emergencyName;
        $a->emergencyContact = $get('emergency_contact');
        $a->notes = $notes !== '' ? $notes : null;
        $em->persist($a);
        $em->flush();
        $actor = $this->getUser();
        $activityLogger->record(
            $actor instanceof User ? $actor : null,
            'appointment.create',
            'appointment',
            (string) $a->id,
            'Requested an appointment at '.$a->center,
        );

        $this->addFlash('success', 'Appointment request sent to '.$center->name.'. The center will confirm it.');

        return $this->redirectToRoute('app_dashboard');
    }
}
