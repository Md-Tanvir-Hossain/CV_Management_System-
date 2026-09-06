<?php

namespace App\Controller;

use App\Entity\DiscussionPost;
use App\Entity\Position;
use App\Entity\User;
use App\Repository\DiscussionPostRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\CommonMark\CommonMarkConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/positions/{id}/discussion')]
final class DiscussionController extends AbstractController
{
    #[Route('', name: 'app_position_discussion', methods: ['GET', 'POST'])]
    public function index(Position $position, Request $request, DiscussionPostRepository $repository, EntityManagerInterface $entityManager, CommonMarkConverter $markdown): Response
    {
        $user = $this->authenticatedUser();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('discussion-'.$position->getId(), $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid form token.');
            }
            $content = trim((string) $request->request->get('content'));
            if ($content === '') {
                $this->addFlash('warning', 'Discussion content cannot be empty.');
            } else {
                $entityManager->persist(new DiscussionPost($position, $user, $content));
                $entityManager->flush();
                return $this->redirectToRoute('app_position_discussion', ['id' => $position->getId()]);
            }
        }

        return $this->render('position/discussion.html.twig', [
            'position' => $position,
            'posts' => $this->renderPosts($position, $repository->findForPosition($position), $markdown),
        ]);
    }

    #[Route('/updates', name: 'app_position_discussion_updates', methods: ['GET'])]
    public function updates(Position $position, Request $request, DiscussionPostRepository $repository, CommonMarkConverter $markdown): JsonResponse
    {
        $this->authenticatedUser();
        $posts = $this->renderPosts($position, $repository->findForPosition($position, max(0, $request->query->getInt('since'))), $markdown);
        return new JsonResponse($posts);
    }

    #[Route('/profiles/{profileId}', name: 'app_public_profile', methods: ['GET'])]
    public function publicProfile(int $profileId, UserRepository $userRepository): Response
    {
        if (!$this->isGranted('ROLE_RECRUITER') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Recruiter access is required.');
        }
        $user = $userRepository->find($profileId);
        if (!$user instanceof User) {
            throw $this->createNotFoundException('Profile not found.');
        }
        return $this->render('profile/public.html.twig', ['profileUser' => $user]);
    }

    /** @param list<DiscussionPost> $posts @return list<array{id: int, author: string, authorUrl: string, content: string, createdAt: string}> */
    private function renderPosts(Position $position, array $posts, CommonMarkConverter $markdown): array
    {
        return array_map(fn (DiscussionPost $post): array => [
            'id' => $post->getId(),
            'author' => $post->getAuthor()->getEmail(),
            'authorUrl' => $this->generateUrl('app_public_profile', ['id' => $position->getId(), 'profileId' => $post->getAuthor()->getId()]),
            'content' => $markdown->convert($post->getContent())->getContent(),
            'createdAt' => $post->getCreatedAt()->format(DATE_ATOM),
        ], $posts);
    }

    private function authenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Sign in to participate in discussions.');
        }
        return $user;
    }
}