<?php
// src/Controller/ProfessionalPublicController.php
namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Repository\ServiceRepository;
use App\Repository\BusinessHoursRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException; // To handle 404 if user not found
use Symfony\Bundle\SecurityBundle\Security; // Import Security component

#[Route('/{bookingLink}', name: 'app_professional_public_')]
class ProfessionalPublicController extends AbstractController
{
    private Security $security; // Declare the Security property

    public function __construct(Security $security) // Inject Security component
    {
        $this->security = $security;
    }

    #[Route('/', name: 'show', methods: ['GET'], requirements: ['bookingLink' => '^(?!profile|admin|appointment|register|login|logout|verify|reset-password|service|client|business-hours|unavailability).*'])]
    public function show(
        string $bookingLink,
        UserRepository $userRepository,
        ServiceRepository $serviceRepository,
        BusinessHoursRepository $businessHoursRepository
    ): Response {
        /** @var User|null $professional */
        $professional = $userRepository->findOneByBookingLink($bookingLink);

        if (!$professional) {
            throw new NotFoundHttpException('Ce lien de réservation n\'existe pas.');
        }

        // Get active services for this professional
        $services = $serviceRepository->findActiveByProfessional($professional);

        // Get business hours for this professional
        $businessHours = $businessHoursRepository->findByProfessional($professional);

        // Sort business hours by day of the week (Monday to Sunday)
        // Assuming getDayOfWeek() returns an integer (1 for Monday, 2 for Tuesday, etc.)
        usort($businessHours, function($a, $b) {
            return $a->getDayOfWeek() <=> $b->getDayOfWeek();
        });

        // Determine if any user (client or professional) is logged in
        $isUserLoggedIn = $this->security->getUser() !== null;

        return $this->render('professional_public/show.html.twig', [
            'professional' => $professional,
            'services' => $services,
            'businessHours' => $businessHours,
            'isUserLoggedIn' => $isUserLoggedIn, // Pass this variable to the template
        ]);
    }
}
