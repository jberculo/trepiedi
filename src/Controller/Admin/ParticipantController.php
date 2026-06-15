<?php

namespace App\Controller\Admin;

use App\Entity\Prediction;
use App\Entity\User;
use App\Form\UserAdminType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/deelnemers')]
#[IsGranted('ROLE_ADMIN')]
class ParticipantController extends AbstractController
{
    #[Route('', name: 'admin_participant_index', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/participant/index.html.twig', [
            'users' => $users->findBy([], ['displayName' => 'ASC']),
        ]);
    }

    #[Route('/{id}/bewerken', name: 'admin_participant_edit', methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request, EntityManagerInterface $em, UserRepository $users): Response
    {
        $form = $this->createForm(UserAdminType::class, $user);
        $form->get('isAdmin')->setData($user->isAdmin());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Beheerrechten op basis van de checkbox.
            $user->setRoles($form->get('isAdmin')->getData() ? ['ROLE_ADMIN'] : []);
            // Naam kan gewijzigd zijn: slug bijwerken zonder botsing met zichzelf.
            $user->setSlug($users->uniqueSlug($user->getDisplayName(), $user));
            $em->flush();
            $this->addFlash('success', 'admin.participant_updated');

            return $this->redirectToRoute('admin_participant_index');
        }

        return $this->render('admin/_crud_form.html.twig', [
            'form' => $form,
            'title' => 'admin.participant_edit',
            'back_path' => $this->generateUrl('admin_participant_index'),
        ]);
    }

    #[Route('/{id}/verwijderen', name: 'admin_participant_delete', methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('delete-participant-' . $user->getId(), (string) $request->getPayload()->get('_token'))) {
            return $this->redirectToRoute('admin_participant_index');
        }

        // Jezelf verwijderen kan niet.
        if ($this->getUser() instanceof User && $this->getUser()->getId() === $user->getId()) {
            $this->addFlash('error', 'admin.cannot_delete_self');

            return $this->redirectToRoute('admin_participant_index');
        }

        // Eerst de voorspellingen van deze speler weg (geen cascade op de FK).
        foreach ($em->getRepository(Prediction::class)->findBy(['user' => $user]) as $prediction) {
            $em->remove($prediction);
        }
        $em->remove($user);
        $em->flush();
        $this->addFlash('success', 'admin.participant_deleted');

        return $this->redirectToRoute('admin_participant_index');
    }
}
