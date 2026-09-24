<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AdminUserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/users')]
final class AdminUserController extends AbstractController
{
    private const MANAGED_ROLES = ['ROLE_CANDIDATE', 'ROLE_RECRUITER', 'ROLE_ADMIN'];

    #[Route('', name: 'app_admin_user_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(UserRepository $userRepository): Response
    {
        $this->requireAdmin();

        return $this->render('admin/user_index.html.twig', ['users' => $userRepository->findForAdmin()]);
    }

    #[Route('/new', name: 'app_admin_user_new', methods: ['GET', 'POST'], priority: 10)]
    #[IsGranted('ROLE_ADMIN')]
    public function new(
        FormFactoryInterface $formFactory,
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = new User();
        $form = $formFactory->create(AdminUserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($userRepository->findOneBy(['email' => $user->getEmail()]) instanceof User) {
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('An account with this email already exists.'));
            } else {
                $user->setRoles(['ROLE_ADMIN']);
                $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('password')->getData()));
                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Admin account created.');

                if ($this->isGranted('ROLE_ADMIN')) {
                    return $this->redirectToRoute('app_admin_user_index');
                }

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('admin/user_form.html.twig', ['admin_user_form' => $form]);
    }

    #[Route('/{id}/toggle-blocked', name: 'app_admin_user_toggle_blocked', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleBlocked(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->requireAdmin();
        if (!$this->isCsrfTokenValid('admin-user-block-'.$user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid form token.');
        }
        $user->setBlocked(!$user->isBlocked());
        $entityManager->flush();

        return $this->redirectToRoute('app_admin_user_index');
    }

    #[Route('/{id}/roles', name: 'app_admin_user_roles', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateRoles(User $user, Request $request, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage): Response
    {
        $this->requireAdmin();
        $role = (string) $request->request->get('role');
        $action = (string) $request->request->get('role_action');
        if (!$this->isCsrfTokenValid('admin-user-roles-'.$user->getId(), $request->request->get('_token')) || !in_array($role, self::MANAGED_ROLES, true) || !in_array($action, ['add', 'remove'], true)) {
            throw $this->createAccessDeniedException('Invalid role change.');
        }
        $roles = array_values(array_filter($user->getRoles(), static fn (string $item): bool => $item !== 'ROLE_USER'));
        if ($action === 'add') {
            $roles[] = $role;
        } else {
            $roles = array_values(array_filter($roles, static fn (string $item): bool => $item !== $role));
        }
        $user->setRoles(array_values(array_unique($roles)));
        $entityManager->flush();

        if ($user === $this->getUser() && $role === 'ROLE_ADMIN' && $action === 'remove') {
            $tokenStorage->setToken(null);
            return $this->redirectToRoute('app_login');
        }

        return $this->redirectToRoute('app_admin_user_index');
    }

    #[Route('/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(User $user, Request $request, EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage): Response
    {
        $this->requireAdmin();
        if (!$this->isCsrfTokenValid('admin-user-delete-'.$user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid form token.');
        }
        $isCurrentUser = $user === $this->getUser();
        $entityManager->remove($user);
        $entityManager->flush();
        if ($isCurrentUser) {
            $tokenStorage->setToken(null);
            return $this->redirectToRoute('app_login');
        }

        return $this->redirectToRoute('app_admin_user_index');
    }

    private function requireAdmin(): void
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException('Administrator access is required.');
        }
    }
}