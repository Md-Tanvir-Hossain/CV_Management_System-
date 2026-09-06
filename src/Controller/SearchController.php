<?php

namespace App\Controller;

use App\Entity\CV;
use App\Entity\Position;
use App\Entity\User;
use App\Repository\CvLikeRepository;
use App\Repository\CvRepository;
use App\Repository\PositionRepository;
use App\Repository\SearchRepository;
use App\Service\PositionAccessEvaluator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function index(Request $request, SearchRepository $search, PositionRepository $positionRepository, CvRepository $cvRepository, CvLikeRepository $likeRepository, PositionAccessEvaluator $evaluator): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $type = (string) $request->query->get('type', 'all');
        $positions = [];
        $cvs = [];
        $likeCounts = [];
        if ($query !== '') {
            $positionIds = $type === 'cvs' ? [] : $search->positionIds($query);
            $positionsById = [];
            foreach ($positionRepository->findBy(['id' => $positionIds]) as $position) {
                $positionsById[$position->getId()] = $position;
            }
            $positions = array_values(array_filter(array_map(fn (int $id): ?Position => $positionsById[$id] ?? null, $positionIds), static fn (?Position $position): bool => $position instanceof Position));
            $user = $this->getUser();
            if ($user instanceof User && !$this->isGranted('ROLE_RECRUITER') && !$this->isGranted('ROLE_ADMIN')) {
                $positions = $evaluator->filterAccessible($user, $positions);
            } elseif (!$user instanceof User) {
                $positions = array_values(array_filter($positions, static fn (Position $position): bool => $position->isPublic()));
            }
            if ($type !== 'positions' && $user instanceof User && ($this->isGranted('ROLE_RECRUITER') || $this->isGranted('ROLE_ADMIN'))) {
                $cvIds = $search->publishedCvIds($query);
                $cvById = [];
                foreach ($cvRepository->findBy(['id' => $cvIds, 'status' => 'published']) as $cv) {
                    $cvById[$cv->getId()] = $cv;
                }
                $cvs = array_values(array_filter(array_map(fn (int $id): ?CV => $cvById[$id] ?? null, $cvIds), static fn (?CV $cv): bool => $cv instanceof CV));
                $likeCounts = $likeRepository->countsForCvs($cvs);
            }
        }
        return $this->render('search/index.html.twig', compact('query', 'type', 'positions', 'cvs', 'likeCounts'));
    }
}