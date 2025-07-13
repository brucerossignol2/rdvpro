<?php
// src/Controller/ProfessionalAppointmentListController.php
namespace App\Controller;

use App\Entity\User; // Represents the Professional
use App\Repository\AppointmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface; // Import CsrfTokenManagerInterface

#[Route('/my-professional-appointments')]
#[IsGranted('ROLE_USER')] // Only professionals (ROLE_USER) can access this page
class ProfessionalAppointmentListController extends AbstractController
{
    private CsrfTokenManagerInterface $csrfTokenManager; // Declare property

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager) // Inject CsrfTokenManagerInterface
    {
        $this->csrfTokenManager = $csrfTokenManager;
    }

    #[Route('/', name: 'app_professional_appointments_index', methods: ['GET'])]
    public function index(AppointmentRepository $appointmentRepository): Response
    {
        /** @var User $professional */
        $professional = $this->getUser();

        // Récupérer les rendez-vous futurs du professionnel, y compris ceux en cours
        // La méthode findByProfessionalUpcomingAppointments doit récupérer les RDV dont l'heure de fin est >= maintenant
        $now = new \DateTimeImmutable();
        $upcomingAppointments = $appointmentRepository->findByProfessionalUpcomingAppointments($professional, $now);

        // Prepare appointments with delete tokens for the Twig template
        $appointmentsWithTokens = [];
        foreach ($upcomingAppointments as $appointment) {
            $appointmentsWithTokens[] = [
                'appointment' => $appointment,
                'deleteToken' => $this->csrfTokenManager->getToken('delete' . $appointment->getId())->getValue(),
            ];
        }

        return $this->render('professional_appointment_list/index.html.twig', [
            'appointments' => $appointmentsWithTokens,
        ]);
    }
}
