<?php

namespace App\Controller;

use App\Entity\Attribute;
use App\Enum\AttributeCategory;
use App\Form\AttributeType;
use App\Repository\AttributeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/attributes')]
final class AttributeController extends AbstractController
{
    #[Route('', name: 'app_attribute_index', methods: ['GET'])]
    public function index(Request $request, AttributeRepository $repository): Response
    {
        $this->requireRecruiter();
        $category = null;
        $categoryValue = $request->query->get('category');
        if (is_string($categoryValue) && $categoryValue !== '') {
            $category = AttributeCategory::tryFrom($categoryValue);
        }

        return $this->render('attribute/index.html.twig', [
            'attributes' => $repository->findForLibrary($request->query->get('q'), $category),
            'categories' => AttributeCategory::cases(),
            'query' => $request->query->get('q', ''),
            'selectedCategory' => $category,
        ]);
    }

    #[Route('/new', name: 'app_attribute_new', methods: ['GET', 'POST'])]
    public function new(Request $request, FormFactoryInterface $formFactory, EntityManagerInterface $entityManager): Response
    {
        $this->requireRecruiter();
        $attribute = new Attribute();
        $form = $formFactory->create(AttributeType::class, $attribute);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyOptions($attribute, $form->get('optionsText')->getData());
            $entityManager->persist($attribute);
            $entityManager->flush();
            $this->addFlash('success', 'Attribute created.');

            return $this->redirectToRoute('app_attribute_index');
        }

        return $this->render('attribute/form.html.twig', ['form' => $form, 'heading' => 'Create attribute']);
    }

    #[Route('/{id}/edit', name: 'app_attribute_edit', methods: ['GET', 'POST'])]
    public function edit(Attribute $attribute, Request $request, FormFactoryInterface $formFactory, EntityManagerInterface $entityManager): Response
    {
        $this->requireRecruiter();
        $form = $formFactory->create(AttributeType::class, $attribute, ['action' => $this->generateUrl('app_attribute_edit', ['id' => $attribute->getId()])]);
        $form->get('optionsText')->setData(implode("\n", $attribute->getOptions()));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->applyOptions($attribute, $form->get('optionsText')->getData());
            $attribute->touch();
            $entityManager->flush();
            $this->addFlash('success', 'Attribute updated.');

            return $this->redirectToRoute('app_attribute_index');
        }

        return $this->render('attribute/form.html.twig', ['form' => $form, 'heading' => 'Edit attribute']);
    }

    #[Route('/{id}/delete', name: 'app_attribute_delete', methods: ['POST'])]
    public function delete(Attribute $attribute, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->requireRecruiter();
        if ($attribute->isBuiltin()) {
            $this->addFlash('warning', 'Built-in attributes cannot be deleted.');

            return $this->redirectToRoute('app_attribute_index');
        }

        if ($this->isCsrfTokenValid('delete-attribute-'.$attribute->getId(), $request->request->get('_token'))) {
            $entityManager->remove($attribute);
            $entityManager->flush();
            $this->addFlash('success', 'Attribute deleted.');
        }

        return $this->redirectToRoute('app_attribute_index');
    }

    private function requireRecruiter(): void
    {
        if (!$this->isGranted('ROLE_RECRUITER') && !$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Recruiter access is required.');
        }
    }

    private function applyOptions(Attribute $attribute, mixed $options): void
    {
        $values = is_string($options) ? preg_split('/\R/', $options) : [];
        $attribute->setOptions(array_values(array_filter(array_map('trim', $values ?: []))));
    }
}