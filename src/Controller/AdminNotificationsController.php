<?php

namespace App\Controller;

use App\Service\AdminDashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminNotificationsController extends AbstractController
{
    #[Route('/admin/notifications', name: 'app_admin_notifications', methods: ['GET'])]
    public function index(AdminDashboardService $dashboard): Response
    {
        $overview = $dashboard->overview();
        $notifications = [];

        foreach ([
            ['count' => $overview['alerts']['pending_appointments'], 'title' => 'Appointment requests need review', 'description' => 'Pending patient appointment requests are waiting for an update.', 'route' => 'app_admin_records', 'tone' => 'warning'],
            ['count' => $overview['alerts']['overdue_appointments'], 'title' => 'Confirmed appointments are overdue', 'description' => 'Confirmed appointment dates have passed. Review their records and update their status.', 'route' => 'app_admin_records', 'tone' => 'danger'],
            ['count' => $overview['alerts']['inactive_centers'], 'title' => 'Dialysis centers are inactive', 'description' => 'Inactive centers are not listed for new bookings.', 'route' => 'app_admin_centers', 'tone' => 'info'],
        ] as $notification) {
            if ($notification['count'] > 0) {
                $notifications[] = $notification;
            }
        }

        return $this->render('admin/notifications.html.twig', [
            'notifications' => $notifications,
            'recent_activities' => $overview['recent_activities'],
        ]);
    }
}
