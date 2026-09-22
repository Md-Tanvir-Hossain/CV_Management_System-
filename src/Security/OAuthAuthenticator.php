<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class OAuthAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserChecker $userChecker,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return in_array($request->attributes->get('_route'), [
            'app_connect_google_check',
            'app_connect_facebook_check',
        ], true);
    }

    public function authenticate(Request $request): Passport
    {
        $provider = $this->providerForRoute((string) $request->attributes->get('_route'));
        $client = $this->clientRegistry->getClient($provider);
        $accessToken = $this->fetchAccessToken($client);
        $resourceOwner = $client->fetchUserFromToken($accessToken);
        $email = $this->emailFromResourceOwner($resourceOwner);

        return new SelfValidatingPassport(new UserBadge($email, function (string $email): User {
            $user = $this->userRepository->findOneBy(['email' => strtolower(trim($email))]);

            if (!$user) {
                $user = (new User())
                    ->setEmail($email)
                    ->setRoles(['ROLE_CANDIDATE'])
                    ->setPassword(null);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            }

            // This explicit check also covers the callback before a token is created.
            $this->userChecker->checkPreAuth($user);

            return $user;
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->saveAuthenticationErrorToSession($request, $exception);

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function providerForRoute(string $route): string
    {
        return $route === 'app_connect_google_check' ? 'google' : 'facebook';
    }

    private function emailFromResourceOwner(ResourceOwnerInterface $resourceOwner): string
    {
        $email = method_exists($resourceOwner, 'getEmail') ? $resourceOwner->getEmail() : null;

        if (!is_string($email) || trim($email) === '') {
            throw new AuthenticationException('The OAuth provider did not return an email address.');
        }

        return strtolower(trim($email));
    }
}