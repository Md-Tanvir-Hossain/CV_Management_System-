<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;

final class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $user = $this->security->getUser();
        $locale = $request->getSession()->get('_locale');
        if ($user instanceof User) {
            $locale = $user->getLocale();
            $request->getSession()->set('theme', $user->getTheme());
        }
        $request->setLocale(is_string($locale) && in_array($locale, ['en', 'fr'], true) ? $locale : 'en');
    }
}