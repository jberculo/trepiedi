<?php

namespace App\Controller;

use App\Reference\Countries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Autocomplete-bron voor landnamen bij het invoeren van wedstrijden (alleen beheer).
 */
#[IsGranted('ROLE_ADMIN')]
class CountryApiController extends AbstractController
{
    #[Route('/api/landen', name: 'api_countries', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query->get('q', '');

        return $this->json(Countries::search($query, $request->getLocale()));
    }
}
