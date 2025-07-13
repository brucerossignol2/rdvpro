<?php
// src/Controller/ClientAppointmentController.php
namespace App\Controller;

use App\Entity\Client;
use App\Repository\AppointmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/my-appointments')]
#[IsGranted('ROLE_CLIENT')] // Seuls les clients peuvent accéder à leurs rendez-vous
class ClientAppointmentController extends AbstractController
{
    #[Route('/', name: 'app_client_appointments_index', methods: ['GET'])]
    public function index(AppointmentRepository $appointmentRepository): Response
    {
        $user = $this->getUser();

        // Assurez-vous que l'utilisateur connecté est bien une instance de Client
        if (!$user instanceof Client) {
            throw new AccessDeniedException('Vous n\'avez pas les droits pour accéder à cette page.');
        }

        /** @var Client $client */
        $client = $user;

        // Récupérer les rendez-vous futurs du client, y compris ceux en cours
        // La méthode findByClientUpcomingAppointments doit récupérer les RDV dont l'heure de fin est >= maintenant
        $now = new \DateTimeImmutable();
        $upcomingAppointments = $appointmentRepository->findByClientUpcomingAppointments($client, $now);

        return $this->render('client_appointment/index.html.twig', [
            'appointments' => $upcomingAppointments,
        ]);
    }
}
