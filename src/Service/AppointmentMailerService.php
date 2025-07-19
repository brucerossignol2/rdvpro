<?php

namespace App\Service;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use App\Entity\Appointment;
use App\Entity\User;
use Symfony\Component\Mailer\MailerInterface;

class AppointmentMailerService
{
    private $mailer;
    private $twig;

    public function __construct(MailerInterface $mailer, Environment $twig)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
    }

    /**
     * Envoie un e-mail au client lors d'un changement de statut de rendez-vous.
     *
     * @param Appointment $appointment Le rendez-vous concerné.
     * @param string $newStatus Le nouveau statut du rendez-vous ('confirmed', 'cancelled', 'pending').
     * @param User $professional Le professionnel associé au rendez-vous.
     */
    public function sendStatusChangeEmail(Appointment $appointment, string $newStatus, User $professional): void
    {
        $client = $appointment->getClient();
        if (!$client || !$client->getEmail()) {
            // Gérer le cas où le client ou son e-mail est manquant
            // Vous pourriez logger une erreur ici
            return;
        }

        // Charger le fichier .env si ce n'est pas déjà fait (utile pour les tests ou si non chargé globalement)
        // En production, Symfony gère le chargement de .env automatiquement.
        // Cette ligne est plus pour s'assurer que les variables sont disponibles en dehors du contexte de l'application web.
        $dotenv = new Dotenv();
        $dotenv->loadEnv(dirname(__DIR__, 2) . '/.env');

        $mailerDsn = $_ENV['MAILER_DSN'] ?? null;
        
        if (!$mailerDsn) {
            throw new \RuntimeException('MAILER_DSN environment variable is not set in .env');
        }

        // Utilisation de Symfony\Component\Mailer\Mailer avec le transport
        $transport = Transport::fromDsn($mailerDsn);
        $mailer = new Mailer($transport);

        $subject = '';
        $template = '';
        $professionalNameForEmail = $professional->getFirstName() . ' ' . $professional->getLastName();

        switch ($newStatus) {
            case 'confirmed':
                $subject = 'Confirmation de votre rendez-vous avec ' . $professionalNameForEmail;
                $template = 'emails/appointment_statut_confirmed.html.twig';
                break;
            case 'cancelled':
                $subject = 'Annulation de votre rendez-vous avec ' . $professionalNameForEmail;
                $template = 'emails/appointment_statut_cancelled.html.twig';
                break;
            case 'pending':
                $subject = 'Mise à jour du statut de votre rendez-vous avec ' . $professionalNameForEmail;
                $template = 'emails/appointment_statut_pending.html.twig';
                break;
            default:
                // Si un statut inconnu est passé, ou si c'est une indisponibilité personnelle, ne pas envoyer d'e-mail
                return;
        }

        $email = (new Email())
            ->from($_ENV['MAILER_FROM_EMAIL'] ?? 'rdvpro@brelect.fr')
            ->to($client->getEmail())
            ->subject($subject)
            ->html($this->twig->render($template, [
                'appointment' => $appointment,
                'client' => $client,
                'professional' => $professional,
            ]));

        // Ajout de l'adresse de réponse si le professionnel a un businessEmail
        if ($professional->getBusinessEmail()) {
            $email->addReplyTo($professional->getBusinessEmail());
        }

        $mailer->send($email);
    }
}