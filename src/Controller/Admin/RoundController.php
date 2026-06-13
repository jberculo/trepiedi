<?php

namespace App\Controller\Admin;

use App\Entity\Round;
use App\Form\RoundType;
use App\Repository\RoundRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ronden')]
#[IsGranted('ROLE_ADMIN')]
class RoundController extends AbstractController
{
    #[Route('', name: 'admin_round_index', methods: ['GET'])]
    public function index(RoundRepository $repository): Response
    {
        return $this->render('admin/round/index.html.twig', [
            'rounds' => $repository->findBy([], ['sortOrder' => 'ASC']),
        ]);
    }

    #[Route('/nieuw', name: 'admin_round_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $round = new Round();
        $form = $this->createForm(RoundType::class, $round);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($round);
            $em->flush();
            $this->addFlash('success', 'admin.round_added');

            return $this->redirectToRoute('admin_round_index');
        }

        return $this->render('admin/_crud_form.html.twig', [
            'form' => $form,
            'title' => 'admin.round_new',
            'back_path' => $this->generateUrl('admin_round_index'),
        ]);
    }

    #[Route('/{id}/bewerken', name: 'admin_round_edit', methods: ['GET', 'POST'])]
    public function edit(Round $round, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RoundType::class, $round);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'admin.round_updated');

            return $this->redirectToRoute('admin_round_index');
        }

        return $this->render('admin/_crud_form.html.twig', [
            'form' => $form,
            'title' => 'admin.round_edit',
            'back_path' => $this->generateUrl('admin_round_index'),
        ]);
    }

    #[Route('/{id}/verwijderen', name: 'admin_round_delete', methods: ['POST'])]
    public function delete(Round $round, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-round-' . $round->getId(), (string) $request->getPayload()->get('_token'))) {
            $em->remove($round);
            $em->flush();
            $this->addFlash('success', 'admin.round_deleted');
        }

        return $this->redirectToRoute('admin_round_index');
    }
}
