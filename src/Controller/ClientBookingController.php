<?php
// src/Controller/ClientBookingController.php
namespace App\Controller;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use App\Entity\Client;
use App\Entity\User; // Represents the Professional
use App\Entity\Service;
use App\Entity\Appointment; // Ensure Appointment entity is imported
use App\Repository\AppointmentRepository;
use App\Repository\BusinessHoursRepository;
use App\Repository\UserRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request; // Import Request class
use Symfony\Component\HttpFoundation\JsonResponse; // Needed for AJAX if you add it later
use DateTime; // Import DateTime class
use DateTimeZone; // Import DateTimeZone class
use DateInterval; // Import DateInterval for time calculations

#[Route('/book')]
class ClientBookingController extends AbstractController
{
    private Security $security;
    private MailerInterface $mailer; // Déclarez le type

    public function __construct(Security $security, MailerInterface $mailer)
    {
        $this->security = $security;
        $this->mailer = $mailer;
    }

    /**
     * Displays the booking calendar for a specific professional.
     * Clients can view availability and initiate booking.
     */
    #[Route('/{bookingLink}/{serviceId}', name: 'app_client_booking_calendar', methods: ['GET'])]
    public function calendar(
        string $bookingLink,
        int $serviceId,
        UserRepository $userRepository,
        ServiceRepository $serviceRepository,
        AppointmentRepository $appointmentRepository,
        BusinessHoursRepository $businessHoursRepository,
        Request $request // Inject Request to access session
    ): Response {
        // Find the professional by their booking link
        $professional = $userRepository->findOneBy(['bookingLink' => $bookingLink]);
        if (!$professional) {
            throw new NotFoundHttpException('Professionnel non trouvé.');
        }

        // Find the selected service
        $selectedService = $serviceRepository->find($serviceId);
        if (!$selectedService || $selectedService->getProfessional() !== $professional) {
            throw new NotFoundHttpException('Service non trouvé ou n\'appartient pas à ce professionnel.');
        }

        // Determine if the client is logged in
        $isClientLoggedIn = $this->security->isGranted('ROLE_CLIENT');
        /** @var Client|null $currentClient */
        $currentClient = $isClientLoggedIn ? $this->security->getUser() : null;

        // Store the bookingLink in session if client is not logged in
        // This ensures the login/registration page can retrieve it
        if (!$isClientLoggedIn) {
            $request->getSession()->set('last_booking_link', $bookingLink);
        } else {
            // Clear it if the client is logged in and it's no longer needed
            $request->getSession()->remove('last_booking_link');
        }

        // Fetch existing appointments and unavailabilities for the professional
        $appointments = $appointmentRepository->findBy(['professional' => $professional]);

        $events = [];
        foreach ($appointments as $appointment) {
            $eventData = [
                'id' => $appointment->getId(),
                'start' => $appointment->getStartTime()->format('Y-m-d\TH:i:s'),
                'end' => $appointment->getEndTime()->format('Y-m-d\TH:i:s'),
                'extendedProps' => [
                    'isPersonalUnavailability' => $appointment->isIsPersonalUnavailability(),
                    'isClientAppointment' => !$appointment->isIsPersonalUnavailability(),
                    'clientId' => $appointment->getClient() ? $appointment->getClient()->getId() : null,
                ],
            ];

            if ($appointment->isIsPersonalUnavailability()) {
                // Professional's unavailability
                $eventData['display'] = 'background'; // Render as background event
                $eventData['backgroundColor'] = '#e9ecef'; // Light grey
                $eventData['borderColor'] = '#e9ecef';
                $eventData['classNames'] = ['professional-unavailability'];
                $eventData['title'] = ''; // Empty title as requested
            } elseif ($currentClient && $appointment->getClient() && $appointment->getClient()->getId() === $currentClient->getId()) {
                // Logged-in client's own appointment
                $eventData['backgroundColor'] = '#198754'; // Darker Green
                $eventData['borderColor'] = '#198754';
                $eventData['textColor'] = '#ffffff'; // White text
                $eventData['classNames'] = ['client-own-appointment'];
                // Title will be formatted in JS to show time slot
                $eventData['title'] = $appointment->getStartTime()->format('H:i') . ' - ' . $appointment->getEndTime()->format('H:i');
            } else {
                // Other clients' appointments
                $eventData['display'] = 'background'; // Render as background event
                $eventData['backgroundColor'] = '#e9ecef'; // Light grey
                $eventData['borderColor'] = '#e9ecef';
                $eventData['classNames'] = ['other-client-appointment'];
                $eventData['title'] = ''; // Empty title as requested
            }
            $events[] = $eventData;
        }

        // Fetch business hours for the professional
        $businessHoursEntities = $businessHoursRepository->findBy(['professional' => $professional]);
        $businessHours = [];

        // Initialize minTime to a very late hour and maxTime to a very early hour
        // This way, any actual business hour will correctly update these values.
        $minTime = '23:59';
        $maxTime = '00:00';

        // Flag to check if any open business hours were found
        $foundOpenHours = false;

        // Logique pour déterminer si affiche la semaine suivante en fin de semaine travaillée du pro
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
            $dayOfWeek = $bh->getDayOfWeek(); // 1 for Monday, 7 for Sunday

            // FullCalendar uses 0 for Sunday, 1 for Monday...
            // This conversion is only for FullCalendar's `businessHours` array, not for `initialDate` logic.
            $fcDayOfWeek = ($dayOfWeek == 7) ? 0 : $dayOfWeek;

            if ($bh->isIsOpen()) {
                $foundOpenHours = true; // Mark that open hours were found

                // Handle first time slot
                if ($bh->getStartTime() && $bh->getEndTime()) {
                    $businessHours[] = [
                        'daysOfWeek' => [$fcDayOfWeek],
                        'startTime' => $bh->getStartTime()->format('H:i'),
                        'endTime' => $bh->getEndTime()->format('H:i'),
                    ];
                    // Update min/max times for calendar view
                    if ($bh->getStartTime()->format('H:i') < $minTime) {
                        $minTime = $bh->getStartTime()->format('H:i');
                    }
                    if ($bh->getEndTime()->format('H:i') > $maxTime) {
                        $maxTime = $bh->getEndTime()->format('H:i');
                    }

                    // Check for latest closing time of the week
                    // Create a DateTime object for the closing time on its respective day of the current week
                    $closingTime = (clone $currentWeekInitialDate)->modify('+' . ($dayOfWeek - 1) . ' days');
                    $closingTime->setTime($bh->getEndTime()->format('H'), $bh->getEndTime()->format('i'), $bh->getEndTime()->format('s'));

                    if ($latestClosingTimeOfWeek === null || $closingTime > $latestClosingTimeOfWeek) {
                        $latestClosingTimeOfWeek = $closingTime;
                    }
                }
                // Handle second time slot
                if ($bh->getStartTime2() && $bh->getEndTime2()) {
                    $businessHours[] = [
                        'daysOfWeek' => [$fcDayOfWeek],
                        'startTime' => $bh->getStartTime2()->format('H:i'),
                        'endTime' => $bh->getEndTime2()->format('H:i'),
                    ];
                    // Update min/max times for calendar view
                    if ($bh->getStartTime2()->format('H:i') < $minTime) {
                        $minTime = $bh->getStartTime2()->format('H:i');
                    }
                    if ($bh->getEndTime2()->format('H:i') > $maxTime) {
                        $maxTime = $bh->getEndTime2()->format('H:i');
                    }

                    // Check for latest closing time of the week for the second slot
                    $closingTime2 = (clone $currentWeekInitialDate)->modify('+' . ($dayOfWeek - 1) . ' days');
                    $closingTime2->setTime($bh->getEndTime2()->format('H'), $bh->getEndTime2()->format('i'), $bh->getEndTime2()->format('s'));

                    if ($latestClosingTimeOfWeek === null || $closingTime2 > $latestClosingTimeOfWeek) {
                        $latestClosingTimeOfWeek = $closingTime2;
                    }
                }
            }
        }

