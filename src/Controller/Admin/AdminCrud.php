<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gedeelde CRUD-afhandeling voor de admin-controllers: het formulier verwerken
 * (opslaan + flash + redirect, anders het standaardformulier tonen) en het met
 * CSRF beveiligde verwijderen. Houdt de losse controllers vrij van copy-paste.
 *
 * Wordt alleen in AbstractController-subklassen gebruikt; de $this->-helpers
 * (render/addFlash/redirectToRoute/etc.) komen daarvandaan.
 */
trait AdminCrud
{
    /**
     * Voert een POST-actie uit na geldige CSRF-check en keert terug naar de lijst.
     *
     * @param callable(): void $action
     */
    private function handlePostAction(
        Request $request,
        string $tokenId,
        string $indexRoute,
        callable $action,
        array $routeParameters = [],
    ): Response {
        if ($this->isCsrfTokenValid($tokenId, (string) $request->getPayload()->get('_token'))) {
            $action();
        }

        return $this->redirectToRoute($indexRoute, $routeParameters);
    }

    /**
     * Verwerkt een new/edit-formulier. Bij een geldige inzending: optioneel
     * $onValid (extra logica voor opslaan), persist + flush, flash, terug naar
     * de lijst. Anders het standaard admin-formulier renderen.
     *
     * @param callable(mixed):void|null $onValid
     */
    private function handleCrudForm(
        FormInterface $form,
        Request $request,
        EntityManagerInterface $em,
        string $flashKey,
        string $indexRoute,
        string $title,
        ?callable $onValid = null,
        bool $includeCountryAutocomplete = false,
    ): Response {
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($onValid !== null) {
                $onValid($form->getData());
            }
            $em->persist($form->getData());
            $em->flush();
            $this->addFlash('success', $flashKey);

            return $this->redirectToRoute($indexRoute);
        }

        return $this->render('admin/_crud_form.html.twig', [
            'form' => $form,
            'title' => $title,
            'back_path' => $this->generateUrl($indexRoute),
            'include_country_autocomplete' => $includeCountryAutocomplete,
        ]);
    }

    /**
     * Verwijdert een entity na een geldige CSRF-token, met flash en redirect.
     *
     * @param callable(object): bool|void|null $beforeDelete Return false to abort deletion.
     */
    private function deleteWithCsrf(
        Request $request,
        EntityManagerInterface $em,
        string $tokenId,
        object $entity,
        string $flashKey,
        string $indexRoute,
        ?callable $beforeDelete = null,
    ): Response {
        return $this->handlePostAction($request, $tokenId, $indexRoute, function () use ($em, $entity, $flashKey, $beforeDelete): void {
            if ($beforeDelete !== null && $beforeDelete($entity) === false) {
                return;
            }
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', $flashKey);
        });
    }
}
