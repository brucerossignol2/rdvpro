<?php
// src/Controller/AppointmentController.php
namespace App\Controller;

use Symfony\Component\Mailer\Mailer;
use App\Entity\Appointment;
use App\Entity\Client; // Import Client entity
use App\Form\AppointmentType;
use App\Repository\AppointmentRepository;
use App\Repository\BusinessHoursRepository;
use App\Repository\ServiceRepository; // Import ServiceRepository
use App\Repository\ClientRepository; // Import ClientRepository
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use DateTime; // Import DateTime class
use DateTimeZone; // Import DateTimeZone class
use DateInterval; // Import DateInterval for time calculations
use Symfony\Component\Dotenv\Dotenv; // Import Dotenv
use Symfony\Component\Mailer\Transport; // Import Transport

#[Route('/appointments')]
#[IsGranted('ROLE_USER')] // Restrict access to authenticated users
class AppointmentController extends AbstractController
{

    private MailerInterface $mailer; // Déclarez le type
    private CsrfTokenManagerInterface $csrfTokenManager; // Déclarez le type

    // Un seul constructeur qui prend toutes les dépendances nécessaires
    public function __construct(
        MailerInterface $mailer,
        CsrfTokenManagerInterface $csrfTokenManager
    ) {
        $this->mailer = $mailer;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * Displays the calendar with appointments and unavailabilities.
     */
    #[Route('/', name: 'app_appointment_index', methods: ['GET'])]
    public function index(AppointmentRepository $appointmentRepository, BusinessHoursRepository $businessHoursRepository): Response
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->getUser();

        // Fetch existing appointments and unavailabilities for the current professional
        $appointments = $appointmentRepository->findBy(['professional' => $professional]);

        $events = [];
        foreach ($appointments as $appointment) {
            $backgroundColor = $appointment->isIsPersonalUnavailability() ? '#dc3545' : '#007bff'; // Red for unavailability, blue for appointment
            $borderColor = $backgroundColor;
            $textColor = '#ffffff';

            $events[] = [
                'id' => $appointment->getId(),
                'title' => $appointment->getTitle(),
                'start' => $appointment->getStartTime()->format('Y-m-d\TH:i:s'),
                'end' => $appointment->getEndTime()->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $backgroundColor,
                'borderColor' => $borderColor,
                'textColor' => $textColor,
                'extendedProps' => [
                    'description' => $appointment->getDescription(),
                    'isPersonalUnavailability' => $appointment->isIsPersonalUnavailability(),
                    'clientId' => $appointment->getClient() ? $appointment->getClient()->getId() : null,
                    'clientName' => $appointment->getClient() ? $appointment->getClient()->getFullName() : null,
                    'services' => array_map(fn($service) => ['id' => $service->getId(), 'name' => $service->getName(), 'duration' => $service->getDuration()], $appointment->getServices()->toArray()),
                    'deleteToken' => $this->csrfTokenManager->getToken('delete' . $appointment->getId())->getValue(), // Generate and pass delete token
                ],
            ];
        }

        // Fetch and format business hours for FullCalendar
        $businessHoursEntities = $businessHoursRepository->findBy(
            ['professional' => $professional],
            ['dayOfWeek' => 'ASC']
        );

        $minTimeInMinutes = 24 * 60; // Initialize with a very high value (e.g., 24:00 in minutes)
        $maxTimeInMinutes = 0;      // Initialize with a very low value (e.g., 00:00 in minutes)
        $formattedBusinessHours = [];
        $foundOpenHours = false; // Flag to check if any open business hours were found

        // Logique pour déterminer si affiche la semaine suivante en fin de semaine travaillée
        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
        // Calcul du lundi de la semaine actuelle (plus robuste que 'this monday')
        // Définir la date au lundi de la semaine ISO en cours
        $currentWeekInitialDate = (clone $now)->setISODate(
            (int)$now->format('o'), // Année ISO
            (int)$now->format('W'), // Numéro de semaine ISO
            1                       // Jour de la semaine (1 pour Lundi)
        )->setTime(0, 0, 0); // S'assurer que l'heure est bien minuit
        // Calcul du lundi de la semaine prochaine
        $nextWeekInitialDate = (clone $currentWeekInitialDate)->modify('+1 week');

        $latestClosingTimeOfWeek = null;

        foreach ($businessHoursEntities as $bh) {
            if ($bh->isIsOpen()) {
                $foundOpenHours = true; // Mark that open hours were found

                $phpDayOfWeek = (int)$bh->getDayOfWeek(); // 1 for Monday, 7 for Sunday
                // FullCalendar uses 0 for Sunday, 1 for Monday, ..., 6 for Saturday
                // PHP 1 (Mon) -> FC 1 (Mon)
                // PHP 7 (Sun) -> FC 0 (Sun)
                $fullCalendarDayOfWeek = ($phpDayOfWeek === 7) ? 0 : $phpDayOfWeek;

                // First time slot
                if ($bh->getStartTime() && $bh->getEndTime()) {
                    $formattedBusinessHours[] = [
                        'daysOfWeek' => [$fullCalendarDayOfWeek],
                        'startTime' => $bh->getStartTime()->format('H:i'),
                        'endTime' => $bh->getEndTime()->format('H:i'),
                    ];
                    $startMinutes = (int)$bh->getStartTime()->format('H') * 60 + (int)$bh->getStartTime()->format('i');
                    $endMinutes = (int)$bh->getEndTime()->format('H') * 60 + (int)$bh->getEndTime()->format('i');

                    $minTimeInMinutes = min($minTimeInMinutes, $startMinutes);
                    $maxTimeInMinutes = max($maxTimeInMinutes, $endMinutes);

                    // Check for latest closing time of the week
                    $closingTime = (clone $currentWeekInitialDate)->modify('+' . ($phpDayOfWeek - 1) . ' days');
                    $closingTime->setTime($bh->getEndTime()->format('H'), $bh->getEndTime()->format('i'), $bh->getEndTime()->format('s'));

                    if ($latestClosingTimeOfWeek === null || $closingTime > $latestClosingTimeOfWeek) {
                        $latestClosingTimeOfWeek = $closingTime;
                    }
                }
                // Second time slot
                if ($bh->getStartTime2() && $bh->getEndTime2()) {
                    $formattedBusinessHours[] = [
                        'daysOfWeek' => [$fullCalendarDayOfWeek],
                        'startTime' => $bh->getStartTime2()->format('H:i'),
                        'endTime' => $bh->getEndTime2()->format('H:i'),
                    ];
                    $startMinutes = (int)$bh->getStartTime2()->format('H') * 60 + (int)$bh->getStartTime2()->format('i');
                    $endMinutes = (int)$bh->getEndTime2()->format('H') * 60 + (int)$bh->getEndTime2()->format('i');

                    $minTimeInMinutes = min($minTimeInMinutes, $startMinutes);
                    $maxTimeInMinutes = max($maxTimeInMinutes, $endMinutes);

                    // Check for latest closing time of the week for the second slot
                    $closingTime2 = (clone $currentWeekInitialDate)->modify('+' . ($phpDayOfWeek - 1) . ' days');
                    $closingTime2->setTime($bh->getEndTime2()->format('H'), $bh->getEndTime2()->format('i'), $bh->getEndTime2()->format('s'));

                    if ($latestClosingTimeOfWeek === null || $closingTime2 > $latestClosingTimeOfWeek) {
                        $latestClosingTimeOfWeek = $closingTime2;
                    }
                }
            }
        }

        // If no open business hours were found, set a default visible range for the calendar
        if (!$foundOpenHours) {
            $minTimeInMinutes = 8 * 60; // Default to 8 AM
            $maxTimeInMinutes = 18 * 60; // Default to 6 PM
        } else {
            // Round minTimeInMinutes down to the nearest 30-minute interval
            $minTimeInMinutes = floor($minTimeInMinutes / 30) * 30;

            // Round maxTimeInMinutes up to the nearest 30-minute interval,
            // then add 30 minutes (the slotDuration) to ensure the last slot is fully visible.
            // FullCalendar's slotMaxTime is exclusive, so it should be the time *after* the last desired slot.
            $maxTimeInMinutes = ceil($maxTimeInMinutes / 30) * 30;
            $maxTimeInMinutes += 30; // Add 30 minutes to ensure the last slot is fully visible.
        }

        // Convert back to HH:mm:ss format for FullCalendar's slotMinTime/slotMaxTime
        $formattedMinTime = sprintf('%02d:%02d:00', floor($minTimeInMinutes / 60), $minTimeInMinutes % 60);
        $formattedMaxTime = sprintf('%02d:%02d:00', floor($maxTimeInMinutes / 60), $maxTimeInMinutes % 60);

        $initialDate = $currentWeekInitialDate->format('Y-m-d'); // Default to current week

        // If a latest closing time was found, calculate the threshold
        if ($latestClosingTimeOfWeek) {
            // Calculate 1 hour before the latest closing time
            $thresholdTime = (clone $latestClosingTimeOfWeek)->sub(new DateInterval('PT1H'));

            // If current time is past the threshold, display next week
            if ($now > $thresholdTime) {
                $initialDate = $nextWeekInitialDate->format('Y-m-d');
            }
        }
        // If no business hours are defined for the week, it will default to the current week.


        // Generate a generic CSRF token for AJAX updates (drag/drop/resize)
        $updateTimesCsrfToken = $this->csrfTokenManager->getToken('update_appointment_times')->getValue();

        return $this->render('appointment/index.html.twig', [
            'events' => json_encode($events), // Pass events as JSON for FullCalendar
            'businessHours' => json_encode($formattedBusinessHours), // Pass formatted business hours
            'updateTimesCsrfToken' => $updateTimesCsrfToken, // Pass generic token for AJAX updates
            'minTime' => $formattedMinTime, // Pass dynamic min time
            'maxTime' => $formattedMaxTime, // Pass dynamic max time
            'initialDate' => $initialDate, // Pass the dynamically determined initial date
        ]);
    }

