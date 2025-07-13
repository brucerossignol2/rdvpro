<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    
    public function boot(): void
    {
        parent::boot();

        // Définir le fuseau horaire par défaut de PHP à 'Europe/Paris'.
        // Ceci assure une gestion cohérente des objets DateTime à travers l'application,
        // notamment pour les cas où le fuseau horaire n'est pas explicitement spécifié.
        date_default_timezone_set('Europe/Paris');
    }

}
