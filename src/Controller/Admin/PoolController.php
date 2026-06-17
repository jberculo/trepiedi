<?php

namespace App\Controller\Admin;

use App\Entity\Pool;
use App\Entity\User;
use App\Form\PoolType;
use App\Pool\PoolCodeGenerator;
use App\Repository\PoolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/poules')]
#[IsGranted('ROLE_ADMIN')]
class PoolController extends AbstractController
{
    #[Route('', name: 'admin_pool_index', methods: ['GET'])]
    public function index(PoolRepository $repository): Response
    {
        return $this->render('admin/pool/index.html.twig', [
            'pools' => $repository->findAllForAdmin(),
        ]);
    }

    #[Route('/nieuw', name: 'admin_pool_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, PoolRepository $pools, PoolCodeGenerator $codeGenerator): Response
    {
        $pool = new Pool();
        $form = $this->createForm(PoolType::class, $pool);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Code automatisch uit de naam genereren (code-veld is read-only).
            if ($pool->getCode() === '') {
                $pool->setCode($codeGenerator->generate($pool->getName()));
            }
            $this->ensureSingleDefault($pool, $pools);
            $em->persist($pool);
            $em->flush();
            $this->addFlash('success', 'admin.pool_added');

            return $this->redirectToRoute('admin_pool_index');
        }

        return $this->render('admin/_crud_form.html.twig', [
            'form' => $form,
            'title' => 'admin.pool_new',
            'back_path' => $this->generateUrl('admin_pool_index'),
        ]);
    }

    #[Route('/{id}/bewerken', name: 'admin_pool_edit', methods: ['GET', 'POST'])]
    public function edit(Pool $pool, Request $request, EntityManagerInterface $em, PoolRepository $pools): Response
    {
        $form = $this->createForm(PoolType::class, $pool);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->ensureSingleDefault($pool, $pools);
            $em->flush();
            $this->addFlash('success', 'admin.pool_updated');

            return $this->redirectToRoute('admin_pool_index');
        }

        return $this->render('admin/_crud_form.html.twig', [
            'form' => $form,
            'title' => 'admin.pool_edit',
            'back_path' => $this->generateUrl('admin_pool_index'),
        ]);
    }

    #[Route('/{id}/leden', name: 'admin_pool_members', methods: ['GET'])]
    public function members(Pool $pool): Response
    {
        return $this->render('admin/pool/members.html.twig', [
            'pool' => $pool,
        ]);
    }

    #[Route('/{id}/leden/{userId}/verwijderen', name: 'admin_pool_member_remove', methods: ['POST'])]
    public function removeMember(Pool $pool, int $userId, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('remove-member-' . $pool->getId() . '-' . $userId, (string) $request->getPayload()->get('_token'))) {
            $user = $em->getRepository(User::class)->find($userId);
            if ($user !== null && $user->isInPool($pool)) {
                $user->removePool($pool);
                // Was dit de actieve poule? Dan terugvallen op een andere keuze.
                if ($user->getActivePool() !== null && $user->getActivePool()->getId() === $pool->getId()) {
                    $user->setActivePool(null);
                }
                $em->flush();
                $this->addFlash('success', 'admin.member_removed');
            }
        }

        return $this->redirectToRoute('admin_pool_members', ['id' => $pool->getId()]);
    }

    /**
     * Soft-delete: de poule wordt gearchiveerd (niet fysiek verwijderd), zodat
     * leden die zonder poule komen te zitten een melding krijgen.
     */
    #[Route('/{id}/archiveren', name: 'admin_pool_delete', methods: ['POST'])]
    public function archive(Pool $pool, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('archive-pool-' . $pool->getId(), (string) $request->getPayload()->get('_token'))) {
            return $this->redirectToRoute('admin_pool_index');
        }

        // De standaardpoule mag niet weg: nieuwe spelers moeten ergens terechtkomen.
        if ($pool->isDefault()) {
            $this->addFlash('error', 'admin.cannot_delete_default');

            return $this->redirectToRoute('admin_pool_index');
        }

        $pool->archive();
        // Wie deze poule als actief had, valt terug (PoolContext kiest opnieuw).
        foreach ($pool->getMembers() as $member) {
            if ($member->getActivePool() !== null && $member->getActivePool()->getId() === $pool->getId()) {
                $member->setActivePool(null);
            }
        }
        $em->flush();
        $this->addFlash('success', 'admin.pool_archived');

        return $this->redirectToRoute('admin_pool_index');
    }

    #[Route('/{id}/herstellen', name: 'admin_pool_restore', methods: ['POST'])]
    public function restore(Pool $pool, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('restore-pool-' . $pool->getId(), (string) $request->getPayload()->get('_token'))) {
            $pool->restore();
            $em->flush();
            $this->addFlash('success', 'admin.pool_restored');
        }

        return $this->redirectToRoute('admin_pool_index');
    }

    /**
     * Houd hooguit één standaardpoule aan: als deze poule de standaard wordt,
     * zet het bij de andere uit.
     */
    private function ensureSingleDefault(Pool $pool, PoolRepository $pools): void
    {
        if (!$pool->isDefault()) {
            return;
        }

        foreach ($pools->findBy(['isDefault' => true]) as $other) {
            if ($other !== $pool) {
                $other->setDefault(false);
            }
        }
    }
}
