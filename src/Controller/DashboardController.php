<?php

namespace App\Controller;

use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Entity\User;
use App\Form\PredictionType;
use App\Repository\PredictionRepository;
use App\Repository\RoundRepository;
use App\Scoring\ScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class DashboardController extends AbstractController
{
    #[Route('/voorspellen', name: 'app_dashboard')]
    public function index(
        RoundRepository $roundRepository,
        PredictionRepository $predictionRepository,
        ScoringService $scoringService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $predictions = $predictionRepository->findByUserIndexedByMatch($user);

        $rounds = [];
        foreach ($roundRepository->findAllOrdered() as $round) {
            $matches = [];
            foreach ($round->getMatches() as $match) {
                $prediction = $predictions[$match->getId()] ?? null;
                $matches[] = [
                    'match' => $match,
                    'prediction' => $prediction,
                    'locked' => $match->isLocked(),
                    'score' => $prediction !== null ? $scoringService->scorePrediction($prediction) : null,
                    'form' => ($match->isLocked() || !$match->isActive())
                        ? null
                        : $this->buildForm($match, $prediction)->createView(),
                ];
            }
            $rounds[] = ['round' => $round, 'matches' => $matches];
        }

        return $this->render('dashboard/index.html.twig', [
            'rounds' => $rounds,
        ]);
    }

    #[Route('/voorspelling/{id}/opslaan', name: 'app_prediction_save', methods: ['POST'])]
    public function save(
        FootballMatch $match,
        Request $request,
        PredictionRepository $predictionRepository,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $ajax = $request->isXmlHttpRequest();

        if (!$match->isActive()) {
            return $this->saveError($ajax, $match, 'dash.not_available_flash', $translator);
        }

        if ($match->isLocked()) {
            return $this->saveError($ajax, $match, 'dash.locked_flash', $translator);
        }

        $prediction = $predictionRepository->findOneForUserAndMatch($user, $match) ?? new Prediction();
        $form = $this->buildForm($match, $prediction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $prediction->setUser($user);
            $prediction->setFootballMatch($match);
            $prediction->setUpdatedAt(new \DateTimeImmutable());
            $em->persist($prediction);
            $em->flush();

            if ($ajax) {
                return new JsonResponse([
                    'ok' => true,
                    'message' => $translator->trans('common.saved'),
                    'updatedAt' => $prediction->getUpdatedAt()->format('d-m H:i'),
                ]);
            }

            $this->addFlash('success', $translator->trans('dash.saved_flash', ['%match%' => (string) $match]));

            return $this->redirectToRoute('app_dashboard', ['_fragment' => 'match-' . $match->getId()]);
        }

        $messages = [];
        foreach ($form->getErrors(true) as $error) {
            $messages[] = $error->getMessage();
        }

        if ($ajax) {
            return new JsonResponse([
                'ok' => false,
                'message' => $messages !== [] ? implode(' ', $messages) : $translator->trans('dash.not_available_flash'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($messages as $message) {
            $this->addFlash('error', $message);
        }

        return $this->redirectToRoute('app_dashboard', ['_fragment' => 'match-' . $match->getId()]);
    }

    /**
     * Foutmelding voor een geblokkeerde/inactieve wedstrijd: JSON bij AJAX, anders flash + redirect.
     */
    private function saveError(bool $ajax, FootballMatch $match, string $key, TranslatorInterface $translator): Response
    {
        if ($ajax) {
            return new JsonResponse(
                ['ok' => false, 'message' => $translator->trans($key)],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->addFlash('error', $key);

        return $this->redirectToRoute('app_dashboard', ['_fragment' => 'match-' . $match->getId()]);
    }

    private function buildForm(FootballMatch $match, ?Prediction $prediction): FormInterface
    {
        return $this->createForm(PredictionType::class, $prediction ?? new Prediction(), [
            'match' => $match,
            'action' => $this->generateUrl('app_prediction_save', ['id' => $match->getId()]),
        ]);
    }
}
