<?php

namespace App\Controller;

use App\Entity\Attribute;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\PositionAttribute;
use App\Repository\CvLikeRepository;
use App\Repository\CvRepository;
use App\Enum\AccessRuleOperator;
use App\Enum\PositionLevel;
use App\Repository\AttributeRepository;
use App\Repository\PositionAccessRuleRepository;
use App\Repository\PositionAttributeRepository;
use App\Repository\PositionRepository;
use App\Service\PositionAccessEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/positions')]
final class PositionController extends AbstractController
{
    #[Route('', name: 'app_position_index', methods: ['GET'])]
    public function index(Request $request, PositionRepository $repository, PositionAccessEvaluator $evaluator): Response
    {
        $positions = $repository->findForLibrary($request->query->get('q'));
        if (!$this->isGranted('ROLE_RECRUITER') && !$this->isGranted('ROLE_ADMIN')) {
            $user = $this->getUser();
            if ($user instanceof \App\Entity\User) {
                $positions = $evaluator->filterAccessible($user, $positions);
            } else {
                $positions = array_values(array_filter($positions, static fn (Position $position): bool => $position->isPublic()));
            }
        }

        return $this->render('position/index.html.twig', [
            'positions' => $positions,
            'query' => $request->query->get('q', ''),
            'canManage' => $this->isGranted('ROLE_RECRUITER') || $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    #[Route('/{id}/cvs', name: 'app_position_cvs', methods: ['GET'])]
    public function cvs(Position $position, CvRepository $cvRepository, CvLikeRepository $likeRepository): Response
    {
        $this->requireRecruiter();
        $cvs = $cvRepository->findPublishedForPosition($position);

        return $this->render('position/cvs.html.twig', [
            'position' => $position,
            'cvs' => $cvs,
            'likeCounts' => $likeRepository->countsForCvs($cvs),
        ]);
    }

    #[Route('/new', name: 'app_position_new', methods: ['GET', 'POST'])]
    public function new(Request $request, AttributeRepository $attributeRepository, EntityManagerInterface $entityManager, PositionAccessEvaluator $evaluator): Response
    {
        $this->requireRecruiter();
        $position = new Position();

        return $this->editForm($position, $request, $attributeRepository, $entityManager, $evaluator, false, [], [], null, null);
    }

    #[Route('/{id}/edit', name: 'app_position_edit', methods: ['GET', 'POST'])]
    public function edit(Position $position, Request $request, AttributeRepository $attributeRepository, PositionAttributeRepository $positionAttributeRepository, PositionAccessRuleRepository $ruleRepository, EntityManagerInterface $entityManager, PositionAccessEvaluator $evaluator): Response
    {
        $this->requireRecruiter();
        $selectedAttributeIds = array_map(static fn (PositionAttribute $item): ?int => $item->getAttribute()->getId(), $positionAttributeRepository->findForPosition($position));
        $rules = $ruleRepository->findForPosition($position);

        return $this->editForm($position, $request, $attributeRepository, $entityManager, $evaluator, true, $selectedAttributeIds, $rules, $positionAttributeRepository, $ruleRepository);
    }

    #[Route('/{id}/duplicate', name: 'app_position_duplicate', methods: ['POST'])]
    public function duplicate(Position $position, Request $request, PositionAttributeRepository $attributeRepository, PositionAccessRuleRepository $ruleRepository, EntityManagerInterface $entityManager): Response
    {
        $this->requireRecruiter();
        if (!$this->isCsrfTokenValid('duplicate-position-'.$position->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid form token.');
        }
        $copy = new Position();
        $copy->setTitle($position->getTitle().' (copy)')
            ->setShortDescription($position->getShortDescription())
            ->setIsPublic($position->isPublic())
            ->setCompany($position->getCompany())
            ->setLevel($position->getLevel())
            ->setProjectTags($position->getProjectTags())
            ->setMaxProjects($position->getMaxProjects());
        $entityManager->persist($copy);
        foreach ($attributeRepository->findForPosition($position) as $item) {
            $entityManager->persist(new PositionAttribute($copy, $item->getAttribute()));
        }
        foreach ($ruleRepository->findForPosition($position) as $rule) {
            $entityManager->persist(new PositionAccessRule($copy, $rule->getAttribute(), $rule->getOperator(), $rule->getComparisonValue()));
        }
        $entityManager->flush();
        $this->addFlash('success', 'Position duplicated.');

        return $this->redirectToRoute('app_position_edit', ['id' => $copy->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_position_delete', methods: ['POST'])]
    public function delete(Position $position, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->requireRecruiter();
        if (!$this->isCsrfTokenValid('delete-position-'.$position->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid form token.');
        }
        $entityManager->remove($position);
        $entityManager->flush();
        $this->addFlash('success', 'Position deleted.');

        return $this->redirectToRoute('app_position_index');
    }

    private function editForm(Position $position, Request $request, AttributeRepository $attributeRepository, EntityManagerInterface $entityManager, PositionAccessEvaluator $evaluator, bool $isEdit, array $selectedAttributeIds = [], array $rules = [], ?PositionAttributeRepository $positionAttributeRepository = null, ?PositionAccessRuleRepository $ruleRepository = null): Response
    {
        $attributes = $attributeRepository->findBy([], ['name' => 'ASC']);
        $rulesByAttribute = [];
        foreach ($rules as $rule) {
            $rulesByAttribute[$rule->getAttribute()->getId()] = $rule;
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('position', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid form token.');
            }
            if ($isEdit && (int) $request->request->get('version') !== $position->getVersion()) {
                throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('This position changed elsewhere. Reload before saving.');
            }
            $this->fillPosition($position, $request);
            $selectedIds = array_map('intval', $request->request->all('attributes'));
            $submittedRules = $request->request->all('rules');
            $newRules = [];
            foreach ($attributes as $attribute) {
                $attributeId = $attribute->getId();
                if (!in_array($attributeId, $selectedIds, true)) {
                    continue;
                }
                $ruleData = is_array($submittedRules[$attributeId] ?? null) ? $submittedRules[$attributeId] : [];
                $operatorValue = trim((string) ($ruleData['operator'] ?? ''));
                if ($operatorValue !== '') {
                    $operator = AccessRuleOperator::tryFrom($operatorValue);
                    if (!$operator || !in_array($operator, $evaluator->allowedOperators($attribute->getType()), true)) {
                        $this->addFlash('warning', 'One access rule uses an invalid operator for its attribute type.');
                        return $this->render('position/form.html.twig', $this->formViewData($position, $attributes, $selectedIds, $submittedRules, $evaluator));
                    }
                    $value = isset($ruleData['value']) && is_scalar($ruleData['value']) ? (string) $ruleData['value'] : null;
                    $newRules[] = new PositionAccessRule($position, $attribute, $operator, $value);
                }
            }
            if ($isEdit && $positionAttributeRepository && $ruleRepository) {
                foreach ($positionAttributeRepository->findForPosition($position) as $item) {
                    $entityManager->remove($item);
                }
                foreach ($ruleRepository->findForPosition($position) as $rule) {
                    $entityManager->remove($rule);
                }
                $entityManager->flush();
            }
            $entityManager->persist($position);
            foreach ($attributes as $attribute) {
                if (in_array($attribute->getId(), $selectedIds, true)) {
                    $entityManager->persist(new PositionAttribute($position, $attribute));
                }
            }
            foreach ($newRules as $rule) {
                $entityManager->persist($rule);
            }
            $entityManager->flush();
            $this->addFlash('success', $isEdit ? 'Position updated.' : 'Position created.');

            return $this->redirectToRoute('app_position_index');
        }

        $ruleInput = [];
        foreach ($rulesByAttribute as $attributeId => $rule) {
            $ruleInput[$attributeId] = ['enabled' => '1', 'operator' => $rule->getOperator()->value, 'value' => $rule->getComparisonValue() ?? ''];
        }

        return $this->render('position/form.html.twig', $this->formViewData($position, $attributes, $selectedAttributeIds, $ruleInput, $evaluator));
    }

    private function formViewData(Position $position, array $attributes, array $selectedIds, array $rules, PositionAccessEvaluator $evaluator): array
    {
        $operators = [];
        foreach ($attributes as $attribute) {
            $operators[$attribute->getId()] = $evaluator->allowedOperators($attribute->getType());
        }

        return ['position' => $position, 'attributes' => $attributes, 'selectedIds' => $selectedIds, 'rules' => $rules, 'operators' => $operators, 'levels' => PositionLevel::cases()];
    }

    private function fillPosition(Position $position, Request $request): void
    {
        $position->setTitle((string) $request->request->get('title'))
            ->setShortDescription((string) $request->request->get('short_description'))
            ->setIsPublic($request->request->getBoolean('is_public'))
            ->setCompany($request->request->get('company'))
            ->setProjectTags(preg_split('/[,\n]/', (string) $request->request->get('project_tags')) ?: [])
            ->setMaxProjects(max(0, (int) $request->request->get('max_projects', 3)));
        $level = PositionLevel::tryFrom((string) $request->request->get('level'));
        $position->setLevel($level);
        $position->touch();
    }

    private function requireRecruiter(): void
    {
        if (!$this->isGranted('ROLE_RECRUITER') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Recruiter access is required.');
        }
    }
}