<?php

// Chemin vers l'autoload_runtime.php de Symfony
require_once __DIR__.'/vendor/autoload_runtime.php';

use Symfony\Component\Dotenv\Dotenv;

// Charge le fichier .env
(new Dotenv())->bootEnv(__DIR__.'/.env');

// Affiche la valeur de MAILER_DSN lue
echo "Valeur de MAILER_DSN via getenv() : " . getenv('MAILER_DSN') . PHP_EOL;

// Affiche la valeur de MAILER_DSN lue via $_ENV (si .env l'ajoute aussi à $_ENV)
echo "Valeur de MAILER_DSN via \$_ENV : " . ($_ENV['MAILER_DSN'] ?? 'Non défini dans $_ENV') . PHP_EOL;

?>