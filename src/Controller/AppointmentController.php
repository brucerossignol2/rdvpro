<?php
// src/Controller/AppointmentController.php
namespace App\Controller;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use App\Service\AppointmentMailerService;
use App\Entity\Appointment;
use App\Entity\User;
use App\Entity\Client;
use App\Form\AppointmentType;
use App\Repository\AppointmentRepository;
use App\Repository\BusinessHoursRepository;
use App\Repository\ServiceRepository;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use DateTime;
use DateTimeZone;
use DateInterval;

#[Route('/appointments')]
#[IsGranted('ROLE_USER')] // Restrict access to authenticated users
class AppointmentController extends AbstractController
{

    private MailerInterface $mailer;
    private CsrfTokenManagerInterface $csrfTokenManager;

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
            $backgroundColor = '#007bff'; // Default primary blue for appointments (will be overridden by status)
            $borderColor = $backgroundColor;
            $textColor = '#ffffff';

            if ($appointment->isIsPersonalUnavailability()) {
                // Indisponibilité : Bleu (primary)
                $backgroundColor = '#0dcaf0'; // Primary Blue
                $borderColor = '#0dcaf0';
            } else {
                // Pour les rendez-vous client, la couleur sera déterminée par le statut en JavaScript
                // Nous passons simplement le statut dans les extendedProps
                switch ($appointment->getStatus()) {
                    case 'confirmed':
                        $backgroundColor = '#28a745'; // Success Green
                        $borderColor = '#28a745';
                        break;
                    case 'cancelled':
                        $backgroundColor = '#6c757d'; // Secondary Gray
                        $borderColor = '#6c757d';
                        break;
                    case 'pending':
                        $backgroundColor = '#ffc107'; // Warning Yellow/Orange
                        $borderColor = '#ffc107';
                        break;
                    default:
                        $backgroundColor = '#007bff'; // Default to primary blue if status is unknown
                        $borderColor = '#007bff';
                        break;
                }
            }

