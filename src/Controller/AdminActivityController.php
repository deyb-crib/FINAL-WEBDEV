<?php

namespace App\Controller;

use App\Entity\AdminActivity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/activity')]
class AdminActivityController extends AbstractController
{
    #[Route('', name: 'app_admin_activity', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $search = trim((string) $request->query->get('q', ''));
        $query = $em->createQueryBuilder()
            ->select('a')
            ->from(AdminActivity::class, 'a')
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        if ($search !== '') {
            $query->andWhere($query->expr()->orX(
                'a.actorName LIKE :search',
                'a.actorEmail LIKE :search',
                'a.summary LIKE :search',
                'a.action LIKE :search',
            ))->setParameter('search', '%'.$search.'%');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $countQuery = clone $query;
        $total = (int) $countQuery->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();
        $activities = $query->setFirstResult(($page - 1) * 50)->setMaxResults(50)->getQuery()->getResult();

        return $this->render('admin/activity.html.twig', [
            'activities' => $activities,
            'search' => $search,
            'page' => $page,
            'page_count' => max(1, (int) ceil($total / 50)),
            'total' => $total,
        ]);
    }
}
