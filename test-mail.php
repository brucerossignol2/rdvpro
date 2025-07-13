<?php

require 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;

// Charge les variables d'environnement depuis .env
$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

// Lit la variable MAILER_DSN
$dsn = $_ENV['MAILER_DSN'] ?? null;

if (!$dsn) {
    die("MAILER_DSN n'est pas défini dans le fichier .env\n");
}

$transport = Transport::fromDsn($dsn);
$mailer = new Mailer($transport);

$email = (new Email())
    ->from('info@br-net.fr')
    ->to('bruce.rossignol@wanadoo.fr')
    ->subject('Test depuis script')
    ->html('<p>Ceci est un test manuel</p>');

$mailer->send($email);

echo "Mail envoyé\n";
