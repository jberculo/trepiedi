<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountType;
use App\Repository\UserRepository;
use App\Security\ApiTokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account')]
    public function edit(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        ApiTokenGenerator $apiTokenGenerator,
        #[Autowire('%kernel.project_dir%/public/uploads/avatars')]
        string $avatarDir,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Zorg dat de speler een persoonlijke API-sleutel heeft (lazy aanmaken).
        if ($apiTokenGenerator->ensure($user)) {
            $em->flush();
        }

        $form = $this->createForm(AccountType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Naam kan gewijzigd zijn: slug bijwerken (zonder botsing met zichzelf).
            $user->setSlug($users->uniqueSlug($user->getDisplayName(), $user));

            $file = $form->get('avatar')->getData();
            if ($file instanceof UploadedFile) {
                $filename = $user->getSlug() . '-' . uniqid() . '.' . ($file->guessExtension() ?: 'bin');
                $file->move($avatarDir, $filename);

                $old = $user->getAvatar();
                if ($old !== null && is_file($avatarDir . '/' . $old)) {
                    @unlink($avatarDir . '/' . $old);
                }
                $user->setAvatar($filename);
            }

            $em->flush();

            // Taalkeuze meteen toepassen.
            $request->getSession()->set('_locale', $user->getLocale());

            $this->addFlash('success', 'account.saved');

            return $this->redirectToRoute('app_account');
        }

        return $this->render('account/edit.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/account/api-sleutel', name: 'app_account_api_token', methods: ['POST'])]
    public function regenerateApiToken(
        Request $request,
        EntityManagerInterface $em,
        ApiTokenGenerator $apiTokenGenerator,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isCsrfTokenValid('api-token-' . $user->getId(), (string) $request->getPayload()->get('_token'))) {
            $user->setApiToken($apiTokenGenerator->generate());
            $em->flush();
            $this->addFlash('success', 'account.api_token_regenerated');
        }

        return $this->redirectToRoute('app_account');
    }
}
