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
    use AdminCrud;

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
        $form = $this->createForm(PoolType::class, new Pool());

        return $this->handleCrudForm($form, $request, $em, 'admin.pool_added', 'admin_pool_index', 'admin.pool_new', function (Pool $pool) use ($pools, $codeGenerator): void {
            // Code automatisch uit de naam genereren (code-veld is read-only).
            if ($pool->getCode() === '') {
                $pool->setCode($codeGenerator->generate($pool->getName()));
            }
            $this->ensureSingleDefault($pool, $pools);
        });
    }

    #[Route('/{id}/bewerken', name: 'admin_pool_edit', methods: ['GET', 'POST'])]
    public function edit(Pool $pool, Request $request, EntityManagerInterface $em, PoolRepository $pools): Response
    {
        $form = $this->createForm(PoolType::class, $pool);

        return $this->handleCrudForm($form, $request, $em, 'admin.pool_updated', 'admin_pool_index', 'admin.pool_edit', function (Pool $pool) use ($pools): void {
            $this->ensureSingleDefault($pool, $pools);
        });
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
        return $this->handlePostAction(
            $request,
            'remove-member-' . $pool->getId() . '-' . $userId,
            'admin_pool_members',
            function () use ($pool, $userId, $em): void {
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
            },
            ['id' => $pool->getId()],
        );
    }

    /**
     * Soft-delete: de poule wordt gearchiveerd (niet fysiek verwijderd), zodat
     * leden die zonder poule komen te zitten een melding krijgen.
     */
    #[Route('/{id}/archiveren', name: 'admin_pool_delete', methods: ['POST'])]
    public function archive(Pool $pool, Request $request, EntityManagerInterface $em): Response
    {
        return $this->handlePostAction($request, 'archive-pool-' . $pool->getId(), 'admin_pool_index', function () use ($pool, $em): void {
            if ($pool->isDefault()) {
                $this->addFlash('error', 'admin.cannot_delete_default');

                return;
            }

            $pool->archive();
            foreach ($pool->getMembers() as $member) {
                if ($member->getActivePool() !== null && $member->getActivePool()->getId() === $pool->getId()) {
                    $member->setActivePool(null);
                }
            }
            $em->flush();
            $this->addFlash('success', 'admin.pool_archived');
        });
    }

    #[Route('/{id}/herstellen', name: 'admin_pool_restore', methods: ['POST'])]
    public function restore(Pool $pool, Request $request, EntityManagerInterface $em): Response
    {
        return $this->handlePostAction($request, 'restore-pool-' . $pool->getId(), 'admin_pool_index', function () use ($pool, $em): void {
            $pool->restore();
            $em->flush();
            $this->addFlash('success', 'admin.pool_restored');
        });
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
