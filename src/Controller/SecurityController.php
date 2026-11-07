<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $auth): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'app_admin_dashboard' : 'app_dashboard');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $auth->getLastUsername(),
            'error' => $auth->getLastAuthenticationError(),
            'admin_mode' => false,
            'already_authenticated' => false,
        ]);
    }

    #[Route('/admin/login', name: 'app_admin_login', methods: ['GET'])]
    public function adminLogin(AuthenticationUtils $auth): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $auth->getLastUsername(),
            'error' => $auth->getLastAuthenticationError(),
            'admin_mode' => true,
            'already_authenticated' => $this->getUser() !== null,
        ]);
    }

    #[Route('/admin-access-denied', name: 'app_admin_access_denied', methods: ['GET'])]
    public function adminAccessDenied(): Response
    {
        $this->addFlash('error', 'This account does not have administrator access. Sign in with an admin account or ask an administrator to grant access.');

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('Handled by the firewall.');
    }
}
