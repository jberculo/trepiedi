<?php

namespace App\Controller\Admin;

use App\Account\AvatarStorage;
use App\Entity\Prediction;
use App\Entity\User;
use App\Form\UserAdminType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/deelnemers')]
#[IsGranted('ROLE_ADMIN')]
class ParticipantController extends AbstractController
{
    use AdminCrud;

    #[Route('', name: 'admin_participant_index', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/participant/index.html.twig', [
            'users' => $users->findBy([], ['displayName' => 'ASC']),
        ]);
    }

    #[Route('/{id}/bewerken', name: 'admin_participant_edit', methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request, EntityManagerInterface $em, UserRepository $users, AvatarStorage $avatars, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(UserAdminType::class, $user, [
            'allow_password_reset' => !$user->isAdmin(),
        ]);
        // Checkbox vooraf vullen (moet voor handleRequest gebeuren).
        $form->get('isAdmin')->setData($user->isAdmin());

        return $this->handleCrudForm($form, $request, $em, 'admin.participant_updated', 'admin_participant_index', 'admin.participant_edit', onValid: function (User $user) use ($form, $users, $avatars, $passwordHasher): void {
            $user->setRoles($form->get('isAdmin')->getData() ? ['ROLE_ADMIN'] : []);
            $user->setSlug($users->uniqueSlug($user->getDisplayName(), $user));

            $avatar = $form->get('avatar')->getData();
            if ($avatar instanceof UploadedFile) {
                $avatars->store($user, $avatar, AvatarStorage::parseCrop($form->get('crop')->getData()));
            }

            if ($form->has('newPassword')) {
                $newPassword = $form->get('newPassword')->getData();
                if (is_string($newPassword) && $newPassword !== '') {
                    $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                }
            }
        }, avatarPreview: $user);
    }

    #[Route('/{id}/verwijderen', name: 'admin_participant_delete', methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        return $this->deleteWithCsrf(
            $request,
            $em,
            'delete-participant-' . $user->getId(),
            $user,
            'admin.participant_deleted',
            'admin_participant_index',
            function (User $user) use ($em): bool {
                if ($this->getUser() instanceof User && $this->getUser()->getId() === $user->getId()) {
                    $this->addFlash('error', 'admin.cannot_delete_self');

                    return false;
                }

                foreach ($em->getRepository(Prediction::class)->findBy(['user' => $user]) as $prediction) {
                    $em->remove($prediction);
                }

                return true;
            },
        );
    }
}
