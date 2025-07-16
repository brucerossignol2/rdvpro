<?php
// run_reminders.php
// Placé à la racine de votre projet Symfony (ex: /home/brelect/rdvpro-sys/run_reminders.php)

// Définir le chemin absolu vers la racine de votre projet Symfony
// Assurez-vous que ce chemin est correct pour votre installation OVH
$symfonyRoot = '/homez.654/brelect/rdvpro-sys';

// Chemin absolu vers le script bin/console de Symfony
$consolePath = $symfonyRoot . '/bin/console';

// La commande Symfony à exécuter
$command = 'app:send-appointment-reminders';

// Chemin absolu pour le fichier de log
// Le dossier 'var/log' doit exister et être accessible en écriture
$logFile = $symfonyRoot . '/var/log/cron_reminders.log';

// Construire la commande complète à exécuter
// Utilisation de 'exec' pour exécuter la commande en arrière-plan et capturer la sortie
// '>>' pour ajouter à la fin du fichier de log, '2>&1' pour rediriger les erreurs
$fullCommand = "php {$consolePath} {$command} >> {$logFile} 2>&1";

// Exécuter la commande
// Note: exec() ne retourne que la dernière ligne de la sortie.
// Pour capturer toute la sortie, on utilise la redirection dans la commande elle-même.
exec($fullCommand);

// Vous pouvez ajouter un message de journalisation ici si nécessaire,
// mais la sortie de la commande Symfony elle-même sera dans cron_reminders.log
// echo "Commande Symfony exécutée: {$command}\n";

?>
