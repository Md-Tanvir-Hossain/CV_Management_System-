<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PreferencesController extends AbstractController
{
    #[Route('/preferences', name: 'app_preferences', methods: ['POST'])]
    public function update(Request $request, EntityManagerInterface $entityManager): Response
    {
        $locale = (string) $request->request->get('locale', 'en');
        $theme = (string) $request->request->get('theme', 'light');
        if (!in_array($locale, ['en', 'fr'], true) || !in_array($theme, ['light', 'dark'], true)) {
            throw $this->createAccessDeniedException('Invalid preference.');
        }

        $request->getSession()->set('_locale', $locale);
        $request->getSession()->set('theme', $theme);
        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setLocale($locale)->setTheme($theme);
            $entityManager->flush();
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_home'));
    }
}