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
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $appointments = $em->getRepository(Appointment::class)->findBy(
            ['user' => $this->getUser()],
            ['appointmentDate' => 'DESC', 'appointmentTime' => 'DESC']
        );

        return $this->render('dashboard/index.html.twig', ['appointments' => $appointments]);
    }

    #[Route('/dashboard/appointments/{id}/cancel', name: 'app_appointment_cancel', methods: ['POST'])]
    public function cancel(int $id, Request $request, EntityManagerInterface $em, AdminActivityLogger $activityLogger): Response
    {
        $a = $em->getRepository(Appointment::class)->find($id);
        if (!$a || $a->user?->getId() !== $this->getUser()?->getId()) {
            throw $this->createNotFoundException();
        }
        if ($this->isCsrfTokenValid('cancel'.$id, (string) $request->request->get('_token')) && $a->status !== 'Cancelled') {
            $a->status = 'Cancelled';
            $em->flush();
            $actor = $this->getUser();
            $activityLogger->record(
                $actor instanceof User ? $actor : null,
                'appointment.cancel',
                'appointment',
                (string) $a->id,
                'Cancelled an appointment at '.$a->center,
            );
            $this->addFlash('success', 'Appointment cancelled.');
        }

        return $this->redirectToRoute('app_dashboard');
    }
}
