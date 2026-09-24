<?php

namespace App\Controller;

use App\Entity\Attribute;
use App\Entity\CV;
use App\Entity\CvLike;
use App\Entity\Position;
use App\Entity\ProfileAttributeValue;
use App\Entity\User;
use App\Repository\CvRepository;
use App\Repository\CvLikeRepository;
use App\Repository\PositionAttributeRepository;
use App\Repository\ProfileAttributeValueRepository;
use App\Repository\UserRepository;
use App\Service\CvProjection;
use App\Service\CvSearchIndexer;
use App\Service\PositionAccessEvaluator;
use App\Service\AttributeValueValidator;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/cvs')]
final class CvController extends AbstractController
{
    #[Route('/positions/{id}/new', name: 'app_cv_new', methods: ['POST'])]
    public function new(Position $position, Request $request, PositionAccessEvaluator $evaluator, CvRepository $cvRepository, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $candidate = $this->candidateOwner($request, $userRepository);
        if (!$this->isCsrfTokenValid('cv-new-'.$position->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid form token.');
        }
        if (!$evaluator->isAccessible($candidate, $position)) {
            throw $this->createAccessDeniedException('This position is not currently accessible.');
        }
        $cv = $cvRepository->findOneBy(['candidate' => $candidate, 'position' => $position]);
        if (!$cv instanceof CV) {
            $cv = new CV($candidate, $position);
            $entityManager->persist($cv);
            try {
                $entityManager->flush();
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
                throw new ConflictHttpException('A CV already exists for this position.');
            }
        }

        return $this->redirectToRoute('app_cv_show', ['id' => $cv->getId()]);
    }

    #[Route('/{id}', name: 'app_cv_show', methods: ['GET'])]
    public function show(CV $cv, CvProjection $projection, CvLikeRepository $likeRepository): Response
    {
        $this->assertCanView($cv);
        $data = $projection->forCv($cv);

        return $this->render('cv/show.html.twig', [
            'cv' => $cv,
            'attributes' => $data['attributes'],
            'projects' => $data['projects'],
            'missingValues' => $projection->hasMissingValues($data['attributes']),
            'editable' => $this->isOwner($cv),
            'likeCount' => $likeRepository->countForCv($cv),
            'liked' => $this->isRecruiter() && $likeRepository->isLikedBy($cv, $this->getUser()),
        ]);
    }

    #[Route('/{id}/pdf', name: 'app_cv_pdf', methods: ['GET'])]
    public function pdf(CV $cv, Request $request, CvProjection $projection): Response
    {
        $this->assertCanView($cv);
        if ($cv->getStatus()->value !== 'published') {
            throw $this->createNotFoundException('CV not found.');
        }

        $cvUrl = $this->generateUrl('app_cv_show', ['id' => $cv->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $qrCode = new Builder(data: $cvUrl, size: 120, margin: 4);
        $qrCode = $qrCode->build();
        $data = $projection->forCv($cv);
        $html = $this->renderView('cv/show.html.twig', [
            'cv' => $cv,
            'attributes' => $data['attributes'],
            'projects' => $data['projects'],
            'missingValues' => false,
            'editable' => false,
            'likeCount' => 0,
            'liked' => false,
            'pdf' => true,
            'qrCodeDataUri' => $qrCode->getDataUri(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return new Response($dompdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="cv-'.$cv->getId().'.pdf"',
        ]);
    }

    #[Route('/{id}/like', name: 'app_cv_like', methods: ['POST'])]
    public function toggleLike(CV $cv, Request $request, CvLikeRepository $likeRepository, EntityManagerInterface $entityManager): Response
    {
        $recruiter = $this->getUser();
        if (!$recruiter instanceof User || !$this->isRecruiter()) {
            throw new AccessDeniedException('Recruiter access is required.');
        }
        if ($cv->getStatus()->value !== 'published' || !$this->isCsrfTokenValid('cv-like-'.$cv->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('This CV cannot be liked.');
        }
        $like = $likeRepository->findOneBy(['cv' => $cv, 'recruiter' => $recruiter]);
        if ($like instanceof CvLike) {
            $entityManager->remove($like);
        } else {
            $entityManager->persist(new CvLike($cv, $recruiter));
        }
        $entityManager->flush();
        return $this->redirectToRoute('app_cv_show', ['id' => $cv->getId()]);
    }

    #[Route('/{id}/attributes/{attributeId}', name: 'app_cv_attribute_update', methods: ['PATCH'])]
    public function updateAttribute(CV $cv, int $attributeId, Request $request, PositionAttributeRepository $positionAttributeRepository, ProfileAttributeValueRepository $valueRepository, CvSearchIndexer $indexer, EntityManagerInterface $entityManager, AttributeValueValidator $valueValidator): JsonResponse
    {
        $this->assertOwner($cv);
        $attribute = $entityManager->getRepository(Attribute::class)->find($attributeId);
        if (!$attribute instanceof Attribute || $positionAttributeRepository->findOneBy(['position' => $cv->getPosition(), 'attribute' => $attribute]) === null) {
            return new JsonResponse(['message' => 'CV attribute not found.'], Response::HTTP_NOT_FOUND);
        }
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !array_key_exists('value', $payload) || !isset($payload['version'])) {
            return new JsonResponse(['message' => 'A value and version are required.'], Response::HTTP_BAD_REQUEST);
        }
        $value = $valueRepository->findOneBy(['user' => $cv->getCandidate(), 'attribute' => $attribute]);
        if (!$value instanceof ProfileAttributeValue) {
            if ((int) $payload['version'] !== 1) {
                return new JsonResponse(['message' => 'This profile changed elsewhere. Reload before saving.'], Response::HTTP_CONFLICT);
            }
            $value = new ProfileAttributeValue($cv->getCandidate(), $attribute);
            $entityManager->persist($value);
        } elseif ((int) $payload['version'] !== $value->getVersion()) {
            return new JsonResponse(['message' => 'This profile changed elsewhere. Reload before saving.'], Response::HTTP_CONFLICT);
        }
        $newValue = is_scalar($payload['value']) ? (string) $payload['value'] : null;
        if (($message = $valueValidator->validate($attribute, $newValue)) !== null) {
            return new JsonResponse(['message' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $value->setValue($newValue);
        try {
            $entityManager->flush();
        } catch (OptimisticLockException) {
            return new JsonResponse(['message' => 'This profile changed elsewhere. Reload before saving.'], Response::HTTP_CONFLICT);
        }
        if ($cv->getStatus()->value === 'published') {
            $indexer->refresh($cv);
        }

        return new JsonResponse(['version' => $value->getVersion(), 'saved' => true]);
    }

    #[Route('/{id}/publish', name: 'app_cv_publish', methods: ['POST'])]
    public function publish(CV $cv, Request $request, CvProjection $projection, CvSearchIndexer $indexer, EntityManagerInterface $entityManager): Response
    {
        $this->assertOwner($cv);
        if (!$this->isCsrfTokenValid('cv-publish-'.$cv->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid form token.');
        }
        $data = $projection->forCv($cv);
        if ($projection->hasMissingValues($data['attributes'])) {
            throw new ConflictHttpException('Fill every CV attribute before publishing.');
        }
        if ((int) $request->request->get('version') !== $cv->getVersion()) {
            throw new ConflictHttpException('This CV changed elsewhere. Reload before publishing.');
        }
        $cv->publish();
        try {
            $entityManager->flush();
        } catch (OptimisticLockException) {
            throw new ConflictHttpException('This CV changed elsewhere. Reload before publishing.');
        }
        $indexer->refresh($cv);

        return $this->redirectToRoute('app_cv_show', ['id' => $cv->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_cv_delete', methods: ['POST'])]
    public function delete(CV $cv, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->assertOwner($cv);
        if (!$this->isCsrfTokenValid('cv-delete-'.$cv->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid form token.');
        }
        $entityManager->remove($cv);
        $entityManager->flush();

        return $this->redirectToRoute('app_profile', ['tab' => 'cvs']);
    }

    private function candidateOwner(?Request $request = null, ?UserRepository $userRepository = null): User
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

    private function isOwner(CV $cv): bool
    {
        return $this->isGranted('ROLE_ADMIN') || ($this->getUser() === $cv->getCandidate() && $this->isGranted('ROLE_CANDIDATE'));
    }

    private function assertOwner(CV $cv): void
    {
        if (!$this->isOwner($cv)) {
            throw new AccessDeniedException('Only the candidate can edit this CV.');
        }
    }

    private function assertCanView(CV $cv): void
    {
        if ($this->isOwner($cv)) {
            return;
        }
        if (!$this->isGranted('ROLE_RECRUITER') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('This CV is not yours.');
        }
        if ($cv->getStatus()->value !== 'published') {
            throw $this->createNotFoundException('CV not found.');
        }
    }

    private function isRecruiter(): bool
    {
        return $this->isGranted('ROLE_RECRUITER') || $this->isGranted('ROLE_ADMIN');
    }
}