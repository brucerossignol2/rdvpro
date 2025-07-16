<?php
// src/Command/SendAppointmentRemindersCommand.php

namespace App\Command;

use App\Repository\AppointmentRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Service\Attribute\Required; // For Symfony 5.3+
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController; // To use renderView

// For rendering Twig templates in a command, you might need to extend AbstractController
// or inject the Twig environment. Extending AbstractController is simpler for quick setup.
// If you encounter issues with renderView, you might need to inject Twig directly:
// use Twig\Environment;
// private Environment $twig;
// public function __construct(..., Environment $twig) { $this->twig = $twig; parent::__construct(); }
// $this->twig->render(...)

#[AsCommand(
    name: 'app:send-appointment-reminders',
    description: 'Envoi un rappel de rendez-vous 24 heures avant.',
)]
class SendAppointmentRemindersCommand extends Command
{
    private AppointmentRepository $appointmentRepository;
    private MailerInterface $mailer;
    private \Twig\Environment $twig; // Inject Twig Environment for rendering templates

    // Use constructor injection for dependencies
    public function __construct(AppointmentRepository $appointmentRepository, MailerInterface $mailer, \Twig\Environment $twig)
    {
        parent::__construct();
        $this->appointmentRepository = $appointmentRepository;
        $this->mailer = $mailer;
        $this->twig = $twig;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Checking for appointments due for reminders...');

        // Define the time window for reminders: 24 hours from now
        // We look for appointments starting between 23 hours and 25 hours from now
        // to catch anything that might have been slightly off due to execution time.
        $now = new \DateTimeImmutable();
        $startWindow = $now->add(new \DateInterval('PT23H')); // 23 hours from now
        $endWindow = $now->add(new \DateInterval('PT25H'));   // 25 hours from now

        $appointments = $this->appointmentRepository->findAppointmentsDueForReminder($startWindow, $endWindow);

        if (empty($appointments)) {
            $output->writeln('No appointments found for reminders in the next 24 hours.');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('Found %d appointments due for reminders.', count($appointments)));

        foreach ($appointments as $appointment) {
            $client = $appointment->getClient();
            $professional = $appointment->getProfessional();

            // Ensure we have a client, a professional, and their emails
            if ($client && $client->getEmail() && $professional && $professional->getEmail()) {
                try {
                    // Render the email HTML content using Twig
                    $emailHtml = $this->twig->render(
                        'emails/appointment_rappel.html.twig',
                        [
                            'appointment' => $appointment,
                            'professional' => $professional,
                            'client' => $client,
                        ]
                    );

                    $email = (new Email())
                        ->from(getenv('MAILER_FROM_EMAIL') ?: 'rdvpro@brelect.fr') // Use getenv for environment variables
                        ->to($client->getEmail())
                        ->subject('Rappel de votre rendez-vous avec ' . $professional->getFirstName() . ' ' . $professional->getLastName())
                        ->html($emailHtml);

                    // Met répondre au professionnel si le mail existe
                    if ($professional->getBusinessEmail()) { // Assuming businessEmail is the reply-to
                        $email->addReplyTo($professional->getBusinessEmail());
                    }

                    $this->mailer->send($email);
                    $output->writeln(sprintf('Reminder sent for appointment ID %d to client %s.', $appointment->getId(), $client->getEmail()));

                    // Optionally, you might want to mark the appointment as "reminder sent"
                    // to prevent sending multiple reminders for the same appointment.
                    // This would require adding a new field to your Appointment entity (e.g., `reminderSentAt`).
                    // For example:
                    // $appointment->setReminderSentAt(new \DateTimeImmutable());
                    // $this->appointmentRepository->getEntityManager()->flush();

                } catch (\Exception $e) {
                    $output->writeln(sprintf('Failed to send reminder for appointment ID %d: %s', $appointment->getId(), $e->getMessage()));
                }
            } else {
                $output->writeln(sprintf('Skipping reminder for appointment ID %d: Missing client, professional, or email.', $appointment->getId()));
            }
        }

        $output->writeln('Reminder sending process completed.');

        return Command::SUCCESS;
    }
}
