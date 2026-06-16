<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Pool\PoolEnroller;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use App\Security\LoginFormAuthenticator;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $authenticator,
        PoolEnroller $poolEnroller,
        \App\Security\ApiTokenGenerator $apiTokenGenerator,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        $session = $request->getSession();
        $code = $session->get(PoolEnroller::SESSION_KEY);
        $code = is_string($code) ? $code : null;

        if ($form->isSubmitted() && $form->isValid()) {
            // Foutieve/verlopen poule-link: niet registreren.
            if (!$poolEnroller->isValidCode($code)) {
                $session->remove(PoolEnroller::SESSION_KEY);
                $this->addFlash('error', 'pool.invalid_link');

                return $this->render('security/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            $user->setPassword(
                $passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData())
            );
            $user->setSlug($userRepository->uniqueSlug($user->getDisplayName()));
            $apiTokenGenerator->ensure($user);
            $userRepository->save($user, true);

            // Inschrijven op de poule van de (onthouden) uitnodigingscode, anders
            // de standaardpoule.
            $session->remove(PoolEnroller::SESSION_KEY);
            $poolEnroller->enroll($user, $code);

            return $userAuthenticator->authenticateUser($user, $authenticator, $request);
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
