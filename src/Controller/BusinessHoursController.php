<?php
// src/Controller/BusinessHoursController.php
namespace App\Controller;

use App\Entity\BusinessHours;
use App\Form\BusinessHoursType;
use App\Repository\BusinessHoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile/business-hours')]
#[IsGranted('ROLE_USER')] // Only authenticated users can manage their business hours
class BusinessHoursController extends AbstractController
{
    #[Route('/', name: 'app_business_hours_index', methods: ['GET'])]
    public function index(BusinessHoursRepository $businessHoursRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->getUser();

        // Get business hours for the current professional, ordered by day of week
        $businessHours = $businessHoursRepository->findBy(
            ['professional' => $professional],
            ['dayOfWeek' => 'ASC']
        );

        // If no business hours are set, initialize for all days of the week
        // This ensures the form always has 7 entries to work with
        if (count($businessHours) === 0) {
            for ($i = 1; $i <= 7; $i++) {
                $hour = new BusinessHours();
                $hour->setProfessional($professional);
                $hour->setDayOfWeek($i);
                $hour->setIsOpen(false); // Default to closed
                $hour->setStartTime(null); // Ensure null for new time fields
                $hour->setEndTime(null);   // Ensure null for new time fields
                $hour->setStartTime2(null); // Ensure null for new time fields
                $hour->setEndTime2(null);   // Ensure null for new time fields
                $entityManager->persist($hour);
            }
            $entityManager->flush();
            // Re-fetch to get the newly created entities
            $businessHours = $businessHoursRepository->findBy(
                ['professional' => $professional],
                ['dayOfWeek' => 'ASC']
            );
        }

        return $this->render('business_hours/index.html.twig', [
            'businessHours' => $businessHours,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_business_hours_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, BusinessHours $businessHour, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->getUser();

        // Ensure the business hour entry belongs to the logged-in professional
        if ($businessHour->getProfessional() !== $professional) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cet horaire.');
        }

        $form = $this->createForm(BusinessHoursType::class, $businessHour);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // If the day is marked as closed, clear all time slots
            if (!$businessHour->isIsOpen()) {
                $businessHour->setStartTime(null);
                $businessHour->setEndTime(null);
                $businessHour->setStartTime2(null);
                $businessHour->setEndTime2(null);
            } else {
                // If the second time slot is not provided in the form, ensure it's null in the entity
                // This handles cases where user removes the second slot
                // We check if the form field was submitted, not just if the entity value is null
                $formData = $request->request->all();
                $businessHoursFormData = $formData['business_hours'] ?? [];

                if (!isset($businessHoursFormData['startTime2']) && !isset($businessHoursFormData['endTime2'])) {
                    $businessHour->setStartTime2(null);
                    $businessHour->setEndTime2(null);
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Les horaires ont été mis à jour avec succès !');

            return $this->redirectToRoute('app_business_hours_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('business_hours/edit.html.twig', [
            'business_hour' => $businessHour,
            'form' => $form,
        ]);
    }

    // No 'new' action needed as we pre-populate 7 days.
    // No 'delete' action for individual days, as they represent the week structure.
}
