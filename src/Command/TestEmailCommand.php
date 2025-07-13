<?php
// src/Command/TestEmailCommand.php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:test-email',
    description: 'Envoie un e-mail de test via Symfony Mailer pour vérifier la configuration.',
)]
class TestEmailCommand extends Command
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private ParameterBagInterface $params;

    public function __construct(
        MailerInterface $mailer,
        LoggerInterface $logger,
        ParameterBagInterface $params
    ) {
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->params = $params;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🚀 Test d\'envoi d\'email avec debugging...');
        $output->writeln('');

        // Affichage de la configuration
        $this->displayConfiguration($output);

        // Test d'envoi
        $output->writeln('📧 Test d\'envoi d\'email');
        $testResult = $this->testEmailSending($output);

        // Résumé
        $output->writeln('');
        $output->writeln('📊 RÉSUMÉ DU TEST:');
        $output->writeln('- Envoi d\'email: ' . ($testResult ? '✅ SUCCÈS' : '❌ ÉCHEC'));

        if ($testResult) {
            $output->writeln('');
            $output->writeln('<info>🎉 L\'email a été envoyé avec succès !</info>');
            $output->writeln('<info>📝 Vérifiez votre boîte de réception (et les spams)</info>');
            return Command::SUCCESS;
        } else {
            $output->writeln('');
            $output->writeln('<error>❌ L\'envoi a échoué. Vérifiez les logs ci-dessus.</error>');
            return Command::FAILURE;
        }
    }

    private function displayConfiguration(OutputInterface $output): void
    {
        $output->writeln('🔧 CONFIGURATION ACTUELLE:');
        
        // Récupération du DSN (masqué pour la sécurité)
        $mailerDsn = $_ENV['MAILER_DSN'] ?? 'Non défini';
        $maskedDsn = $this->maskDsn($mailerDsn);
        
        $output->writeln('- MAILER_DSN: ' . $maskedDsn);
        $output->writeln('- Environnement: ' . ($_ENV['APP_ENV'] ?? 'Non défini'));
        $output->writeln('');
    }

    private function maskDsn(string $dsn): string
    {
        // Masque le mot de passe dans le DSN pour l'affichage
        return preg_replace('/(:\/\/[^:]+:)[^@]+(@)/', '$1****$2', $dsn);
    }

    private function testEmailSending(OutputInterface $output): bool
    {
        try {
            $output->writeln('📝 Création de l\'email...');
            
            $email = (new Email())
                ->from('info@br-net.fr')
                ->to('bruce.rossignol@brelect.fr')
                ->subject('Test Email Debug - ' . date('Y-m-d H:i:s'))
                ->html($this->getEmailContent())
                ->text('Test Email Debug - Envoyé le ' . date('Y-m-d H:i:s'));

            $output->writeln('✅ Email créé');
            $output->writeln('📧 De: info@br-net.fr');
            $output->writeln('📧 À: bruce.rossignol@brelect.fr');
            $output->writeln('');

            $output->writeln('📤 Envoi en cours...');
            
            // Envoi avec capture des erreurs détaillées
            $this->mailer->send($email);
            
            $output->writeln('<info>✅ Email envoyé avec succès via le transport configuré</info>');
            $output->writeln('<info>⏰ Heure d\'envoi: ' . date('Y-m-d H:i:s') . '</info>');
            
            // Log de succès
            $this->logger->info('Email de test envoyé avec succès', [
                'from' => 'info@br-net.fr',
                'to' => 'bruce.rossignol@brelect.fr',
                'subject' => 'Test Email Debug - ' . date('Y-m-d H:i:s'),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            return true;

        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            $output->writeln('<error>❌ Erreur de transport SMTP: ' . $e->getMessage() . '</error>');
            $this->logDetailedError($e, 'Transport SMTP');
            return false;
            
        } catch (\Symfony\Component\Mime\Exception\InvalidArgumentException $e) {
            $output->writeln('<error>❌ Erreur de format d\'email: ' . $e->getMessage() . '</error>');
            $this->logDetailedError($e, 'Format email');
            return false;
            
        } catch (\Exception $e) {
            $output->writeln('<error>❌ Erreur générale: ' . $e->getMessage() . '</error>');
            $output->writeln('<error>📁 Fichier: ' . $e->getFile() . ':' . $e->getLine() . '</error>');
            $this->logDetailedError($e, 'Erreur générale');
            return false;
        }
    }

    private function getEmailContent(): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Test Email Debug</title>
        </head>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #333;">🎯 Test Email Debug</h2>
            <p>Cet email a été envoyé via <strong>Symfony Mailer</strong> pour tester la configuration.</p>
            
            <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h3>📊 Informations de test:</h3>
                <ul>
                    <li><strong>Date:</strong> ' . date('Y-m-d H:i:s') . '</li>
                    <li><strong>Serveur:</strong> ' . gethostname() . '</li>
                    <li><strong>Expéditeur:</strong> info@br-net.fr</li>
                    <li><strong>Destinataire:</strong> bruce.rossignol@brelect.fr</li>
                    <li><strong>PHP Version:</strong> ' . phpversion() . '</li>
                </ul>
            </div>
            
            <div style="background-color: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;">
                <p><strong>✅ Succès!</strong></p>
                <p>Si vous recevez cet email, votre configuration Symfony Mailer fonctionne parfaitement!</p>
            </div>
            
            <hr style="margin: 30px 0;">
            <p style="color: #666; font-size: 12px;">
                <em>Email automatique généré par la commande app:test-email</em>
            </p>
        </body>
        </html>
        ';
    }

    private function logDetailedError(\Exception $e, string $context): void
    {
        $this->logger->error('Erreur lors du test d\'email', [
            'context' => $context,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}