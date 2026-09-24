<?php

namespace App\Controller;

use App\Entity\Attribute;
use App\Entity\ProfileAttributeValue;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\AttributeRepository;
use App\Repository\CvRepository;
use App\Repository\CvLikeRepository;
use App\Repository\ProfileAttributeValueRepository;
use App\Repository\PositionRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Service\PositionAccessEvaluator;
use App\Service\CvSearchIndexer;
use App\Service\BadgeService;
use App\Service\AttributeValueValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

#[Route('/profile')]
final class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile', methods: ['GET'])]
    public function index(
        AttributeRepository $attributeRepository,
        ProfileAttributeValueRepository $valueRepository,
        ProjectRepository $projectRepository,
        CvRepository $cvRepository,
        CvLikeRepository $likeRepository,
        PositionRepository $positionRepository,
        PositionAccessEvaluator $accessEvaluator,
        EntityManagerInterface $entityManager,
        Request $request,
        UserRepository $userRepository,
        BadgeService $badgeService,
    ): Response {
        $user = $this->profileOwner($request, $userRepository);
        $values = $valueRepository->findForUser($user);
        $valueByAttribute = [];
        foreach ($values as $profileValue) {
            $valueByAttribute[$profileValue->getAttribute()->getId()] = $profileValue;
        }
        $builtInAttributes = $attributeRepository->findBy(['isBuiltin' => true], ['name' => 'ASC']);
        foreach ($builtInAttributes as $attribute) {
            if (!isset($valueByAttribute[$attribute->getId()])) {
                $profileValue = new ProfileAttributeValue($user, $attribute);
                $entityManager->persist($profileValue);
                $values[] = $profileValue;
                $valueByAttribute[$attribute->getId()] = $profileValue;
            }
        }
        if ($entityManager->getUnitOfWork()->getScheduledEntityInsertions() !== []) {
            $entityManager->flush();
        }
        $selectedIds = array_map(static fn (ProfileAttributeValue $value): ?int => $value->getAttribute()->getId(), $values);

        $cvs = $cvRepository->findForCandidate($user);

        return $this->render('profile/index.html.twig', [
            'builtInAttributes' => $builtInAttributes,
            'selectedAttributes' => array_values(array_filter($values, static fn (ProfileAttributeValue $value): bool => !$value->getAttribute()->isBuiltin())),
            'availableAttributes' => array_values(array_filter($attributeRepository->findBy(['isBuiltin' => false], ['name' => 'ASC']), static fn (Attribute $attribute): bool => !in_array($attribute->getId(), $selectedIds, true))),
            'valueByAttribute' => $valueByAttribute,
            'projects' => $projectRepository->findForOwner($user),
            'knownTags' => $projectRepository->findDistinctTags(),
            'cvs' => $cvs,
            'cvLikeCounts' => $likeRepository->countsForCvs($cvs),
            'accessiblePositions' => $accessEvaluator->filterAccessible($user, $positionRepository->findForLibrary()),
            'badges' => $badgeService->earnedBy($user),
        ]);
    }

    #[Route('/badges.svg', name: 'app_profile_badges', methods: ['GET'])]
    public function badges(Request $request, UserRepository $userRepository, BadgeService $badgeService): Response
    {
        $user = $this->profileOwner($request, $userRepository);

        return new Response($badgeService->svg($badgeService->earnedBy($user)), Response::HTTP_OK, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="badges.svg"',
        ]);
    }

    #[Route('/attributes/add', name: 'app_profile_attribute_add', methods: ['POST'])]
    public function addAttribute(Request $request, AttributeRepository $attributeRepository, ProfileAttributeValueRepository $valueRepository, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $user = $this->profileOwner($request, $userRepository);
        if (!$this->isCsrfTokenValid('profile-attribute', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid form token.');
        }

        $attribute = $attributeRepository->find((int) $request->request->get('attribute_id'));
        if (!$attribute instanceof Attribute || $attribute->isBuiltin()) {
            throw $this->createNotFoundException('Attribute not found.');
        }

        if ($valueRepository->findOneBy(['user' => $user, 'attribute' => $attribute]) === null) {
            $entityManager->persist(new ProfileAttributeValue($user, $attribute));
            $attribute->markUsed();
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_profile', ['tab' => 'info']);
    }

    #[Route('/attributes/{id}/remove', name: 'app_profile_attribute_remove', methods: ['POST'])]
    public function removeAttribute(ProfileAttributeValue $profileValue, Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $user = $this->profileOwner($request, $userRepository);
        if ($profileValue->getUser() !== $user || $profileValue->getAttribute()->isBuiltin()) {
            throw new AccessDeniedException('This profile value cannot be removed.');
        }
        if (!$this->isCsrfTokenValid('profile-attribute-remove-'.$profileValue->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid form token.');
        }

        $entityManager->remove($profileValue);
        $entityManager->flush();

        return $this->redirectToRoute('app_profile', ['tab' => 'info']);
    }

    #[Route('/attributes/{id}/autosave', name: 'app_profile_attribute_autosave', methods: ['PATCH'])]
    public function autosaveAttribute(int $id, Request $request, ProfileAttributeValueRepository $valueRepository, CvSearchIndexer $indexer, EntityManagerInterface $entityManager, UserRepository $userRepository, AttributeValueValidator $valueValidator): JsonResponse
    {
        $user = $this->profileOwner($request, $userRepository);
        $profileValue = $valueRepository->findOneBy(['id' => $id, 'user' => $user]);
        if (!$profileValue instanceof ProfileAttributeValue) {
            return new JsonResponse(['message' => 'Profile value not found.'], Response::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !array_key_exists('value', $payload) || !isset($payload['version'])) {
            return new JsonResponse(['message' => 'A value and version are required.'], Response::HTTP_BAD_REQUEST);
        }

        if ((int) $payload['version'] !== $profileValue->getVersion()) {
            return new JsonResponse(['message' => 'This profile changed elsewhere. Reload before saving.'], Response::HTTP_CONFLICT);
        }

        $value = is_scalar($payload['value']) ? (string) $payload['value'] : null;
        if (($message = $valueValidator->validate($profileValue->getAttribute(), $value)) !== null) {
            return new JsonResponse(['message' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $profileValue->setValue($value);
        try {
            $entityManager->flush();
        } catch (OptimisticLockException) {
            return new JsonResponse(['message' => 'This profile changed elsewhere. Reload before saving.'], Response::HTTP_CONFLICT);
        }
        $indexer->refreshCandidate($user);

        return new JsonResponse(['version' => $profileValue->getVersion(), 'saved' => true]);
    }

    #[Route('/projects/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function newProject(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $user = $this->profileOwner($request, $userRepository);
        $project = new Project($user);
        if ($request->isMethod('POST')) {
            $this->checkProjectToken($request);
            $this->fillProject($project, $request);
            $entityManager->persist($project);
            $entityManager->flush();
            $this->addFlash('success', 'Project added.');

            return $this->redirectToRoute('app_profile', ['tab' => 'projects']);
        }

        return $this->render('profile/project_form.html.twig', ['project' => $project, 'heading' => 'Add project', 'knownTags' => []]);
    }

    #[Route('/projects/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    public function editProject(Project $project, Request $request, EntityManagerInterface $entityManager, ProjectRepository $projectRepository): Response
    {
        $this->assertProjectOwner($project);
        if ($request->isMethod('POST')) {
            $this->checkProjectToken($request);
            if ((int) $request->request->get('version') !== $project->getVersion()) {
                throw new ConflictHttpException('This project changed elsewhere. Reload before saving.');
            }
            $this->fillProject($project, $request);
            try {
                $entityManager->flush();
            } catch (OptimisticLockException) {
                throw new ConflictHttpException('This project changed elsewhere. Reload before saving.');
            }
            $this->addFlash('success', 'Project updated.');

            return $this->redirectToRoute('app_profile', ['tab' => 'projects']);
        }

        return $this->render('profile/project_form.html.twig', ['project' => $project, 'heading' => 'Edit project', 'knownTags' => $projectRepository->findDistinctTags()]);
    }

    #[Route('/projects/{id}/delete', name: 'app_project_delete', methods: ['POST'])]
    public function deleteProject(Project $project, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->assertProjectOwner($project);
        $this->checkProjectToken($request);
        $entityManager->remove($project);
        $entityManager->flush();

        return $this->redirectToRoute('app_profile', ['tab' => 'projects']);
    }

    private function profileOwner(?Request $request = null, ?UserRepository $userRepository = null): User
    {
        $user = $this->getUser();
        if (!$user instanceof User || (!$this->isGranted('ROLE_CANDIDATE') && !$this->isGranted('ROLE_ADMIN'))) {
            throw new AccessDeniedException('Candidate access is required.');
        }

        $targetId = $request?->query->getInt('user', 0);
        if ($this->isGranted('ROLE_ADMIN') && $targetId > 0 && $userRepository) {
            $target = $userRepository->find($targetId);
            if (!$target instanceof User) {
                throw $this->createNotFoundException('User not found.');
            }
            return $target;
        }

        return $user;
    }

    private function assertProjectOwner(Project $project): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }
        if ($project->getOwner() !== $this->profileOwner()) {
            throw new AccessDeniedException('This project belongs to another user.');
        }
    }

    private function checkProjectToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('project', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid form token.');
        }
    }

    private function fillProject(Project $project, Request $request): void
    {
        $project->setName((string) $request->request->get('name'))
            ->setDescription((string) $request->request->get('description'))
            ->setTags(preg_split('/[,\n]/', (string) $request->request->get('tags')) ?: []);
        $project->setPeriodStart($this->parseDate($request->request->get('period_start')))
            ->setPeriodEnd($this->parseDate($request->request->get('period_end')));
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date ?: null;
    }
}