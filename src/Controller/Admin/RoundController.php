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
    use AdminCrud;

    #[Route('', name: 'admin_round_index', methods: ['GET'])]
    public function index(RoundRepository $repository): Response
    {
        return $this->render('admin/round/index.html.twig', [
            'rounds' => $repository->findAllBySortOrder(),
        ]);
    }

    #[Route('/nieuw', name: 'admin_round_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RoundType::class, new Round());

        return $this->handleCrudForm($form, $request, $em, 'admin.round_added', 'admin_round_index', 'admin.round_new');
    }

    #[Route('/{id}/bewerken', name: 'admin_round_edit', methods: ['GET', 'POST'])]
    public function edit(Round $round, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RoundType::class, $round);

        return $this->handleCrudForm($form, $request, $em, 'admin.round_updated', 'admin_round_index', 'admin.round_edit');
    }

    #[Route('/{id}/verwijderen', name: 'admin_round_delete', methods: ['POST'])]
    public function delete(Round $round, Request $request, EntityManagerInterface $em): Response
    {
        return $this->deleteWithCsrf($request, $em, 'delete-round-' . $round->getId(), $round, 'admin.round_deleted', 'admin_round_index');
    }
}
