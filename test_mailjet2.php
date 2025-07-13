<?php
// test_mailjet2.php
require_once 'vendor/autoload.php';

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Dotenv\Dotenv;

echo "=== Test configuration Mailjet ===\n\n";

// Charger le fichier .env
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

// Récupérer la configuration DSN depuis .env
$mailerDsn = $_ENV['MAILER_DSN'] ?? null;

if (!$mailerDsn) {
    echo "❌ ERREUR: Variable MAILER_DSN non trouvée dans le fichier .env\n";
    echo "Vérifiez que votre fichier .env contient: MAILER_DSN=votre_dsn_mailjet\n";
    exit(1);
}

echo "Configuration utilisée depuis .env:\n";
echo "DSN: " . maskDsn($mailerDsn) . "\n";

try {
    $transport = Transport::fromDsn($mailerDsn);
    $mailer = new Mailer($transport);
    
    $email = (new Email())
        ->from('info@br-net.fr')
        ->to('bruce.rossignol@brelect.fr')
        ->subject('Test Mailjet depuis .env - ' . date('H:i:s'))
        ->html(getEmailContent())
        ->text('Test Mailjet depuis .env - Envoyé le ' . date('Y-m-d H:i:s'));
    
    echo "📧 Envoi en cours...\n";
    $mailer->send($email);
    echo "✅ SUCCÈS - Email envoyé !\n";
    echo "📝 Vérifiez votre boîte de réception (et les spams)\n";
    
} catch (\Exception $e) {
    echo "✗ ÉCHEC - " . $e->getMessage() . "\n";
}

echo "\n=== Configuration actuelle (.env) ===\n";
echo "MAILER_DSN=" . maskDsn($mailerDsn) . "\n";

echo "\n=== Informations système ===\n";
echo "Hostname: " . gethostname() . "\n";
echo "User: " . get_current_user() . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Symfony Mailer: " . (class_exists('Symfony\Component\Mailer\Mailer') ? 'OK' : 'NOK') . "\n";
echo "Dotenv: " . (class_exists('Symfony\Component\Dotenv\Dotenv') ? 'OK' : 'NOK') . "\n";

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
        <h2 style="color: #333;">🎯 Test Mailjet depuis .env</h2>
        <p>Cet email a été envoyé via <strong>Mailjet</strong> en utilisant la configuration du fichier .env</p>
        
        <div style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <h3>📊 Informations de test:</h3>
            <ul>
                <li><strong>Date:</strong> ' . date('Y-m-d H:i:s') . '</li>
                <li><strong>Serveur:</strong> ' . gethostname() . '</li>
                <li><strong>Expéditeur:</strong> info@br-net.fr</li>
                <li><strong>Destinataire:</strong> bruce.rossignol@brelect.fr</li>
                <li><strong>Transport:</strong> Configuration .env</li>
                <li><strong>PHP Version:</strong> ' . phpversion() . '</li>
            </ul>
        </div>
        
        <div style="background-color: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;">
            <p><strong>✅ Succès!</strong></p>
            <p>Si vous recevez cet email, votre configuration Mailjet depuis le fichier .env fonctionne parfaitement!</p>
        </div>
        
        <hr style="margin: 30px 0;">
        <p style="color: #666; font-size: 12px;">
            <em>Email automatique généré par le script de test Mailjet (configuration .env)</em>
        </p>
    </body>
    </html>
    ';
}

echo "\n=== Test terminé ===\n";
?>