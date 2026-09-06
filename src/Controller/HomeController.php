<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Repository\DashboardRepository;
use App\Repository\PositionRepository;
use App\Service\PositionAccessEvaluator;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(PositionRepository $positionRepository, DashboardRepository $dashboardRepository, PositionAccessEvaluator $evaluator): Response
    {
        $latestPositions = $positionRepository->findLatest();
        $popularPositions = $positionRepository->findMostPopular();
        $user = $this->getUser();
        if ($user instanceof User && !$this->isGranted('ROLE_RECRUITER') && !$this->isGranted('ROLE_ADMIN')) {
            $latestPositions = $evaluator->filterAccessible($user, $latestPositions);
            $popularPositions = $evaluator->filterAccessible($user, $popularPositions);
        } elseif (!$user instanceof User) {
            $latestPositions = array_values(array_filter($latestPositions, static fn ($position): bool => $position->isPublic()));
            $popularPositions = array_values(array_filter($popularPositions, static fn ($position): bool => $position->isPublic()));
        }
        return $this->render('home/index.html.twig', [
            'latestPositions' => $latestPositions,
            'popularPositions' => $popularPositions,
            'statistics' => $dashboardRepository->statistics(),
            'tagCloud' => $dashboardRepository->tagCloud(),
            'isRecruiter' => $this->isGranted('ROLE_RECRUITER') || $this->isGranted('ROLE_ADMIN'),
            'tagSearchType' => $this->isGranted('ROLE_RECRUITER') || $this->isGranted('ROLE_ADMIN') ? 'cvs' : 'positions',
        ]);
    }
}