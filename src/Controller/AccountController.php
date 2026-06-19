<?php

namespace App\Controller;

use App\Account\AvatarStorage;
use App\Entity\User;
use App\Form\AccountType;
use App\Form\ChangePasswordType;
use App\Locale\LocaleManager;
use App\Repository\UserRepository;
use App\Security\ApiTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AccountController extends AbstractController
{
    #[Route('/account', name: 'app_account')]
    public function edit(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        AvatarStorage $avatars,
        LocaleManager $localeManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(AccountType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setSlug($users->uniqueSlug($user->getDisplayName(), $user));

            $file = $form->get('avatar')->getData();
            if ($file instanceof UploadedFile) {
                $avatars->store($user, $file, AvatarStorage::parseCrop($form->get('crop')->getData()));
            }

            $em->flush();
            $localeManager->applyUserLocale($request->getSession(), $user);
            $this->addFlash('success', 'account.saved');

            return $this->redirectToRoute('app_account');
        }

        $passwordForm = $this->createForm(ChangePasswordType::class);
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $newPassword = (string) $passwordForm->get('newPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $em->flush();
            $this->addFlash('success', 'account.password_changed');

            return $this->redirectToRoute('app_account');
        }

        $plainApiToken = $request->getSession()->remove('account.new_api_token');

        return $this->render('account/edit.html.twig', [
            'form' => $form,
            'passwordForm' => $passwordForm,
            'plainApiToken' => is_string($plainApiToken) ? $plainApiToken : null,
        ]);
    }

    #[Route('/account/api-sleutel', name: 'app_account_api_token', methods: ['POST'])]
    public function regenerateApiToken(
        Request $request,
        EntityManagerInterface $em,
        ApiTokenService $apiTokens,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isCsrfTokenValid('api-token-' . $user->getId(), (string) $request->getPayload()->get('_token'))) {
            $token = $apiTokens->issue($user);
            $em->flush();
            $request->getSession()->set('account.new_api_token', $token);
            $this->addFlash('success', 'account.api_token_regenerated');
        }

        return $this->redirectToRoute('app_account');
    }
}
