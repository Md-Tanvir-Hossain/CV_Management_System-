<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OAuthController extends AbstractController
{
    #[Route('/connect/google', name: 'app_connect_google')]
    public function google(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry->getClient('google')->redirect(['email']);
    }

    #[Route('/connect/google/check', name: 'app_connect_google_check')]
    public function googleCheck(): Response
    {
        throw new \LogicException('The Google callback is handled by the OAuth authenticator.');
    }

    #[Route('/connect/facebook', name: 'app_connect_facebook')]
    public function facebook(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry->getClient('facebook')->redirect(['email']);
    }

    #[Route('/connect/facebook/check', name: 'app_connect_facebook_check')]
    public function facebookCheck(): Response
    {
        throw new \LogicException('The Facebook callback is handled by the OAuth authenticator.');
    }
}