    /**
     * Creates a new appointment or personal unavailability.
     */
    #[Route('/new', name: 'app_appointment_new', methods: ['GET', 'POST'])]
    #[Route('/new/{start}/{end}/{clientId}', name: 'app_appointment_new_prefilled', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, BusinessHoursRepository $businessHoursRepository, ServiceRepository $serviceRepository, ClientRepository $clientRepository, ?string $start = null, ?string $end = null, ?int $clientId = null): Response
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->getUser();
        $appointment = new Appointment();

        // Set default title based on professional's business name or full name
        //$professionalName = $professional->getBusinessName() ?: ($professional->getFirstName() . ' ' . $professional->getLastName());
        //$appointment->setTitle('RDV posé par ' . $professionalName);

        $appointment->setProfessional($professional);

        // Pre-fill start and end times if provided from calendar click or client creation redirect
        if ($start && $end) {
            try {
                $appointment->setStartTime(new \DateTime($start));
                $appointment->setEndTime(new \DateTime($end));
            } catch (\Exception $e) {
                // Handle invalid date format, perhaps log and ignore
                $this->addFlash('error', 'Format de date ou d\'heure invalide.');
            }
        }

        // Pre-select client if ID is provided from client creation redirect
        if ($clientId) {
            $client = $clientRepository->find($clientId);
            if ($client && $client->getProfessional() === $professional) {
                $appointment->setClient($client);
                $this->addFlash('success', 'Le client "' . $client->getFullName() . '" a été créé et sélectionné.');
            } else {
                $this->addFlash('error', 'Le client spécifié n\'existe pas ou ne vous appartient pas.');
            }
        }

        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check for overlaps with existing appointments/unavailabilities
            $existingAppointments = $entityManager->getRepository(Appointment::class)->findOverlappingAppointments(
                $professional,
                $appointment->getStartTime(),
                $appointment->getEndTime(),
                $appointment->getId() // Exclude current appointment if editing
                );

