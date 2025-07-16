<?php
// run_reminders.php
// Placé à la racine de votre projet Symfony (ex: /home/brelect/rdvpro-sys/run_reminders.php)

// Définir le chemin absolu vers la racine de votre projet Symfony
// Assurez-vous que ce chemin est correct pour votre installation OVH
$symfonyRoot = '/homez.654/brelect/rdvpro-sys';

// Chemin absolu vers l'interpréteur PHP CLI à utiliser pour exécuter bin/console
// IMPORTANT : Remplacez 'php8.4' par la version exacte de PHP que vous voulez pour Symfony
// (par exemple, 'php8.2', 'php8.3', etc., selon ce qui est configuré pour votre hébergement)
$phpCliPath = '/usr/local/php8.4/bin/php';

// Chemin absolu vers le script bin/console de Symfony
$consolePath = $symfonyRoot . '/bin/console';

// La commande Symfony à exécuter
// Ajout de --env=prod pour s'assurer que la commande s'exécute en environnement de production
// Ajout de --no-interaction (-n) pour éviter toute invite interactive
$command = 'app:send-appointment-reminders --env=prod --no-interaction';

// Chemin absolu pour le fichier de log
// Le dossier 'var/log' doit exister et être accessible en écriture par l'utilisateur du Cron
$logFile = $symfonyRoot . '/var/log/cron_reminders.log';

// Construire la commande complète à exécuter
// Utilisation de 'exec' pour exécuter la commande et capturer la sortie
// '>>' pour ajouter à la fin du fichier de log, '2>&1' pour rediriger les erreurs (stdout et stderr)
$fullCommand = "{$phpCliPath} {$consolePath} {$command} >> {$logFile} 2>&1";

// Exécuter la commande
// La sortie de la commande Symfony (y compris les erreurs) sera redirigée vers $logFile
exec($fullCommand);

// Vous pouvez ajouter un message de journalisation ici si nécessaire,
// mais la sortie détaillée de la commande Symfony elle-même sera dans cron_reminders.log
// file_put_contents($logFile, "Script run_reminders.php terminé à " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

?>
