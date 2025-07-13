<?php
// test_mailjet.php
require_once 'vendor/autoload.php';

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

echo "=== Test configuration Mailjet ===\n\n";

// Configuration Mailjet qui fonctionne
$mailjetDsn = 'mailjet+api://7d4e68b1fa41c2c329dad853ddbb5ee2:f34dcf7a466393f87f92adb3372f156f@default';

// Configurations alternatives à tester
$configurations = [
    'mailjet+api://7d4e68b1fa41c2c329dad853ddbb5ee2:f34dcf7a466393f87f92adb3372f156f@default' => 'Mailjet API (configuration qui fonctionne)',
    'mailjet+smtp://7d4e68b1fa41c2c329dad853ddbb5ee2:f34dcf7a466393f87f92adb3372f156f@in-v3.mailjet.com:587' => 'Mailjet SMTP',
    'mailjet+smtp://7d4e68b1fa41c2c329dad853ddbb5ee2:f34dcf7a466393f87f92adb3372f156f@in-v3.mailjet.com:25' => 'Mailjet SMTP port 25',
];

foreach ($configurations as $dsn => $description) {
    echo "Test: $description\n";
    echo "DSN: " . maskDsn($dsn) . "\n";
    
    try {
        $transport = Transport::fromDsn($dsn);
        $mailer = new Mailer($transport);
        
        $email = (new Email())
            ->from('info@br-net.fr')
            ->to('bruce.rossignol@brelect.fr')
            ->subject('Test Mailjet- OK - nom du test : ' . $description)
            ->html(getEmailContent())
            ->text('Test Mailjet - Test : ' . $description);
        
        echo "📧 Envoi en cours...\n";
        $mailer->send($email);
        echo "✅ SUCCÈS - Email envoyé !\n";
        echo "📝 Vérifiez votre boîte de réception (et les spams)\n";
        
    } catch (\Exception $e) {
        echo "✗ ÉCHEC - " . $e->getMessage() . "\n";
    }
    echo "---\n";
}

echo "\n=== Configuration recommandée pour .env ===\n";
echo "MAILER_DSN=" . $mailjetDsn . "\n";

echo "\n=== Informations système ===\n";
echo "Hostname: " . gethostname() . "\n";
echo "User: " . get_current_user() . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Symfony Mailer: " . (class_exists('Symfony\Component\Mailer\Mailer') ? 'OK' : 'NOK') . "\n";

// Fonction pour masquer les clés dans l'affichage
function maskDsn(string $dsn): string
{
    return preg_replace('/(:\/\/[^:]+:)[^@]+(@)/', '$1****$2', $dsn);
}

// Contenu HTML de l'email de test
function getEmailContent(): string
{
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Test Mailjet</title>
    </head>
    <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <h2 style="color: #333;">🎯 Test Mailjet</h2>
        <p>Cet email a été envoyé via <strong>Mailjet</strong> pour tester la configuration.</p>
        
        <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3>📊 Informations de test:</h3>
            <ul>
                <li><strong>Date:</strong> ' . date('Y-m-d H:i:s') . '</li>
                <li><strong>Serveur:</strong> ' . gethostname() . '</li>
                <li><strong>Expéditeur:</strong> info@br-net.fr</li>
                <li><strong>Destinataire:</strong> bruce.rossignol@brelect.fr</li>
                <li><strong>Transport:</strong> Mailjet API</li>
                <li><strong>PHP Version:</strong> ' . phpversion() . '</li>
            </ul>
        </div>
        
        <div style="background-color: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;">
            <p><strong>✅ Succès!</strong></p>
            <p>Si vous recevez cet email, votre configuration Mailjet fonctionne parfaitement!</p>
        </div>
        
        <hr style="margin: 30px 0;">
        <p style="color: #666; font-size: 12px;">
            <em>Email automatique généré par le script de test Mailjet</em>
        </p>
    </body>
    </html>
    ';
}

echo "\n=== Test terminé ===\n";
?>