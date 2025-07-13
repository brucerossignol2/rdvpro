<?php
// src/Command/EmailDiagnosticCommand.php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

#[AsCommand(
    name: 'app:email-diagnostic',
    description: 'Diagnostic complet de la configuration email',
)]
class EmailDiagnosticCommand extends Command
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(
        MailerInterface $mailer,
        LoggerInterface $logger
    ) {
        $this->mailer = $mailer;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('🔍 DIAGNOSTIC EMAIL COMPLET');
        $output->writeln('');

        // Test 1: Vérification de la configuration
        $this->checkConfiguration($output);

        // Test 2: Test avec des headers de debug
        $this->testWithDebugHeaders($output);

        // Test 3: Test vers différentes adresses
        $this->testMultipleDestinations($output);

        // Test 4: Vérification DNS
        $this->checkDNSRecords($output);

        return Command::SUCCESS;
    }

    private function checkConfiguration(OutputInterface $output): void
    {
        $output->writeln('1️⃣ VÉRIFICATION DE LA CONFIGURATION');
        $output->writeln('');

        $mailerDsn = $_ENV['MAILER_DSN'] ?? 'Non défini';
        $output->writeln('📧 DSN: ' . $this->maskDsn($mailerDsn));
        
        // Vérification du serveur
        $serverInfo = parse_url($mailerDsn);
        $output->writeln('🖥️  Serveur SMTP: ' . ($serverInfo['host'] ?? 'Non défini'));
        $output->writeln('🔌 Port: ' . ($serverInfo['port'] ?? 'Non défini'));
        $output->writeln('🌐 IP du serveur: ' . gethostbyname($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $output->writeln('');
    }

    private function testWithDebugHeaders(OutputInterface $output): void
    {
        $output->writeln('2️⃣ TEST AVEC HEADERS DE DEBUG');
        $output->writeln('');

        try {
            $email = (new Email())
                ->from('info@brelect.fr')
                ->to('bruce.rossignol@brelect.fr')
                ->subject('🔍 Test Debug Email - ' . date('Y-m-d H:i:s'))
                ->html($this->getDebugEmailContent())
                ->text('Test Debug Email - ' . date('Y-m-d H:i:s'))
                // Headers de debug
                ->getHeaders()
                ->addTextHeader('X-Debug-Test', 'symfony-mailer')
                ->addTextHeader('X-Server-IP', $_SERVER['SERVER_ADDR'] ?? 'unknown')
                ->addTextHeader('X-Send-Time', date('Y-m-d H:i:s'))
                ->addTextHeader('Return-Path', 'info@brelect.fr');

            $this->mailer->send($email);
            $output->writeln('<info>✅ Email avec headers de debug envoyé</info>');
            
        } catch (\Exception $e) {
            $output->writeln('<error>❌ Erreur: ' . $e->getMessage() . '</error>');
        }
        
        $output->writeln('');
    }

    private function testMultipleDestinations(OutputInterface $output): void
    {
        $output->writeln('3️⃣ TEST VERS DIFFÉRENTES ADRESSES');
        $output->writeln('');

        $testEmails = [
            'bruce.rossignol@brelect.fr' => 'Adresse principale',
            'info@brelect.fr' => 'Même domaine (auto-test)',
            // Ajoutez d'autres adresses de test si nécessaire
        ];

        foreach ($testEmails as $emailAddr => $description) {
            try {
                $email = (new Email())
                    ->from('info@brelect.fr')
                    ->to($emailAddr)
                    ->subject('Test Multi-Destination - ' . date('H:i:s'))
                    ->text('Test vers ' . $description . ' - ' . date('Y-m-d H:i:s'));

                $this->mailer->send($email);
                $output->writeln("<info>✅ {$description} ({$emailAddr}): Envoyé</info>");
                
            } catch (\Exception $e) {
                $output->writeln("<error>❌ {$description} ({$emailAddr}): " . $e->getMessage() . "</error>");
            }
        }
        
        $output->writeln('');
    }

    private function checkDNSRecords(OutputInterface $output): void
    {
        $output->writeln('4️⃣ VÉRIFICATION DES ENREGISTREMENTS DNS');
        $output->writeln('');

        $domain = 'brelect.fr';
        
        // Vérification MX
        $output->writeln("🔍 Enregistrements MX pour {$domain}:");
        $mxRecords = dns_get_record($domain, DNS_MX);
        if ($mxRecords) {
            foreach ($mxRecords as $mx) {
                $output->writeln("  - {$mx['target']} (priorité: {$mx['pri']})");
            }
        } else {
            $output->writeln("  ❌ Aucun enregistrement MX trouvé");
        }
        
        // Vérification SPF
        $output->writeln("🔍 Enregistrement SPF pour {$domain}:");
        $txtRecords = dns_get_record($domain, DNS_TXT);
        $spfFound = false;
        if ($txtRecords) {
            foreach ($txtRecords as $txt) {
                if (strpos($txt['txt'], 'v=spf1') !== false) {
                    $output->writeln("  ✅ SPF: " . $txt['txt']);
                    $spfFound = true;
                }
            }
        }
        if (!$spfFound) {
            $output->writeln("  ⚠️  Aucun enregistrement SPF trouvé");
        }
        
        // Vérification DKIM
        $output->writeln("🔍 Test DKIM pour {$domain}:");
        $dkimRecords = dns_get_record("default._domainkey.{$domain}", DNS_TXT);
        if ($dkimRecords) {
            $output->writeln("  ✅ DKIM configuré");
        } else {
            $output->writeln("  ⚠️  DKIM non trouvé ou non configuré");
        }
        
        $output->writeln('');
        $output->writeln('📋 RECOMMANDATIONS:');
        $output->writeln('');
        $output->writeln('1. Vérifiez que votre serveur IP est autorisé dans SPF');
        $output->writeln('2. Configurez DKIM si pas fait');
        $output->writeln('3. Vérifiez la réputation IP du serveur');
        $output->writeln('4. Contactez le destinataire pour vérifier ses spams');
        $output->writeln('');
    }

    private function getDebugEmailContent(): string
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Debug Email Test</title>
        </head>
        <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #333;">🔍 Email de Debug</h2>
            
            <div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">
                <h3>⚠️ Important</h3>
                <p>Cet email contient des headers de debug pour identifier pourquoi les emails n\'arrivent pas.</p>
            </div>
            
            <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h3>📊 Informations système:</h3>
                <ul>
                    <li><strong>Date d\'envoi:</strong> ' . date('Y-m-d H:i:s') . '</li>
                    <li><strong>Serveur:</strong> ' . gethostname() . '</li>
                    <li><strong>IP serveur:</strong> ' . ($_SERVER['SERVER_ADDR'] ?? 'inconnue') . '</li>
                    <li><strong>Expéditeur:</strong> info@brelect.fr</li>
                    <li><strong>Transport:</strong> OVH SMTP</li>
                </ul>
            </div>
            
            <div style="background-color: #d1ecf1; padding: 15px; border-radius: 5px; border-left: 4px solid #17a2b8;">
                <h3>🔍 Que faire si vous ne recevez pas cet email ?</h3>
                <ol>
                    <li>Vérifiez vos <strong>spams/courriers indésirables</strong></li>
                    <li>Vérifiez l\'adresse email du destinataire</li>
                    <li>Contactez votre administrateur système</li>
                    <li>Vérifiez les enregistrements DNS (SPF, DKIM, DMARC)</li>
                </ol>
            </div>
            
            <hr style="margin: 30px 0;">
            <p style="color: #666; font-size: 12px;">
                <em>Email de diagnostic généré le ' . date('Y-m-d H:i:s') . '</em>
            </p>
        </body>
        </html>
        ';
    }

    private function maskDsn(string $dsn): string
    {
        return preg_replace('/(:\/\/[^:]+:)[^@]+(@)/', '$1****$2', $dsn);
    }
}