<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Bepaalt de taal van het verzoek op basis van de in de sessie opgeslagen keuze.
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire('%kernel.default_locale%')]
        private string $defaultLocale = 'nl',
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->hasPreviousSession()) {
            return;
        }

        $locale = $request->getSession()->get('_locale');
        $request->setLocale(\is_string($locale) && $locale !== '' ? $locale : $this->defaultLocale);
    }

    public static function getSubscribedEvents(): array
    {
        // Voor de standaard LocaleListener van Symfony (priority 16).
        return [KernelEvents::REQUEST => [['onKernelRequest', 20]]];
    }
}
