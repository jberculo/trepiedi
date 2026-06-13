<?php

namespace App\Controller\Admin;

use App\Entity\FootballMatch;
use App\Form\FootballMatchType;
use App\Form\MatchResultType;
use App\Repository\FootballMatchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/wedstrijden')]
#[IsGranted('ROLE_ADMIN')]
class MatchController extends AbstractController
{
    #[Route('', name: 'admin_match_index', methods: ['GET'])]
    public function index(FootballMatchRepository $repository): Response
    {
        return $this->render('admin/match/index.html.twig', [
            'matches' => $repository->findAllForOverview(),
        ]);
    }

    #[Route('/bulk', name: 'admin_match_bulk', methods: ['POST'])]
    public function bulk(Request $request, FootballMatchRepository $repository, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        if (!$this->isCsrfTokenValid('bulk-matches', (string) $request->getPayload()->get('_token'))) {
            return $this->redirectToRoute('admin_match_index');
        }

        $ids = array_map('intval', (array) $request->getPayload()->all('ids'));
        $active = $request->getPayload()->get('active') === '1';

        if ($ids !== []) {
            $matches = $repository->findBy(['id' => $ids]);
            foreach ($matches as $match) {
                $match->setActive($active);
            }
            $em->flush();
            $this->addFlash('success', $translator->trans(
                $active ? 'admin.bulk_activated' : 'admin.bulk_deactivated',
                ['%count%' => count($matches)],
            ));
        }

        return $this->redirectToRoute('admin_match_index');
    }

    #[Route('/nieuw', name: 'admin_match_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $match = new FootballMatch();
        $form = $this->createForm(FootballMatchType::class, $match);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($match);
            $em->flush();
            $this->addFlash('success', 'admin.match_added');

            return $this->redirectToRoute('admin_match_index');
        }

        return $this->render('admin/_crud_form.html.twig', [
            'form' => $form,
            'title' => 'admin.match_new',
            'back_path' => $this->generateUrl('admin_match_index'),
        ]);
    }

    #[Route('/{id}/bewerken', name: 'admin_match_edit', methods: ['GET', 'POST'])]
    public function edit(FootballMatch $match, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(FootballMatchType::class, $match);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'admin.match_updated');

            return $this->redirectToRoute('admin_match_index');
        }

        return $this->render('admin/_crud_form.html.twig', [
            'form' => $form,
            'title' => 'admin.match_edit',
            'back_path' => $this->generateUrl('admin_match_index'),
        ]);
    }

    #[Route('/{id}/uitslag', name: 'admin_match_result', methods: ['GET', 'POST'])]
    public function result(FootballMatch $match, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        $form = $this->createForm(MatchResultType::class, $match, ['match' => $match]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($match->isFinished()
                && ($match->getHomeScore() === null || $match->getAwayScore() === null || $match->getAdvancingTeam() === null)) {
                $form->addError(new \Symfony\Component\Form\FormError(
                    $translator->trans('admin.result_incomplete')
                ));
            }

            if ($form->isValid()) {
                $em->flush();
                $this->addFlash('success', $translator->trans('admin.result_saved', ['%match%' => (string) $match]));

                return $this->redirectToRoute('admin_match_index');
            }
        }

        return $this->render('admin/match/result.html.twig', [
            'form' => $form,
            'match' => $match,
        ]);
    }

    #[Route('/{id}/verwijderen', name: 'admin_match_delete', methods: ['POST'])]
    public function delete(FootballMatch $match, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-match-' . $match->getId(), (string) $request->getPayload()->get('_token'))) {
            $em->remove($match);
            $em->flush();
            $this->addFlash('success', 'admin.match_deleted');
        }

        return $this->redirectToRoute('admin_match_index');
    }
}
