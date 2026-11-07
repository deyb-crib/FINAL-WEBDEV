<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\AdminActivityLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class ActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly AdminActivityLogger $activityLogger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User) {
            $this->activityLogger->record($user, 'auth.login', 'user', (string) $user->getId(), 'Signed in');
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        if ($user instanceof User) {
            $this->activityLogger->record($user, 'auth.logout', 'user', (string) $user->getId(), 'Signed out');
        }
    }
}
