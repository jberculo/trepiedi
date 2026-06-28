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
    use AdminCrud;

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
        return $this->handlePostAction($request, 'bulk-matches', 'admin_match_index', function () use ($request, $repository, $em, $translator): void {
            $ids = array_map('intval', (array) $request->getPayload()->all('ids'));
            $active = $request->getPayload()->get('active') === '1';

            if ($ids === []) {
                return;
            }

            $matches = $repository->findBy(['id' => $ids]);
            foreach ($matches as $match) {
                $match->setActive($active);
            }
            $em->flush();
            $this->addFlash('success', $translator->trans(
                $active ? 'admin.bulk_activated' : 'admin.bulk_deactivated',
                ['%count%' => count($matches)],
            ));
        });
    }

    #[Route('/nieuw', name: 'admin_match_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(FootballMatchType::class, new FootballMatch());

        return $this->handleCrudForm($form, $request, $em, 'admin.match_added', 'admin_match_index', 'admin.match_new', null, true);
    }

    #[Route('/{id}/bewerken', name: 'admin_match_edit', methods: ['GET', 'POST'])]
    public function edit(FootballMatch $match, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(FootballMatchType::class, $match);

        return $this->handleCrudForm($form, $request, $em, 'admin.match_updated', 'admin_match_index', 'admin.match_edit', null, true);
    }

    #[Route('/{id}/uitslag', name: 'admin_match_result', methods: ['GET', 'POST'])]
    public function result(FootballMatch $match, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): Response
    {
        $resultBefore = [$match->getHomeScore(), $match->getAwayScore(), $match->getAdvancingSide(), $match->isFinished()];

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
                // Een tegenstrijdige uitslag (score-winnaar ≠ doorgaande ploeg) wordt
                // pas opgeslagen nadat de beheerder die expliciet bevestigt.
                $confirmed = $request->getPayload()->getBoolean('confirm_inconsistent');
                if ($match->hasInconsistentResult() && !$confirmed) {
                    return $this->render('admin/match/result.html.twig', [
                        'form' => $form,
                        'match' => $match,
                        'needsConfirmation' => true,
                    ]);
                }

                // Een handmatige backend-wijziging van de uitslag haalt de API/MCP-vlag weg.
                $resultAfter = [$match->getHomeScore(), $match->getAwayScore(), $match->getAdvancingSide(), $match->isFinished()];
                if ($resultAfter !== $resultBefore) {
                    $match->setResultViaExternalApi(false);
                }

                $em->flush();
                $this->addFlash('success', $translator->trans('admin.result_saved', ['%match%' => (string) $match]));

                return $this->redirectToRoute('admin_match_index');
            }
        }

        return $this->render('admin/match/result.html.twig', [
            'form' => $form,
            'match' => $match,
            'needsConfirmation' => false,
        ]);
    }

    #[Route('/{id}/verwijderen', name: 'admin_match_delete', methods: ['POST'])]
    public function delete(FootballMatch $match, Request $request, EntityManagerInterface $em): Response
    {
        return $this->deleteWithCsrf($request, $em, 'delete-match-' . $match->getId(), $match, 'admin.match_deleted', 'admin_match_index');
    }
}
