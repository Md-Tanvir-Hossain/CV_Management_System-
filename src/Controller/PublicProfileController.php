<?php

namespace App\Controller;

use App\Repository\CvLikeRepository;
use App\Repository\CvRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class PublicProfileController extends AbstractController
{
    #[Route('/profiles/{id}', name: 'app_public_profile_standalone', methods: ['GET'])]
    public function show(int $id, UserRepository $userRepository, CvRepository $cvRepository, CvLikeRepository $likeRepository): Response
    {
        if (!$this->isGranted('ROLE_RECRUITER') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Recruiter access is required.');
        }

        $profileUser = $userRepository->find($id);
        if (!$profileUser) {
            throw $this->createNotFoundException('Profile not found.');
        }
        $cvs = $cvRepository->findPublishedForCandidate($profileUser);

        return $this->render('profile/public.html.twig', [
            'profileUser' => $profileUser,
            'cvs' => $cvs,
            'likeCounts' => $likeRepository->countsForCvs($cvs),
        ]);
    }
}