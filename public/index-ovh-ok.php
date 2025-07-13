<?php

$isProductionServer = ($_SERVER['HTTP_HOST'] === 'rdvpro.brelect.fr');

if ($isProductionServer) {
    require_once dirname(__DIR__).'/rdvpro-sys/vendor/autoload_runtime.php';
} else {
    // Si ce n'est pas le serveur de production, on considère que c'est l'environnement local
    require_once dirname(__DIR__).'/vendor/autoload_runtime.php';
}

// pour le local
// require_once dirname(__DIR__).'/vendor/autoload_runtime.php';
// pour le serveur OVH
//require_once dirname(__DIR__).'/rdvpro-sys/vendor/autoload_runtime.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};

