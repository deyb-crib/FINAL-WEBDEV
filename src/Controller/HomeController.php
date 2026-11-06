<?php

namespace App\Controller;

use App\Entity\DialysisCenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $em): Response
    {
        $centers = $em->getRepository(DialysisCenter::class)
            ->findBy(['status' => 'Active'], ['id' => 'ASC'], 3);

        return $this->render('home/index.html.twig', ['centers' => $centers]);
    }

    #[Route('/hospitals', name: 'app_hospitals')]
    public function hospitals(EntityManagerInterface $em): Response
    {
        $centers = $em->getRepository(DialysisCenter::class)
            ->findBy(['status' => 'Active'], ['name' => 'ASC']);

        return $this->render('home/hospitals.html.twig', ['centers' => $centers]);
    }

    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('about/about.html.twig');
    }
}
