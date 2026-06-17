<?php

namespace App\Controller;

use App\Dashboard\DashboardViewBuilder;
use App\Entity\FootballMatch;
use App\Entity\Prediction;
use App\Entity\User;
use App\Form\PredictionFormFactory;
use App\Repository\PredictionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class DashboardController extends AbstractController
{
    #[Route('/voorspellen', name: 'app_dashboard')]
    public function index(DashboardViewBuilder $dashboard): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('dashboard/index.html.twig', [
            'rounds' => $dashboard->buildForUser($user),
        ]);
    }

    #[Route('/voorspelling/{id}/opslaan', name: 'app_prediction_save', methods: ['POST'])]
    public function save(
        FootballMatch $match,
        Request $request,
        PredictionRepository $predictionRepository,
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        PredictionFormFactory $predictionForms,
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
        $form = $predictionForms->create($match, $prediction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $prediction->setUser($user);
            $prediction->setFootballMatch($match);
            $prediction->setUpdatedAt(new \DateTimeImmutable());
            $em->persist($prediction);
            $em->flush();

            if ($ajax) {
                return $this->jsonStatus([
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
            return $this->jsonStatus([
                'ok' => false,
                'message' => $messages !== [] ? implode(' ', $messages) : $translator->trans('dash.not_available_flash'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($messages as $message) {
            $this->addFlash('error', $message);
        }

        return $this->dashboardRedirect($match);
    }

    /**
     * Foutmelding voor een geblokkeerde/inactieve wedstrijd: JSON bij AJAX, anders flash + redirect.
     */
    private function saveError(bool $ajax, FootballMatch $match, string $key, TranslatorInterface $translator): Response
    {
        if ($ajax) {
            return $this->jsonStatus(
                ['ok' => false, 'message' => $translator->trans($key)],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->addFlash('error', $key);

        return $this->dashboardRedirect($match);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonStatus(array $payload, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse($payload, $status);
    }

    private function dashboardRedirect(FootballMatch $match): Response
    {
        return $this->redirectToRoute('app_dashboard', ['_fragment' => 'match-' . $match->getId()]);
    }
}
