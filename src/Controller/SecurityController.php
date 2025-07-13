<?php
// src/Controller/SecurityController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\HttpFoundation\Request; // N'oubliez pas d'importer la classe Request
use Symfony\Component\HttpFoundation\Session\SessionInterface; // Importez SessionInterface

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request, SessionInterface $session): Response // Injectez Request et SessionInterface ici
    {
        // Si l'utilisateur est déjà connecté, le rediriger ailleurs.
        // Cette logique est importante AVANT de tenter de se connecter à nouveau.
        if ($this->getUser()) {
            // Le success_handler gérera la redirection après une nouvelle connexion,
            // mais si l'utilisateur est DÉJÀ connecté et essaie d'accéder à /login,
            // nous le redirigeons vers sa page d'accueil par défaut.
            if ($this->isGranted('ROLE_CLIENT')) {
                // Si c'est un client déjà connecté, le rediriger vers sa page de profil ou ses rendez-vous
                return $this->redirectToRoute('app_client_profile_show');
            }
            // Si c'est un professionnel déjà connecté, le rediriger vers son calendrier
            return $this->redirectToRoute('app_appointment_index');
        }

        // obtenir l'erreur de connexion s'il y en a une
        $error = $authenticationUtils->getLastAuthenticationError();
        // dernier nom d'utilisateur saisi par l'utilisateur
        $lastUsername = $authenticationUtils->getLastUsername();

        // Récupérer le bookingLink du paramètre de requête (s'il est présent dans l'URL)
        $bookingLink = $request->query->get('bookingLink');

        // Si le bookingLink n'est pas dans l'URL, essayez de le récupérer d'une session
        // (utile si l'utilisateur arrive sur la page de login sans le bookingLink dans l'URL)
        // Par exemple, si vous l'avez stocké après une inscription client ratée
        if (!$bookingLink && $session->has('last_booking_link')) {
            $bookingLink = $session->get('last_booking_link');
        }

        // IMPORTANT : Stocker le bookingLink en session s'il est présent dans l'URL.
        // Cela assure qu'il persiste lors des redirections, par exemple après une tentative de connexion échouée.
        // Ne le stocker que s'il a été trouvé ou passé, pour ne pas écraser une valeur existante inutilement.
        if ($bookingLink) {
            $session->set('last_booking_link', $bookingLink);
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'bookingLink' => $bookingLink, // Passer le bookingLink au template Twig
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Cette méthode peut être vide - elle sera interceptée par la clé de déconnexion de votre pare-feu.');
    }
}
