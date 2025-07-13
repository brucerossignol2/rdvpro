<?php
// src/Security/LoginSuccessHandler.php

namespace App\Security;

use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Entity\Client; // Import the Client entity
use App\Entity\User; // Import the User entity (for Professional)

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();

        // If the user is a Client
        if ($user instanceof Client) {
            // Get the professional associated with the client
            $professional = $user->getProfessional();
            if ($professional && $professional->getBookingLink()) {
                // Redirect to the professional's public page
                return new RedirectResponse($this->router->generate('app_professional_public_show', [
                    'bookingLink' => $professional->getBookingLink()
                ]));
            } else {
                // Fallback if client has no professional or bookingLink (should not happen if data is consistent)
                return new RedirectResponse($this->router->generate('app_client_profile_show')); // Redirect to client's own profile
            }
        }

        // If the user is a Professional (User entity)
        if ($user instanceof User) {
            // Redirect to the professional's calendar
            return new RedirectResponse($this->router->generate('app_appointment_index'));
        }

        // Default redirect if user type is not recognized (should ideally not be reached)
        return new RedirectResponse($this->router->generate('app_home'));
    }
}
