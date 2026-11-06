<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/signup', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $errors = [];
        $name = '';
        $email = '';

        if ($request->isMethod('POST')) {
            $p = $request->request;
            $name = trim((string) $p->get('name'));
            $email = mb_strtolower(trim((string) $p->get('email')));
            $password = (string) $p->get('password');
            $confirm = (string) $p->get('confirm_password');

            if (!$this->isCsrfTokenValid('register', (string) $p->get('_csrf_token'))) {
                $errors[] = 'Your session expired. Please try again.';
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
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'Account created. You can now log in.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/signup.html.twig', [
            'errors' => $errors,
            'name' => $name,
            'email' => $email,
        ]);
    }
}