            $events[] = [
                'id' => $appointment->getId(),
                'title' => $appointment->getTitle(),
                'start' => $appointment->getStartTime()->format('Y-m-d\TH:i:s'),
                'end' => $appointment->getEndTime()->format('Y-m-d\TH:i:s'),
                'backgroundColor' => $backgroundColor, // Set color directly based on type/status
                'borderColor' => $borderColor,
                'textColor' => $textColor,
                'extendedProps' => [
                    'description' => $appointment->getDescription(),
                    'isPersonalUnavailability' => $appointment->isIsPersonalUnavailability(),
                    'status' => $appointment->getStatus(), // Pass the status to the frontend
                    'clientId' => $appointment->getClient() ? $appointment->getClient()->getId() : null,
                    'clientName' => $appointment->getClient() ? $appointment->getClient()->getFullName() : null,
                    'services' => array_map(fn($service) => ['id' => $service->getId(), 'name' => $service->getName(), 'duration' => $service->getDuration()], $appointment->getServices()->toArray()),
                    'deleteToken' => $this->csrfTokenManager->getToken('delete' . $appointment->getId())->getValue(),
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

        // --- DÉBUT DE LA LOGIQUE MISE À JOUR POUR initialDate ---
        $now = new DateTime('now', new DateTimeZone('Europe/Paris'));
        $initialDate = $now->format('Y-m-d'); // Par défaut, aujourd'hui

        // Obtenir les heures d'ouverture pour le jour actuel
        $currentDayOfWeek = (int)$now->format('N'); // 1 (lundi) à 7 (dimanche)
        $currentDayBusinessHours = $businessHoursRepository->findOneBy(['professional' => $professional, 'dayOfWeek' => $currentDayOfWeek]);

        $shouldAdvanceToNextDay = false;

        if ($currentDayBusinessHours && $currentDayBusinessHours->isIsOpen()) {
            $latestClosingTimeToday = null;

            // Calculer l'heure de fermeture la plus tardive pour aujourd'hui
            if ($currentDayBusinessHours->getEndTime()) {
                $latestClosingTimeToday = (clone $now)->setTime(
                    (int)$currentDayBusinessHours->getEndTime()->format('H'),
                    (int)$currentDayBusinessHours->getEndTime()->format('i'),
                    (int)$currentDayBusinessHours->getEndTime()->format('s')
                );
            }
            if ($currentDayBusinessHours->getEndTime2()) {
                $closingTime2 = (clone $now)->setTime(
                    (int)$currentDayBusinessHours->getEndTime2()->format('H'),
                    (int)$currentDayBusinessHours->getEndTime2()->format('i'),
                    (int)$currentDayBusinessHours->getEndTime2()->format('s')
                );
                if ($latestClosingTimeToday === null || $closingTime2 > $latestClosingTimeToday) {
                    $latestClosingTimeToday = $closingTime2;
                }
            }

            // Si l'heure actuelle dépasse le seuil (ex: 1 heure avant la fermeture), on avance au jour suivant
            if ($latestClosingTimeToday && $now > (clone $latestClosingTimeToday)->sub(new DateInterval('PT1H'))) {
                $shouldAdvanceToNextDay = true;
            }
        } else {
            // Si aujourd'hui n'est pas un jour ouvré, on doit avancer au jour suivant
            $shouldAdvanceToNextDay = true;
        }

        if ($shouldAdvanceToNextDay) {
            // Trouver le prochain jour ouvré à partir de demain
            for ($i = 1; $i <= 7; $i++) { // Vérifier les 7 prochains jours
                $nextDay = (clone $now)->modify('+' . $i . ' days');
                $nextDayOfWeek = (int)$nextDay->format('N');
                $nextDayBusinessHours = $businessHoursRepository->findOneBy(['professional' => $professional, 'dayOfWeek' => $nextDayOfWeek]);

                if ($nextDayBusinessHours && $nextDayBusinessHours->isIsOpen()) {
                    $initialDate = $nextDay->format('Y-m-d');
                    break; // Le prochain jour ouvré est trouvé
                }
                // Si aucun jour ouvré n'est trouvé dans les 7 prochains jours, initialDate restera la date d'aujourd'hui
                // (celle qui était par défaut au début de la logique)
                if ($i === 7) {
                    $initialDate = $now->format('Y-m-d');
                }
            }
        }
        // --- FIN DE LA LOGIQUE MISE À JOUR POUR initialDate ---

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
               // $this->addFlash('success', 'Le client "' . $client->getFullName() . '" a été créé et sélectionné.');
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
                // Définir le statut sur 'confirmed' si ce n'est PAS une indisponibilité personnelle
                $appointment->setStatus('confirmed'); // AJOUTEZ CETTE LIGNE
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
                        $dotenv->loadEnv(dirname(__DIR__, 2) . '/.env');

                        $mailerDsn = $_ENV['MAILER_DSN'] ?? null;

                        if (!$mailerDsn) {
                            throw new \RuntimeException('MAILER_DSN environment variable is not set in .env');
                        }
                        $transport = Transport::fromDsn($mailerDsn);
                        $mailer = new Mailer($transport);
                        $professionalNameForEmail = $professional->getFirstName() . ' ' . $professional->getLastName();
                        $email = (new Email())
                            ->from($_ENV['MAILER_FROM_EMAIL'] ?? 'rdvpro@brelect.fr')
                            ->to($client->getEmail())
                            ->subject('Confirmation de votre rendez-vous avec ' . $professionalNameForEmail)
                            ->html($this->renderView('emails/appointment_confirmation.html.twig', [
                                'appointment' => $appointment,
                                'client' => $client,
                                'professional' => $professional,
                            ]));
                        // Met répondre au professionnel si le mail existe
                        if ($professional->getBusinessEmail()) { // Assuming businessEmail is the reply-to
                            $email->addReplyTo($professional->getBusinessEmail());
                        }

                        $mailer->send($email);
                        $this->addFlash('success', 'Rendez-vous créé et email de confirmation envoyé au client.');
                    } else {
                        $this->addFlash('warning', 'Rendez-vous créé, mais l\'email de confirmation n\'a pas pu être envoyé (client ou professionnel manquant, ou email manquant).');
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Rendez-vous créé, mais une erreur est survenue lors de l\'envoi de l\'email de confirmation : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('success', 'Indisponibilité personnelle créée avec succès.');
            }
            // === FIN DU CODE POUR L'ENVOI D'EMAIL ===

            return $this->redirectToRoute('app_appointment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('appointment/new.html.twig', [
            'appointment' => $appointment,
            'form' => $form,
            'servicesData' => $this->getServicesDataForTemplate($professional, $serviceRepository),
        ]);
    }

    /**
     * Displays a single appointment.
     */
    #[Route('/{id}', name: 'app_appointment_show', methods: ['GET'])]
    public function show(Appointment $appointment): Response
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->getUser();

        // Security check: ensure the professional can only view their own appointments
        if ($appointment->getProfessional() !== $professional) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour voir ce rendez-vous.');
        }

        return $this->render('appointment/show.html.twig', [
            'appointment' => $appointment,
        ]);
    }

    /**
     * Updates the status of an appointment.
     */
    #[Route('/{id}/status/{status}', name: 'app_appointment_update_status', methods: ['POST'])]
        public function updateStatus(
            Request $request,
            Appointment $appointment,
            string $status,
            EntityManagerInterface $entityManager,
            AppointmentMailerService $appointmentMailerService // Injection du service
        ): JsonResponse {
            // Vérification du CSRF
            if (!$this->isCsrfTokenValid('update_status', $request->request->get('_token'))) {
                return new JsonResponse(['status' => 'error', 'message' => 'Jeton CSRF invalide.'], 400);
            }

            // Optionnel : Valider les statuts permis
            $allowedStatuses = ['pending', 'confirmed', 'cancelled'];
            if (!in_array($status, $allowedStatuses)) {
                return new JsonResponse(['status' => 'error', 'message' => 'Statut invalide.'], 400);
            }

            // Mise à jour et sauvegarde
            $appointment->setStatus($status);
            $entityManager->flush();

            // Récupérer l'utilisateur actuellement authentifié (le professionnel)
            $professional = $this->getUser();

            // Vérifier que $professional est bien une instance de votre entité User
            // et n'est pas une indisponibilité personnelle avant d'envoyer l'email.
            // C'est important car $this->getUser() peut retourner null ou un autre type si non authentifié.
            if ($professional instanceof User && !$appointment->isIsPersonalUnavailability()) {
                try {
                    $appointmentMailerService->sendStatusChangeEmail($appointment, $status, $professional);
                    $this->addFlash('success', 'Le statut du rendez-vous a été mis à jour et un e-mail a été envoyé au client.');
                } catch (\Exception $e) {
                    // Le log de l'erreur est crucial ici pour le débogage si l'envoi de l'e-mail échoue pour d'autres raisons
                    error_log('Erreur lors de l\'envoi de l\'e-mail: ' . $e->getMessage());
                    $this->addFlash('error', 'Le statut du rendez-vous a été mis à jour, mais l\'e-mail n\'a pas pu être envoyé : ' . $e->getMessage());
                }
            } else {
                // Optionnel : Ajouter un flash message si l'e-mail n'est pas envoyé parce que le professionnel n'est pas trouvé
                if (!$professional instanceof User) {
                    $this->addFlash('warning', 'Le statut du rendez-vous a été mis à jour, mais l\'e-mail n\'a pas pu être envoyé car le professionnel n\'a pas été identifié.');
                } else { // Si c'est une indisponibilité personnelle
                    $this->addFlash('success', 'Le statut du rendez-vous a été mis à jour (indisponibilité personnelle).');
                }
            }
            
            return new JsonResponse(['status' => 'success']);
        }
    


    /**
     * Edits an existing appointment or personal unavailability.
     */
    #[Route('/{id}/edit', name: 'app_appointment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Appointment $appointment, EntityManagerInterface $entityManager, BusinessHoursRepository $businessHoursRepository, ServiceRepository $serviceRepository, ClientRepository $clientRepository): Response
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->getUser();

        // Security check: ensure the professional can only edit their own appointments
        if ($appointment->getProfessional() !== $professional) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour modifier ce rendez-vous.');
        }

        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check for overlaps with existing appointments/unavailabilities
            $existingAppointments = $entityManager->getRepository(Appointment::class)->findOverlappingAppointments(
                $professional,
                $appointment->getStartTime(),
                $appointment->getEndTime(),
                $appointment->getId() // Exclude current appointment (since we are editing it)
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

            // === DÉBUT DU CODE POUR L'ENVOI D'EMAIL (si statut change ou infos importantes) ===
            // Pour l'édition, la logique d'envoi d'email peut être plus complexe.
            // Par exemple, n'envoyer un email que si le statut change, ou si les heures/dates changent.
            // Pour l'instant, je laisse la même logique que pour la création (hors indisponibilité personnelle)
            if (!$appointment->isIsPersonalUnavailability()) {
                try {
                    /** @var \App\Entity\Client|null $client */
                    $client = $appointment->getClient();
                    /** @var \App\Entity\User|null $professional */
                    $professional = $this->getUser();
                    if ($client && $client->getEmail() && $professional && $professional->getEmail()) {
                        // Load .env file
                        $dotenv = new Dotenv();
                        $dotenv->loadEnv(dirname(__DIR__, 2) . '/.env');

                        $mailerDsn = $_ENV['MAILER_DSN'] ?? null;

                        if (!$mailerDsn) {
                            throw new \RuntimeException('MAILER_DSN environment variable is not set in .env');
                        }
                        $transport = Transport::fromDsn($mailerDsn);
                        $mailer = new Mailer($transport);
                        $professionalNameForEmail = $professional->getFirstName() . ' ' . $professional->getLastName();
                        $email = (new Email())
                            ->from($_ENV['MAILER_FROM_EMAIL'] ?? 'rdvpro@brelect.fr')
                            ->to($client->getEmail())
                            ->subject('Mise à jour de votre rendez-vous avec ' . $professionalNameForEmail)
                            ->html($this->renderView('emails/appointment_update.html.twig', [
                                'appointment' => $appointment,
                                'client' => $client,
                                'professional' => $professional,
                            ]));
                        // Met répondre au professionnel si le mail existe
                        if ($professional->getBusinessEmail()) { // Assuming businessEmail is the reply-to
                            $email->addReplyTo($professional->getBusinessEmail());
                        }
                        $mailer->send($email);
                        $this->addFlash('success', 'Rendez-vous modifié et email de mise à jour envoyé au client.');
                    } else {
                        $this->addFlash('warning', 'Rendez-vous modifié, mais l\'email de mise à jour n\'a pas pu être envoyé.');
                    }
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Rendez-vous modifié, mais une erreur est survenue lors de l\'envoi de l\'email de mise à jour : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('success', 'Indisponibilité personnelle modifiée avec succès.');
            }
            // === FIN DU CODE POUR L'ENVOI D'EMAIL ===

            return $this->redirectToRoute('app_appointment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('appointment/edit.html.twig', [
            'appointment' => $appointment,
            'form' => $form,
            'servicesData' => $this->getServicesDataForTemplate($professional, $serviceRepository),
        ]);
    }

    /**
     * Handles AJAX requests to delete an appointment.
     */
