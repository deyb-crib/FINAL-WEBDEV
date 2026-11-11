<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\DialysisCenter;
use App\Entity\User;
use App\Service\AdminActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users')]
class AdminUserController extends AbstractController
{
    private const PAGE_SIZE = 25;

    #[Route('', name: 'app_admin_users', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $role = (string) $request->query->get('role', 'all');
        $status = (string) $request->query->get('status', 'all');
        $page = max(1, $request->query->getInt('page', 1));

        $query = $em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->orderBy('u.createdAt', 'DESC')
            ->addOrderBy('u.id', 'DESC');

        if ($search !== '') {
            $query->andWhere($query->expr()->orX('u.name LIKE :search', 'u.email LIKE :search'))
                ->setParameter('search', '%'.$search.'%');
        }
        if ($role === 'admin') {
            $query->andWhere('u.roles LIKE :adminRole')->setParameter('adminRole', '%"ROLE_ADMIN"%');
        } elseif ($role === 'hospital_admin') {
            $query->andWhere('u.roles LIKE :hospitalRole')->setParameter('hospitalRole', '%"ROLE_HOSPITAL_ADMIN"%');
        } elseif ($role === 'patient') {
            $query->andWhere('u.roles NOT LIKE :adminRole')
                ->andWhere('u.roles NOT LIKE :hospitalRole')
                ->setParameter('adminRole', '%"ROLE_ADMIN"%')
                ->setParameter('hospitalRole', '%"ROLE_HOSPITAL_ADMIN"%');
        } else {
            $role = 'all';
        }
        if ($status === 'active') {
            $query->andWhere('u.active = true');
        } elseif ($status === 'inactive') {
            $query->andWhere('u.active = false');
        } else {
            $status = 'all';
        }

