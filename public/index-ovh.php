<?php


    // Pour le serveur de production, les chemins doivent inclure '/rdvpro-sys'
    require_once dirname(__DIR__).'/rdvpro-sys/vendor/autoload_runtime.php';
    
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug; // Peut être géré par le runtime, ou retiré si non utilisé
use Symfony\Component\HttpFoundation\Request; // Généralement utilisé plus bas dans le fichier

// AJOUTEZ CETTE LIGNE pour charger le .env sur le serveur de production
(new Dotenv())->bootEnv(dirname(__DIR__).'/rdvpro-sys/.env');

// Le code pour le Kernel et la Request
//$kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
//$request = Request::createFromGlobals(); // Cette ligne manquait aussi

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
