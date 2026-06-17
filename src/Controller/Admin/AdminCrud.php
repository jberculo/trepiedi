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
 * (render/addFlash/redirectToRoute/…) komen daarvandaan.
 */
trait AdminCrud
{
    /**
     * Verwerkt een new/edit-formulier. Bij een geldige inzending: optioneel
     * $onValid (extra logica vóór opslaan), persist + flush, flash, terug naar
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
        ]);
    }

    /**
     * Verwijdert een entity na een geldige CSRF-token, met flash en redirect.
     */
    private function deleteWithCsrf(
        Request $request,
        EntityManagerInterface $em,
        string $tokenId,
        object $entity,
        string $flashKey,
        string $indexRoute,
    ): Response {
        if ($this->isCsrfTokenValid($tokenId, (string) $request->getPayload()->get('_token'))) {
            $em->remove($entity);
            $em->flush();
            $this->addFlash('success', $flashKey);
        }

        return $this->redirectToRoute($indexRoute);
    }
}
