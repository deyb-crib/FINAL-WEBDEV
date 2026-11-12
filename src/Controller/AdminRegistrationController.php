<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AdminActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AdminRegistrationController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(default:admin_signup_code_default:ADMIN_SIGNUP_CODE)%')]
        private readonly string $adminSignupCode,
    ) {
    }

    #[Route('/admin/signup', name: 'app_admin_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        AdminActivityLogger $activityLogger,
    ): Response {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }
        if ($this->isGranted('ROLE_HOSPITAL_STAFF')) {
            return $this->redirectToRoute('app_hospital_admin_dashboard');
        }

        $configured = strlen($this->adminSignupCode) >= 64;
        $errors = [];
        $name = '';
        $email = '';

        if ($configured && $request->isMethod('POST')) {
            $p = $request->request;
            $name = trim((string) $p->get('name'));
            $email = mb_strtolower(trim((string) $p->get('email')));
            $password = (string) $p->get('password');
            $confirm = (string) $p->get('confirm_password');
            $inviteCode = (string) $p->get('invite_code');

            if (!$this->isCsrfTokenValid('admin-register', (string) $p->get('_csrf_token'))) {
                $errors[] = 'Your session expired. Please try again.';
            }
            if (!hash_equals($this->adminSignupCode, $inviteCode)) {
                $errors[] = 'The admin invite code is invalid.';
            }
            if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
                $errors[] = 'Please enter your full name (2-100 characters).';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
                $errors[] = 'Please enter a valid email address.';
            } elseif ($em->getRepository(User::class)->findOneBy(['email' => $email])) {
                $errors[] = 'An account with this email already exists.';
            }
            if (mb_strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            }
            if ($password !== $confirm) {
                $errors[] = 'Passwords do not match.';
            }

            if (!$errors) {
                $user = (new User())->setName($name)->setEmail($email);
                $user->setPassword($hasher->hashPassword($user, $password));
                $user->addRole('ROLE_ADMIN');
                $em->persist($user);
                $em->flush();
                $activityLogger->record($user, 'user.admin_register', 'user', (string) $user->getId(), 'Created an administrator account');

                $this->addFlash('success', 'Administrator account created. You can now sign in.');

                return $this->redirectToRoute('app_admin_login');
            }
        }

        return $this->render('security/admin_signup.html.twig', [
            'configured' => $configured,
            'errors' => $errors,
            'name' => $name,
            'email' => $email,
        ], new Response(status: $configured ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE));
    }
}