            if (count($existingAppointments) > 0) {
                $this->addFlash('error', 'Ce créneau horaire chevauche un rendez-vous ou une indisponibilité existante.');
                return $this->render('appointment/new.html.twig', [
                    'appointment' => $appointment,
                    'form' => $form,
                    'servicesData' => $this->getServicesDataForTemplate($professional, $serviceRepository),
                ]);
            }

            // Validate against business hours if it's a client appointment
            if (!$appointment->isIsPersonalUnavailability()) {
                $isWithinBusinessHours = $this->isAppointmentWithinBusinessHours(
                    $professional,
                    $appointment->getStartTime(),
                    $appointment->getEndTime(),
                    $businessHoursRepository
                );

                if (!$isWithinBusinessHours) {
                    $this->addFlash('error', 'Le rendez-vous doit être dans vos heures d\'ouverture définies.');
                    return $this->render('appointment/new.html.twig', [
                        'appointment' => $appointment,
                        'form' => $form,
                        'servicesData' => $this->getServicesDataForTemplate($professional, $serviceRepository),
                    ]);
                }
            }

            $entityManager->persist($appointment);
            $entityManager->flush();

            // === DÉBUT DU CODE POUR L'ENVOI D'EMAIL ===
            // AJOUT DE LA CONDITION : Ne tente l'envoi d'email que si ce n'est PAS une indisponibilité personnelle
            if (!$appointment->isIsPersonalUnavailability()) {
                try {
                    /** @var \App\Entity\Client|null $client */
                    $client = $appointment->getClient();

                    /** @var \App\Entity\User|null $professional */
                    $professional = $this->getUser();

                    if ($client && $client->getEmail() && $professional && $professional->getEmail()) {
                        // Load .env file
                        $dotenv = new Dotenv();
                        $dotenv->loadEnv(dirname(__DIR__, 2) . '/.env'); // Adjust path as needed for your project structure

                        $mailerDsn = $_ENV['MAILER_DSN'] ?? null;

                        if (!$mailerDsn) {
                            throw new \RuntimeException('MAILER_DSN environment variable is not set in .env');
                        }

                        $transport = Transport::fromDsn($mailerDsn);
                        $mailer = new Mailer($transport);

                        $email = (new Email())
                            ->from($_ENV['MAILER_FROM_EMAIL'] ?? 'rdvpro@brelect.fr') // You can also get this from .env if needed
                            ->to($client->getEmail())
                            ->subject('Confirmation de votre rendez-vous avec ' . $professional->getFirstName() . ' ' . $professional->getLastName())
                            ->html($this->renderView(
                                'emails/appointment_confirmation.html.twig',
                                [
                                    'appointment' => $appointment,
                                    'professional' => $professional,
                                    'client' => $client,
                                ]
                            ));
                       if ($professional->getEmail()) {
                            $emailMessage->addReplyTo($professional->getEmail());
                        }

                        $mailer->send($email);
                        $this->addFlash('success', 'Rendez-vous créé avec succès et un email de confirmation a été envoyé au client.');
                    } else {
                        $this->addFlash('warning', 'Rendez-vous créé, mais impossible d\'envoyer l\'email de confirmation au client (email manquant).');
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Rendez-vous créé, mais une erreur est survenue lors de l\'envoi de l\'email : ' . $e->getMessage());
                }
            } else {
                // Message de succès spécifique pour une indisponibilité personnelle
                $this->addFlash('success', 'Indisponibilité personnelle créée avec succès.');
            }
            // === FIN DU CODE POUR L'ENVOI D'EMAIL ===

