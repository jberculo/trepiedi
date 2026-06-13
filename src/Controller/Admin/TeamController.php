<?php

namespace App\Controller\Admin;

use App\Entity\Team;
use App\Form\TeamType;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/teams')]
#[IsGranted('ROLE_ADMIN')]
class TeamController extends AbstractController
{
    #[Route('', name: 'admin_team_index', methods: ['GET'])]
    public function index(TeamRepository $repository): Response
    {
        return $this->render('admin/team/index.html.twig', [
            'teams' => $repository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/nieuw', name: 'admin_team_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $team = new Team();
        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($team);
            $em->flush();
            $this->addFlash('success', 'admin.team_added');

            return $this->redirectToRoute('admin_team_index');
        }

        return $this->render('admin/_crud_form.html.twig', [
            'form' => $form,
            'title' => 'admin.team_new',
            'back_path' => $this->generateUrl('admin_team_index'),
        ]);
    }

    #[Route('/{id}/bewerken', name: 'admin_team_edit', methods: ['GET', 'POST'])]
    public function edit(Team $team, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TeamType::class, $team);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'admin.team_updated');

            return $this->redirectToRoute('admin_team_index');
        }

        return $this->render('admin/_crud_form.html.twig', [
            'form' => $form,
            'title' => 'admin.team_edit',
            'back_path' => $this->generateUrl('admin_team_index'),
        ]);
    }

    #[Route('/{id}/verwijderen', name: 'admin_team_delete', methods: ['POST'])]
    public function delete(Team $team, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-team-' . $team->getId(), (string) $request->getPayload()->get('_token'))) {
            $em->remove($team);
            $em->flush();
            $this->addFlash('success', 'admin.team_deleted');
        }

        return $this->redirectToRoute('admin_team_index');
    }
}
