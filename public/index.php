<?php

// DOIT ÊTRE LA PREMIÈRE INSTRUCTION EXÉCUTABLE APRÈS LE TAG PHP
// Ce fichier est responsable d'initialiser l'autoloader de Composer,
// sans lequel aucune autre classe de vos dépendances ne peut être trouvée.
require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// Maintenant, les classes comme Dotenv, Kernel, Request, etc., sont disponibles.
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

// Charge les variables d'environnement (APP_ENV, APP_DEBUG, etc.)
// Cela doit être fait avant d'instancier le Kernel.
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// Retourne une fonction qui instancie et retourne le Kernel Symfony.
return function (array $context) {
    // Les valeurs APP_ENV et APP_DEBUG proviennent du contexte chargé par Dotenv.
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};