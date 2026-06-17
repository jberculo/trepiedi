<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Pool\PoolJoinManager;
use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        UserRepository $userRepository,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $authenticator,
        PoolJoinManager $poolJoins,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        $session = $request->getSession();
        $code = $poolJoins->pendingCode($session);

        if ($form->isSubmitted() && $form->isValid()) {
            // Foutieve/verlopen poule-link: niet registreren.
            if (!$poolJoins->isValidCode($code)) {
                $poolJoins->forgetPendingCode($session);
                $this->addFlash('error', 'pool.invalid_link');

                return $this->render('security/register.html.twig', [
                    'registrationForm' => $form,
                ]);
            }

            $user->setPassword(
                $passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData())
            );
            $user->setSlug($userRepository->uniqueSlug($user->getDisplayName()));
            $userRepository->save($user, true);

            // Inschrijven op de poule van de (onthouden) uitnodigingscode, anders
            // de standaardpoule.
            $poolJoins->forgetPendingCode($session);
            $poolJoins->join($user, $code);

            return $userAuthenticator->authenticateUser($user, $authenticator, $request);
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
