#!/bin/bash

set -e  # Stoppe le script en cas d'erreur
APP_DIR="/home/brelect/rdvpro-sys"  # ← Modifie ce chemin selon ton projet

echo "📦 Déploiement de l'application RDVPRO..."

cd "$APP_DIR" || { echo "❌ Le dossier $APP_DIR n'existe pas."; exit 1; }

echo "🔄 Pull du dépôt Git..."
git reset --hard
git clean -fd
git pull origin main

echo "📦 Installation des dépendances PHP sur OVH (composer)..."
composer install --no-dev --optimize-autoloader

echo "🧹 Nettoyage du cache Symfony..."
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

echo "✅ Déploiement terminé avec succès !"
