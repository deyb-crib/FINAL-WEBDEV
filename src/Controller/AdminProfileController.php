<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AdminActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminProfileController extends AbstractController
{
    #[Route('/admin/profile', name: 'app_admin_profile', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em, AdminActivityLogger $activityLogger): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $name = $user->getName();
        $email = $user->getEmail();
        $errors = [];

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name'));
            $email = mb_strtolower(trim((string) $request->request->get('email')));

            if (!$this->isCsrfTokenValid('admin-profile', (string) $request->request->get('_token'))) {
                $errors[] = 'Your session expired. Please try again.';
            }
            if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
                $errors[] = 'Name must be between 2 and 100 characters.';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
                $errors[] = 'Please enter a valid email address.';
            } else {
                $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existing && $existing->getId() !== $user->getId()) {
                    $errors[] = 'An account with this email already exists.';
                }
            }

            if (!$errors) {
                $user->setName($name)->setEmail($email);
                $em->flush();
                $activityLogger->record($user, 'profile.update', 'user', (string) $user->getId(), 'Updated administrator profile');
                $this->addFlash('success', 'Profile updated.');

                return $this->redirectToRoute('app_admin_profile');
            }
        }

        return $this->render('admin/profile.html.twig', [
            'name' => $name,
            'email' => $email,
            'errors' => $errors,
        ]);
    }
}