        $countQuery = clone $query;
        $total = (int) $countQuery->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
        $users = $query->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()
            ->getResult();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'search' => $search,
            'role_filter' => $role,
            'status_filter' => $status,
            'page' => $page,
            'page_count' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'total' => $total,
        ]);
    }

    #[Route('/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function newUser(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        AdminActivityLogger $activityLogger,
    ): Response {
        return $this->userForm(null, $request, $em, $hasher, $activityLogger);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editUser(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        AdminActivityLogger $activityLogger,
    ): Response {
        $user = $em->getRepository(User::class)->find($id);
        if (!$user) {
            throw $this->createNotFoundException();
        }

        return $this->userForm($user, $request, $em, $hasher, $activityLogger);
    }

    #[Route('/{id}', name: 'app_admin_user_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        $user = $em->getRepository(User::class)->find($id);
        if (!$user) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/user_show.html.twig', [
            'managed_user' => $user,
            'appointments' => $em->getRepository(Appointment::class)->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC'],
                10,
            ),
            'appointment_count' => $em->getRepository(Appointment::class)->count(['user' => $user]),
        ]);
    }

    #[Route('/{id}/status', name: 'app_admin_user_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleStatus(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        AdminActivityLogger $activityLogger,
    ): Response {
        $user = $em->getRepository(User::class)->find($id);
        if (!$user) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('admin-user-status-'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');
        } elseif ($this->isCurrentUser($user)) {
            $this->addFlash('error', 'You cannot deactivate the account you are currently using.');
        } elseif ($user->isActive() && $user->isAdmin() && $this->activeAdminCount($em) <= 1) {
            $this->addFlash('error', 'The last active administrator cannot be deactivated.');
        } else {
            $user->setActive(!$user->isActive());
            $em->flush();
            $activityLogger->record(
                $this->currentUser(),
                $user->isActive() ? 'user.activate' : 'user.deactivate',
                'user',
                (string) $user->getId(),
                ($user->isActive() ? 'Activated ' : 'Deactivated ').$user->getEmail(),
            );
            $this->addFlash('success', $user->getEmail().' is now '.($user->isActive() ? 'active.' : 'inactive.'));
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/{id}/delete', name: 'app_admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        AdminActivityLogger $activityLogger,
    ): Response {
        $user = $em->getRepository(User::class)->find($id);
        if (!$user) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('admin-user-delete-'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Your session expired. Please try again.');
        } elseif ($this->isCurrentUser($user)) {
            $this->addFlash('error', 'You cannot delete the account you are currently using.');
        } elseif ($user->isAdmin() && $user->isActive() && $this->activeAdminCount($em) <= 1) {
            $this->addFlash('error', 'The last active administrator cannot be deleted.');
        } elseif ($em->getRepository(Appointment::class)->count(['user' => $user]) > 0) {
            $this->addFlash('error', 'This account has appointment records. Deactivate it instead to preserve its history.');
        } else {
            $email = $user->getEmail();
            $em->remove($user);
            $em->flush();
            $activityLogger->record($this->currentUser(), 'user.delete', 'user', (string) $id, 'Deleted account '.$email);
            $this->addFlash('success', 'User account deleted.');
        }

        return $this->redirectToRoute('app_admin_users');
    }

    private function userForm(
        ?User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        AdminActivityLogger $activityLogger,
    ): Response {
        $isNew = $user === null;
        $user ??= new User();
        $errors = [];
        $name = $user->getName();
        $email = $user->getEmail();
        $isAdmin = $user->isAdmin();
        $isHospitalAdmin = $user->isHospitalAdmin();
        $isActive = $user->isActive();
        $assignedCenterIds = array_map(
            static fn (DialysisCenter $center): int => (int) $center->id,
            $user->getAssignedCenters()->toArray(),
        );

        if ($request->isMethod('POST')) {
            $p = $request->request;
            $name = trim((string) $p->get('name'));
            $email = mb_strtolower(trim((string) $p->get('email')));
            $password = (string) $p->get('password');
            $role = (string) $p->get('role');
            $isAdmin = $role === 'admin';
            $isHospitalAdmin = $role === 'hospital_admin';
            $isActive = $p->getBoolean('is_active');
            $selectedCenterIds = [];
            $selectedCenters = [];

            if (!$this->isCsrfTokenValid('admin-user-form'.($user->getId() ?? 'new'), (string) $p->get('_token'))) {
                $errors[] = 'Your session expired. Please try again.';
            }
            if (!\in_array($role, ['patient', 'admin', 'hospital_admin'], true)) {
                $errors[] = 'Please select a valid account role.';
            }
            foreach ($p->all('assigned_centers') as $selectedCenterId) {
                if (!\is_string($selectedCenterId) && !\is_int($selectedCenterId)) {
                    $errors[] = 'Please select valid dialysis centers.';
                    continue;
                }
                $selectedCenterIds[] = (string) $selectedCenterId;
            }
            $selectedCenterIds = array_values(array_unique($selectedCenterIds));
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
            if ($isNew && mb_strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters.';
            } elseif (!$isNew && $password !== '' && mb_strlen($password) < 8) {
                $errors[] = 'A new password must be at least 8 characters.';
            }
            foreach ($selectedCenterIds as $centerId) {
                if (!ctype_digit($centerId)) {
                    $errors[] = 'Please select valid dialysis centers.';
                    continue;
                }

                $center = $em->getRepository(DialysisCenter::class)->find((int) $centerId);
                if (!$center) {
                    $errors[] = 'One of the selected dialysis centers no longer exists.';
                    continue;
                }
                $selectedCenters[] = $center;
            }
            if ($isHospitalAdmin && !$selectedCenters) {
                $errors[] = 'Assign at least one dialysis center to a hospital administrator.';
            }

            $isSelf = !$isNew && $this->isCurrentUser($user);
            if ($isSelf && (!$isAdmin || !$isActive)) {
                $errors[] = 'You cannot remove your own administrator access or deactivate your own account.';
            }
            if (!$isNew && $user->isAdmin() && $user->isActive() && (!$isAdmin || !$isActive) && $this->activeAdminCount($em) <= 1) {
                $errors[] = 'The last active administrator must remain active with administrator access.';
            }

            if (!$errors) {
                $user->setName($name)
                    ->setEmail($email)
                    ->setActive($isActive)
                    ->setAdmin($isAdmin)
                    ->setHospitalAdmin($isHospitalAdmin);
                foreach ($user->getAssignedCenters()->toArray() as $assignedCenter) {
                    $user->removeAssignedCenter($assignedCenter);
                }
                foreach ($isHospitalAdmin ? $selectedCenters : [] as $center) {
                    $user->assignCenter($center);
                }
                if ($password !== '') {
                    $user->setPassword($hasher->hashPassword($user, $password));
                }
                if ($isNew) {
                    $em->persist($user);
                }
                $em->flush();
                $activityLogger->record(
                    $this->currentUser(),
                    $isNew ? 'user.create' : 'user.update',
                    'user',
                    (string) $user->getId(),
                    ($isNew ? 'Created ' : 'Updated ').$user->getEmail(),
                );
                $this->addFlash('success', $isNew ? 'User account created.' : 'User account updated.');

                return $this->redirectToRoute('app_admin_user_show', ['id' => $user->getId()]);
            }

            $assignedCenterIds = array_values(array_map('intval', $selectedCenterIds));
        }

        return $this->render('admin/user_form.html.twig', [
            'managed_user' => $user,
            'is_new' => $isNew,
            'errors' => $errors,
            'name' => $name,
            'email' => $email,
            'is_admin' => $isAdmin,
            'is_hospital_admin' => $isHospitalAdmin,
            'is_active' => $isActive,
            'centers' => $em->getRepository(DialysisCenter::class)->findBy([], ['name' => 'ASC']),
            'assigned_center_ids' => $assignedCenterIds,
        ]);
    }

    private function isCurrentUser(User $user): bool
    {
        return $user->getId() === $this->currentUser()?->getId();
    }

    private function currentUser(): ?User
    {
        $current = $this->getUser();

        return $current instanceof User ? $current : null;
    }

    private function activeAdminCount(EntityManagerInterface $em): int
    {
        return (int) $em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM users
             WHERE is_active = 1 AND JSON_CONTAINS(roles, JSON_QUOTE('ROLE_ADMIN'))"
        );
    }
}
