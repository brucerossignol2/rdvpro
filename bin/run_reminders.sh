#!/bin/bash

# Chemin absolu vers le répertoire racine de votre projet Symfony
# Assurez-vous que ce chemin est correct pour votre installation OVH
SYMFONY_ROOT="/home/brelect/rdvpro-sys"

# Chemin absolu vers l'interpréteur PHP
# Utilisez "php" si OVH gère automatiquement la version, ou spécifiez le chemin complet si nécessaire (ex: /usr/local/php8.1/bin/php)
PHP_BIN="/usr/local/php8.4/bin/php" # Ou par exemple "/usr/local/php8.1/bin/php"

# Naviguer vers le répertoire du projet Symfony
cd "$SYMFONY_ROOT"

# Exécuter la commande Symfony
# Rediriger la sortie vers un fichier de log pour le débogage
$PHP_BIN bin/console app:send-appointment-reminders >> var/log/cron_reminders.log 2>&1

# Le ">>" ajoute la sortie à la fin du fichier.
# Le "2>&1" redirige les erreurs vers le même fichier de log.