        // If no open business hours were found, set a default visible range for the calendar
        if (!$foundOpenHours) {
            $minTime = '08:00'; // Default start time if no open hours are defined
            $maxTime = '18:00'; // Default end time if no open hours are defined
        }

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

        return $this->render('client_booking/calendar.html.twig', [
            'professional' => $professional,
            'selectedService' => $selectedService,
            'events' => json_encode($events), // Pass events as JSON
            'businessHours' => json_encode($businessHours), // Pass business hours as JSON
            'minTime' => $minTime,
            'maxTime' => $maxTime,
            'isClientLoggedIn' => $isClientLoggedIn,
            'clientRegistrationBookingLink' => $professional->getBookingLink(), // Used for registration link
            'initialDate' => $initialDate, // Pass the dynamically determined initial date
        ]);
    }

    /**
     * Displays available booking slots for a specific professional and service.
     */
    #[Route('/{bookingLink}/{serviceId}/slots', name: 'app_client_booking_slots', methods: ['GET'])]
    public function slots(
        string $bookingLink,
        int $serviceId,
        UserRepository $userRepository,
        ServiceRepository $serviceRepository,
        AppointmentRepository $appointmentRepository,
        BusinessHoursRepository $businessHoursRepository,
        Request $request
    ): Response {
        $professional = $userRepository->findOneBy(['bookingLink' => $bookingLink]);
        if (!$professional) {
            throw new NotFoundHttpException('Professionnel non trouvé.');
        }

        $selectedService = $serviceRepository->find($serviceId);
        if (!$selectedService || $selectedService->getProfessional() !== $professional) {
            throw new NotFoundHttpException('Service non trouvé ou n\'appartient pas à ce professionnel.');
        }

        $isClientLoggedIn = $this->security->isGranted('ROLE_CLIENT');
        if (!$isClientLoggedIn) {
            $request->getSession()->set('last_booking_link', $bookingLink);
        } else {
            $request->getSession()->remove('last_booking_link');
        }

        $parisTimezone = new DateTimeZone('Europe/Paris');
        $now = new DateTime('now', $parisTimezone);

        $availableSlots = [];
        $displayDays = 5; // Number of days to display
        $startDayOffset = 0; // Start from today

        // Handle navigation for previous/next week
        $currentDateParam = $request->query->get('currentDate');
        if ($currentDateParam) {
            try {
                $initialDisplayDate = new DateTime($currentDateParam, $parisTimezone);
            } catch (\Exception $e) {
                $initialDisplayDate = clone $now;
            }
        } else {
            $initialDisplayDate = clone $now;
        }

        // Adjust initialDisplayDate to the start of the relevant week for display
        // If today is Friday, and we show 8 days, we might want to start from today or Monday
        // For simplicity, let's just use the initialDisplayDate as the starting point
        // and iterate from there, ensuring we skip non-working days.

        // Determine the actual start date for the first column
        $currentDay = (int)$now->format('N'); // 1 (for Monday) through 7 (for Sunday)
        $startIterationDate = clone $now;
        // If current time is after latest closing time of today (e.g., 17:00), start from next day
        // This is a simplification; a more robust check involves business hours for today.
        // For now, let's assume if it's late in the day, we show slots from tomorrow.
        // Or, more accurately, we only show slots that are at least 1 hour in the future.

        // Define a fixed interval for slot generation (30 minutes)
        $slotInterval = new DateInterval('PT30M');
        // Service duration needs to be converted to slots (e.g., 50 min service needs 2x30min = 1h slot)
        $serviceDurationMinutes = $selectedService->getDuration();
        $requiredSlotUnits = ceil($serviceDurationMinutes / 30); // Number of 30-min units required
        $actualServiceDuration = new DateInterval('PT' . ($requiredSlotUnits * 30) . 'M');

        for ($i = 0; $i < $displayDays * 2; $i++) { // Iterate enough days to find 8 working days
            $currentWorkingDate = (clone $initialDisplayDate)->modify("+$i days");
            $dayOfWeek = (int)$currentWorkingDate->format('N'); // 1 (Mon) - 7 (Sun)

            // Fetch business hours for the current day of the week
            $businessHours = $businessHoursRepository->findOneBy([
                'professional' => $professional,
                'dayOfWeek' => $dayOfWeek
            ]);

            // If no business hours or not open for this day, skip
            if (!$businessHours || !$businessHours->isIsOpen()) {
                continue;
            }

            $daySlots = [];

            // Get existing appointments/unavailabilities for this specific day
            $startOfDay = (clone $currentWorkingDate)->setTime(0, 0, 0);
            $endOfDay = (clone $currentWorkingDate)->setTime(23, 59, 59);
            $existingAppointments = $appointmentRepository->findAppointmentsInDateRange(
                $professional,
                $startOfDay, // Pass UTC DateTime objects if DB stores UTC
                $endOfDay    // Pass UTC DateTime objects if DB stores UTC
            );

            // Convert existing appointments to Paris timezone for comparison with business hours
            $occupiedSlots = [];
            foreach ($existingAppointments as $appt) {
                $apptStartParis = (clone $appt->getStartTime())->setTimezone($parisTimezone);
                $apptEndParis = (clone $appt->getEndTime())->setTimezone($parisTimezone);
                $occupiedSlots[] = [
                    'start' => $apptStartParis,
                    'end' => $apptEndParis
                ];
            }

            // Generate slots for the first period
            if ($businessHours->getStartTime() && $businessHours->getEndTime()) {
                $start = new DateTime($currentWorkingDate->format('Y-m-d') . ' ' . $businessHours->getStartTime()->format('H:i:s'), $parisTimezone);
                $end = new DateTime($currentWorkingDate->format('Y-m-d') . ' ' . $businessHours->getEndTime()->format('H:i:s'), $parisTimezone);
                $daySlots = array_merge($daySlots, $this->generateAvailableTimeSlots(
                    $start,
                    $end,
                    $slotInterval,
                    $actualServiceDuration,
                    $now,
                    $occupiedSlots
                ));
            }

            // Generate slots for the second period
            if ($businessHours->getStartTime2() && $businessHours->getEndTime2()) {
                $start2 = new DateTime($currentWorkingDate->format('Y-m-d') . ' ' . $businessHours->getStartTime2()->format('H:i:s'), $parisTimezone);
                $end2 = new DateTime($currentWorkingDate->format('Y-m-d') . ' ' . $businessHours->getEndTime2()->format('H:i:s'), $parisTimezone);
                $daySlots = array_merge($daySlots, $this->generateAvailableTimeSlots(
                    $start2,
                    $end2,
                    $slotInterval,
                    $actualServiceDuration,
                    $now,
                    $occupiedSlots
                ));
            }

            if (!empty($daySlots)) {
                $availableSlots[$currentWorkingDate->format('Y-m-d')] = [
                    'date' => $currentWorkingDate,
                    'slots' => $daySlots
                ];
            }

            // Stop if we have enough working days for display, but keep iterating to find up to 8
            if (count($availableSlots) >= $displayDays) {
                break;
            }
        }
        // Take only the first $displayDays working days
        $availableSlots = array_slice($availableSlots, 0, $displayDays, true);


        return $this->render('client_booking/slots.html.twig', [
            'professional' => $professional,
            'selectedService' => $selectedService,
            'availableSlots' => $availableSlots,
            'isClientLoggedIn' => $isClientLoggedIn,
            'clientRegistrationBookingLink' => $professional->getBookingLink(),
            'currentDate' => $initialDisplayDate->format('Y-m-d'), // Pass the current display date for navigation
            'serviceDuration' => $serviceDurationMinutes, // Pass the original service duration
        ]);
    }

    /**
     * Helper method to generate available time slots for a given period.
     * Takes into account existing occupied slots and a future buffer.
     */
    private function generateAvailableTimeSlots(
        DateTime $periodStart,
        DateTime $periodEnd,
        DateInterval $slotInterval,
        DateInterval $serviceDuration,
        DateTime $now,
        array $occupiedSlots
    ): array {
        $slots = [];
        $currentTime = clone $periodStart;
        $futureBuffer = (clone $now)->add(new DateInterval('PT1H')); // Slots must be at least 1 hour in the future

        while ($currentTime->add($serviceDuration) <= $periodEnd) {
            $slotStartTime = (clone $currentTime)->sub($serviceDuration); // The actual start of the slot
            $slotEndTime = clone $currentTime;

            // Ensure the slot starts at a 30-minute interval if it's not already
            // This is crucial if business hours don't start on exact 30-min marks
            $minute = (int)$slotStartTime->format('i');
            if ($minute % 30 !== 0) {
                $newMinute = floor($minute / 30) * 30 + 30;
                if ($newMinute >= 60) {
                    $slotStartTime->add(new DateInterval('PT' . ($newMinute - $minute) . 'M'));
                    $slotStartTime->add(new DateInterval('PT1H'))->setTime((int)$slotStartTime->format('H'), 0); // Reset minutes to 0
                } else {
                    $slotStartTime->add(new DateInterval('PT' . ($newMinute - $minute) . 'M'));
                }
                $slotEndTime = (clone $slotStartTime)->add($serviceDuration);
            }

            // Skip if the slot's end time exceeds the period end time
            if ($slotEndTime > $periodEnd) {
                break;
            }

            // Skip if the slot's start time is in the past or not at least 1 hour in the future
            if ($slotStartTime < $futureBuffer) {
                $currentTime = (clone $slotStartTime)->add($slotInterval); // Move to the next potential 30-min start
                continue;
            }

            $isOccupied = false;
            foreach ($occupiedSlots as $occupied) {
                // Check for overlap: [start1, end1) overlaps with [start2, end2) if start1 < end2 AND end1 > start2
                if ($slotStartTime < $occupied['end'] && $slotEndTime > $occupied['start']) {
                    $isOccupied = true;
                    break;
                }
            }

            if (!$isOccupied) {
                $slots[] = [
                    'start' => $slotStartTime,
                    'end' => $slotEndTime
                ];
            }
            $currentTime = (clone $slotStartTime)->add($slotInterval); // Move to the next 30-min increment for the next potential slot
        }
        return $slots;
    }


    /**
     * Confirms a client's booking.
     */
    #[Route('/{bookingLink}/{serviceId}/confirm/{start}/{end}', name: 'app_client_booking_confirm', methods: ['GET', 'POST'])]
    public function confirmBooking(
        Request $request, // Add Request object here
        string $bookingLink,
        int $serviceId,
        string $start, // ISO string from FullCalendar
        string $end,   // ISO string from FullCalendar
        UserRepository $userRepository,
        ServiceRepository $serviceRepository,
        AppointmentRepository $appointmentRepository,
        BusinessHoursRepository $businessHoursRepository,
        EntityManagerInterface $entityManager,
        Security $security
    ): Response {
        // Ensure the client is logged in
        if (!$security->isGranted('ROLE_CLIENT')) {
            // Store bookingLink in session before redirecting to login
            $request->getSession()->set('last_booking_link', $bookingLink);
            throw new AccessDeniedException('Vous devez être connecté en tant que client pour confirmer un rendez-vous.');
        }

        /** @var Client $client */
        $client = $security->getUser();

        // Find the professional
        $professional = $userRepository->findOneBy(['bookingLink' => $bookingLink]);
        if (!$professional) {
            throw new NotFoundHttpException('Professionnel non trouvé.');
        }

        // Find the selected service
        $selectedService = $serviceRepository->find($serviceId);
        if (!$selectedService || $selectedService->getProfessional() !== $professional) {
            throw new NotFoundHttpException('Service non trouvé ou n\'appartient pas à ce professionnel.');
        }

        // Convert start and end times from ISO string to DateTime objects (they are UTC from JS)
        $startTime = new DateTime($start); // This will be in UTC
        $endTime = new DateTime($end);     // This will be in UTC

        // Set the timezone for display purposes in the template to Europe/Paris
        $parisTimezone = new DateTimeZone('Europe/Paris');
        $startTime->setTimezone($parisTimezone);
        $endTime->setTimezone($parisTimezone);

        // Validate that the chosen slot is within business hours and does not overlap
        // Re-check business hours availability for the selected day
        $dayOfWeek = (int)$startTime->format('N'); // 1 (for Monday) through 7 (for Sunday)
        $businessHour = $businessHoursRepository->findOneBy([
            'professional' => $professional,
            'dayOfWeek' => $dayOfWeek
        ]);

        if (!$businessHour || !$businessHour->isIsOpen() || !$this->checkAppointmentWithinBusinessHours($startTime, $endTime, $businessHour)) {
            $this->addFlash('error', 'Le créneau sélectionné n\'est pas valide ou est en dehors des heures d\'ouverture.');
            return $this->redirectToRoute('app_client_booking_calendar', ['bookingLink' => $bookingLink, 'serviceId' => $serviceId]);
        }

        // Check for overlaps with existing appointments/unavailabilities (excluding the current one if editing)
        $overlappingAppointments = $appointmentRepository->findOverlappingAppointments(
            $professional,
            $startTime, // Pass UTC DateTime objects
            $endTime    // Pass UTC DateTime objects
        );

        if (!empty($overlappingAppointments)) {
            $this->addFlash('error', 'Le créneau sélectionné est déjà pris. Veuillez choisir un autre créneau.');
            return $this->redirectToRoute('app_client_booking_calendar', ['bookingLink' => $bookingLink, 'serviceId' => $serviceId]);
        }

        // Create the new appointment
        $appointment = new Appointment();
        $appointment->setProfessional($professional);
        $appointment->setClient($client);
        $appointment->setStartTime($startTime); // These are now UTC DateTime objects
        $appointment->setEndTime($endTime);     // These are now UTC DateTime objects
        $appointment->setTitle('Rendez-vous pour ' . $client->getFirstName() . ' ' . $client->getLastName());
        $appointment->addService($selectedService); // Add the selected service
        $appointment->setIsPersonalUnavailability(false); // This is a client appointment
        $appointment->setCreatedAt(new \DateTimeImmutable()); // Set creation timestamp

        // === DÉBUT DE L'AJOUT ===
        // Vérifier si le professionnel est le professionnel "primaire" du client
        $isStartingProfessional = $client->getProfessional() === $professional;

        // Vérifier si le professionnel est déjà dans la liste des autres professionnels du client
        $isInHistory = $client->getOtherProfessionals()->contains($professional);

        // Si le professionnel n'est ni le professionnel principal, ni dans l'historique, l'ajouter
        if (!$isStartingProfessional && !$isInHistory) {
            $client->addOtherProfessional($professional);
        }
        // === FIN DE L'AJOUT ===

        // You might want a confirmation form here, but for simplicity, directly persist
        if ($request->isMethod('POST')) {
    try {
        $entityManager->persist($appointment);
        $entityManager->flush();

        // === ENVOI EMAIL AVEC LA MÊME MÉTHODE QUI FONCTIONNE ===
        // Load .env file
        $dotenv = new Dotenv();
        $dotenv->loadEnv(dirname(__DIR__, 2) . '/.env');

        $mailerDsn = $_ENV['MAILER_DSN'] ?? null;
        if (!$mailerDsn) {
            throw new \RuntimeException('MAILER_DSN environment variable is not set in .env');
        }
        
        $transport = Transport::fromDsn($mailerDsn);
        $mailer = new Mailer($transport);

        // EMAIL AU CLIENT
        $emailToClient = (new Email())
            ->from($_ENV['MAILER_FROM_EMAIL'] ?? 'rdvpro@brelect.fr')
            ->to($client->getEmail())
            ->subject(
                'Votre demande de rendez-vous avec ' .
                ($professional->getBusinessName() ?? ($professional->getFirstName() . ' ' . $professional->getLastName()))
            )
            ->html($this->renderView(
                'emails/client_appointment_confirmation.html.twig',
                [
                    'appointment' => $appointment,
                    'professional' => $professional,
                    'client' => $client,
                    'service' => $selectedService,
                    'startTime' => $startTime,
                    'endTime' => $endTime,
                ]
            ));

        if ($professional->getBusinessEmail()) {
            $emailToClient->addReplyTo($professional->getBusinessEmail());
        }

        $mailer->send($emailToClient);
        $this->addFlash('success', 'Votre rendez-vous a été réservé avec succès et un e-mail de confirmation vous a été envoyé !');

        // EMAIL AU PROFESSIONNEL
        if ($professional->getBusinessEmail()) {
            $emailToProfessional = (new Email())
                ->from($_ENV['MAILER_FROM_EMAIL'] ?? 'rdvpro@brelect.fr')
                ->to($professional->getBusinessEmail())
                ->subject(
                    'Nouveau rendez-vous: ' . $client->getFirstName() . ' ' . $client->getLastName() .
                    ' pour ' . $selectedService->getName() .
                    ' le ' . $startTime->format('d/m/Y à H:i')
                )
                ->html($this->renderView(
                    'emails/professional_appointment_notification.html.twig',
                    [
                        'appointment' => $appointment,
                        'professional' => $professional,
                        'client' => $client,
                        'service' => $selectedService,
                        'startTime' => $startTime,
                        'endTime' => $endTime,
                    ]
                ));

            if ($client->getEmail()) {
                $emailToProfessional->replyTo($client->getEmail());
            }

            $mailer->send($emailToProfessional);
        }

        return $this->redirectToRoute('app_client_appointments_index');
        
    } catch (\Exception $e) {
        $this->addFlash('error', 'Une erreur est survenue lors de la réservation : ' . $e->getMessage());
        return $this->redirectToRoute('app_client_booking_calendar', ['bookingLink' => $bookingLink, 'serviceId' => $serviceId]);
    }
}

        // For GET request, display a confirmation page (optional, but good UX)
        return $this->render('client_booking/confirm_booking.html.twig', [
            'professional' => $professional,
            'service' => $selectedService,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'client' => $client,
        ]);
    }

    /**
     * Helper method to check if an appointment falls within business hours.
     * This method assumes $appointmentStart and $appointmentEnd are already in UTC
     * and $businessHour entity times are relative to the professional's local timezone.
     * It's crucial that this logic correctly compares times across timezones.
     */
    private function checkAppointmentWithinBusinessHours(
        \DateTimeInterface $appointmentStart,
        \DateTimeInterface $appointmentEnd,
        \App\Entity\BusinessHours $businessHour
    ): bool {
        // Convert appointment times to the professional's local timezone for comparison
        $parisTimezone = new DateTimeZone('Europe/Paris');
        $apptStartParis = clone $appointmentStart;
        $apptStartParis->setTimezone($parisTimezone);
        $apptEndParis = clone $appointmentEnd;
        $apptEndParis->setTimezone($parisTimezone);

        // Get the date part of the appointment in Paris timezone
        $todayDate = $apptStartParis->format('Y-m-d');

        // Create DateTime objects for business hours, using the date of the appointment
        // and setting them to the professional's local timezone (Europe/Paris)
        $bhStart1 = $businessHour->getStartTime() ? new DateTime($todayDate . ' ' . $businessHour->getStartTime()->format('H:i:s'), $parisTimezone) : null;
        $bhEnd1 = $businessHour->getEndTime() ? new DateTime($todayDate . ' ' . $businessHour->getEndTime()->format('H:i:s'), $parisTimezone) : null;
        $bhStart2 = $businessHour->getStartTime2() ? new DateTime($todayDate . ' ' . $businessHour->getStartTime2()->format('H:i:s'), $parisTimezone) : null;
        $bhEnd2 = $businessHour->getEndTime2() ? new DateTime($todayDate . ' ' . $businessHour->getEndTime2()->format('H:i:s'), $parisTimezone) : null;

        // Convert all times to timestamps for easier comparison
        $apptStartSec = $apptStartParis->getTimestamp();
        $apptEndSec = $apptEndParis->getTimestamp();

        $bhStart1Sec = $bhStart1 ? $bhStart1->getTimestamp() : null;
        $bhEnd1Sec = $bhEnd1 ? $bhEnd1->getTimestamp() : null;
        $bhStart2Sec = $bhStart2 ? $bhStart2->getTimestamp() : null;
        $bhEnd2Sec = $bhEnd2 ? $bhEnd2->getTimestamp() : null;

        // Check if appointment is fully contained within the first time slot
        $inFirstSlot = ($bhStart1Sec && $bhEnd1Sec && $apptStartSec >= $bhStart1Sec && $apptEndSec <= $bhEnd1Sec);

        // Check if appointment is fully contained within the second time slot
        $inSecondSlot = ($bhStart2Sec && $bhEnd2Sec && $apptStartSec >= $bhStart2Sec && $apptEndSec <= $bhEnd2Sec);

        // If there's a second slot, the appointment must be fully within one of the two slots
        if ($bhStart2Sec && $bhEnd2Sec) {
            return $inFirstSlot || $inSecondSlot;
        } else {
            // If there's only one slot (or no second slot defined), check against the first slot
            return $inFirstSlot;
        }
    }
}