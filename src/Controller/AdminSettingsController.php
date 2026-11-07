<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\KernelInterface;

class AdminSettingsController extends AbstractController
{
    #[Route('/admin/settings', name: 'app_admin_settings', methods: ['GET'])]
    public function index(EntityManagerInterface $em, KernelInterface $kernel): Response
    {
        $connection = $em->getConnection();
        $platformName = (new \ReflectionClass($connection->getDatabasePlatform()))->getShortName();
        $platformName = preg_replace('/Platform$/', '', $platformName);

        return $this->render('admin/settings.html.twig', [
            'environment' => $kernel->getEnvironment(),
            'php_version' => PHP_VERSION,
            'timezone' => date_default_timezone_get(),
            'database_platform' => $platformName,
            'database_available' => (int) $connection->fetchOne('SELECT 1') === 1,
        ]);
    }
}