#[Route('/{id}', name: 'app_appointment_delete', methods: ['POST'])]
public function delete(Request $request, Appointment $appointment, EntityManagerInterface $entityManager): Response
{
    /** @var \App\Entity\User $professional */
    $professional = $this->getUser();

    // Vérifie si le professionnel est autorisé à supprimer ce rendez-vous
    if ($appointment->getProfessional() !== $professional) {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['status' => 'error', 'message' => 'Accès non autorisé.'], Response::HTTP_FORBIDDEN);
        }

        $this->addFlash('error', 'Accès non autorisé.');
        return $this->redirectToRoute('app_appointment_show', ['id' => $appointment->getId()]);
    }

    $csrfToken = $request->request->get('_token');
    if ($this->isCsrfTokenValid('delete' . $appointment->getId(), $csrfToken)) {
        $entityManager->remove($appointment);
        $entityManager->flush();

        $this->addFlash('success', 'Le rendez-vous a été supprimé avec succès.');

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['status' => 'success', 'message' => 'Rendez-vous supprimé avec succès.']);
        }

        return $this->redirectToRoute('app_appointment_index');
    }

    $this->addFlash('error', 'Jeton CSRF invalide.');

    if ($request->isXmlHttpRequest()) {
        return new JsonResponse(['status' => 'error', 'message' => 'Jeton CSRF invalide.'], Response::HTTP_BAD_REQUEST);
    }

    return $this->redirectToRoute('app_appointment_show', ['id' => $appointment->getId()]);
}

    /**
     * Helper method to check if an appointment is within professional's business hours.
     */
    private function isAppointmentWithinBusinessHours(
        \App\Entity\User $professional,
        \DateTimeInterface $appointmentStartTime,
        \DateTimeInterface $appointmentEndTime,
        BusinessHoursRepository $businessHoursRepository
    ): bool {
        $dayOfWeek = (int)$appointmentStartTime->format('N'); // 1 (for Monday) through 7 (for Sunday)
        $businessHour = $businessHoursRepository->findOneBy(['professional' => $professional, 'dayOfWeek' => $dayOfWeek]);

        if (!$businessHour || !$businessHour->isIsOpen()) {
            return false; // No business hours defined or closed for this day
        }

        $apptStartSec = $appointmentStartTime->getTimestamp();
        $apptEndSec = $appointmentEndTime->getTimestamp();

        // Create DateTime objects for today's date with business hours times
        $todayDate = $appointmentStartTime->format('Y-m-d');

        // First time slot
        $bhStart1 = $businessHour->getStartTime() ? new \DateTime($todayDate . ' ' . $businessHour->getStartTime()->format('H:i:s')) : null;
        $bhEnd1 = $businessHour->getEndTime() ? new \DateTime($todayDate . ' ' . $businessHour->getEndTime()->format('H:i:s')) : null;

        // Second time slot
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
                'name' => $service->getName(),
            ];
        }
        return json_encode($servicesData);
    }
}