            return $this->redirectToRoute('app_appointment_index');
        }

        return $this->render('appointment/new.html.twig', [
            'appointment' => $appointment,
            'form' => $form,
            'servicesData' => $this->getServicesDataForTemplate($professional, $serviceRepository),
        ]);
    }

    /**
     * Edits an existing appointment or personal unavailability.
     */
    #[Route('/{id}/edit', name: 'app_appointment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Appointment $appointment, EntityManagerInterface $entityManager, BusinessHoursRepository $businessHoursRepository, ServiceRepository $serviceRepository): Response
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->getUser();

        // Ensure the appointment belongs to the logged-in professional
        if ($appointment->getProfessional() !== $professional) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier ce rendez-vous.');
        }

        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check for overlaps with existing appointments/unavailabilities
            $existingAppointments = $entityManager->getRepository(Appointment::class)->findOverlappingAppointments(
                $professional,
                $appointment->getStartTime(),
                $appointment->getEndTime(),
                $appointment->getId() // Exclude current appointment if editing
            );

            if (count($existingAppointments) > 0) {
                $this->addFlash('error', 'Ce créneau horaire chevauche un rendez-vous ou une indisponibilité existante.');
                return $this->render('appointment/edit.html.twig', [
                    'appointment' => $appointment,
                    'form' => $form,
                    'servicesData' => $this->getServicesDataForTemplate($professional, $serviceRepository),
                ]);
            }

            // Validate against business hours if it's a client appointment
            if (!$appointment->isIsPersonalUnavailability()) {
                $isWithinBusinessHours = $this->isAppointmentWithinBusinessHours(
                    $professional,
                    $appointment->getStartTime(),
                    $appointment->getEndTime(),
                    $businessHoursRepository
                );

                if (!$isWithinBusinessHours) {
                    $this->addFlash('error', 'Le rendez-vous doit être dans vos heures d\'ouverture définies.');
                    return $this->render('appointment/edit.html.twig', [
                        'appointment' => $appointment,
                        'form' => $form,
                        'servicesData' => $this->getServicesDataForTemplate($professional, $serviceRepository),
                    ]);
                }
            }

            $entityManager->flush();

            // AJOUT DE LA CONDITION POUR LE MESSAGE DE SUCCÈS LORS DE L'ÉDITION ÉGALEMENT
            if (!$appointment->isIsPersonalUnavailability()) {
                $this->addFlash('success', 'Le rendez-vous a été mis à jour avec succès !');
            } else {
                $this->addFlash('success', 'L\'indisponibilité personnelle a été mise à jour avec succès !');
            }
            

            return $this->redirectToRoute('app_appointment_index');
        }

        return $this->render('appointment/edit.html.twig', [
            'appointment' => $appointment,
            'form' => $form,
            'servicesData' => $this->getServicesDataForTemplate($professional, $serviceRepository),
        ]);
    }

    /**
     * Handles AJAX request to update appointment times (for drag/drop/resize).
     */
    #[Route('/{id}/update-times', name: 'app_appointment_update_times', methods: ['POST'])]
    public function updateTimes(Request $request, Appointment $appointment, EntityManagerInterface $entityManager, BusinessHoursRepository $businessHoursRepository): JsonResponse
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->getUser();

        // Ensure the appointment belongs to the logged-in professional
        if ($appointment->getProfessional() !== $professional) {
            return new JsonResponse(['status' => 'error', 'message' => 'Accès non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        // Validate CSRF token for AJAX update
        if (!$this->isCsrfTokenValid('update_appointment_times', $request->headers->get('X-CSRF-TOKEN'))) {
            return new JsonResponse(['status' => 'error', 'message' => 'Token CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['start']) || !isset($data['end'])) {
            return new JsonResponse(['status' => 'error', 'message' => 'Données de temps manquantes.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $newStartTime = new \DateTime($data['start']);
            $newEndTime = new \DateTime($data['end']);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Format de date ou d\'heure invalide.'], Response::HTTP_BAD_REQUEST);
        }

        // Check for overlaps with existing appointments/unavailabilities
        $existingAppointments = $entityManager->getRepository(Appointment::class)->findOverlappingAppointments(
            $professional,
            $newStartTime,
            $newEndTime,
            $appointment->getId() // Exclude current appointment
        );

        if (count($existingAppointments) > 0) {
            return new JsonResponse(['status' => 'error', 'message' => 'Ce créneau horaire chevauche un rendez-vous ou une indisponibilité existante.'], Response::HTTP_CONFLICT);
        }

        // Validate against business hours if it's a client appointment
        if (!$appointment->isIsPersonalUnavailability()) {
            $isWithinBusinessHours = $this->isAppointmentWithinBusinessHours(
                $professional,
                $newStartTime,
                $newEndTime,
                $businessHoursRepository
            );

            if (!$isWithinBusinessHours) {
                return new JsonResponse(['status' => 'error', 'message' => 'Le rendez-vous doit être dans vos heures d\'ouverture définies.'], Response::HTTP_BAD_REQUEST);
            }
        }

        $appointment->setStartTime($newStartTime);
        $appointment->setEndTime($newEndTime);
        $entityManager->flush();

        return new JsonResponse(['status' => 'success', 'message' => 'Rendez-vous mis à jour avec succès.']);
    }


    /**
     * Deletes an appointment.
     */
    #[Route('/{id}', name: 'app_appointment_delete', methods: ['POST'])]
    public function delete(Request $request, Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->getUser();

        // Ensure the appointment belongs to the logged-in professional
        if ($appointment->getProfessional() !== $professional) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer ce rendez-vous.');
        }

        // The CSRF token for delete is now passed via extendedProps.deleteToken
        // We need to retrieve it from the request body or header if it's an AJAX request
        // For a standard form submission, it's in request->request->get('_token')
        // The token name is 'delete' concatenated with the appointment ID
        if ($this->isCsrfTokenValid('delete' . $appointment->getId(), $request->request->get('_token'))) {
            $entityManager->remove($appointment);
            $entityManager->flush();
            $this->addFlash('success', 'Le rendez-vous a été supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. La suppression n\'a pas pu être effectuée.');
        }

        // Check if the request came from the calendar page to redirect back there
        if ($request->request->get('redirect_to_calendar') === 'true') {
            return $this->redirectToRoute('app_appointment_index');
        }

        // Otherwise, redirect back to the professional's appointment list
        return $this->redirectToRoute('app_professional_appointments_index');
    }

    /**
     * Helper to check if an appointment falls within the professional's business hours.
     * This considers both single and double time slots for each day.
     */
    private function isAppointmentWithinBusinessHours(
        \App\Entity\User $professional,
        \DateTimeInterface $appointmentStart,
        \DateTimeInterface $appointmentEnd,
        BusinessHoursRepository $businessHoursRepository
    ): bool {
        $dayOfWeek = (int)$appointmentStart->format('N'); // 1 (for Monday) through 7 (for Sunday)
        $businessHour = $businessHoursRepository->findOneBy([
            'professional' => $professional,
            'dayOfWeek' => $dayOfWeek
        ]);

        if (!$businessHour || !$businessHour->isIsOpen()) {
            return false; // Professional is closed on this day
        }

        $apptStartSec = $appointmentStart->getTimestamp();
        $apptEndSec = $appointmentEnd->getTimestamp();

        // Get today's date part for time comparisons
        $todayDate = $appointmentStart->format('Y-m-d');

        // Convert business hours to DateTime objects for comparison on the same date
        $bhStart1 = $businessHour->getStartTime() ? new \DateTime($todayDate . ' ' . $businessHour->getStartTime()->format('H:i:s')) : null;
        $bhEnd1 = $businessHour->getEndTime() ? new \DateTime($todayDate . ' ' . $businessHour->getEndTime()->format('H:i:s')) : null;
        $bhStart2 = $businessHour->getStartTime2() ? new \DateTime($todayDate . ' ' . $businessHour->getStartTime2()->format('H:i:s')) : null;
        $bhEnd2 = $businessHour->getEndTime2() ? new \DateTime($todayDate . ' ' . $businessHour->getEndTime2()->format('H:i:s')) : null;

        // Check if appointment is fully contained within the first time slot
        $inFirstSlot = ($bhStart1 && $bhEnd1 && $apptStartSec >= $bhStart1->getTimestamp() && $apptEndSec <= $bhEnd1->getTimestamp());

        // Check if appointment is fully contained within the second time slot
        $inSecondSlot = ($bhStart2 && $bhEnd2 && $apptStartSec >= $bhStart2->getTimestamp() && $apptEndSec <= $bhEnd2->getTimestamp());

        // If there's a second slot, the appointment must be fully within one of the two slots
        if ($bhStart2 && $bhEnd2) {
            return $inFirstSlot || $inSecondSlot;
        } else {
            // If there's only one slot (or no second slot defined), check against the first slot
            return $inFirstSlot;
        }
    }

    /**
     * Helper method to get services data formatted for JavaScript.
     */
    private function getServicesDataForTemplate(\App\Entity\User $professional, ServiceRepository $serviceRepository): string
    {
        $services = $serviceRepository->findActiveByProfessional($professional);
        $servicesData = [];
        foreach ($services as $service) {
            $servicesData[] = [
                'id' => $service->getId(),
                'duration' => $service->getDuration(),
            ];
        }
        return json_encode($servicesData);
    }
}