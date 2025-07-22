<?php
// test-image.php - À placer dans public/ et accéder via https://votre-site.com/test-image.php

$uploadsDir = __DIR__ . '/uploads/images/';
$webPath = '/uploads/images/';

echo "<h1>Test d'accès aux images</h1>";

// Vérifier si le répertoire existe
if (is_dir($uploadsDir)) {
    echo "<p>✅ Répertoire uploads/images existe</p>";
    
    // Lister les fichiers
    $files = scandir($uploadsDir);
    $imageFiles = array_filter($files, function($file) {
        return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file);
    });
    
    if (empty($imageFiles)) {
        echo "<p>❌ Aucune image trouvée dans le répertoire</p>";
    } else {
        echo "<h2>Images trouvées :</h2>";
        foreach ($imageFiles as $file) {
            $fullPath = $uploadsDir . $file;
            $webUrl = $webPath . $file;
            $size = filesize($fullPath);
            $readable = is_readable($fullPath) ? '✅' : '❌';
            
            echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 5px;'>";
            echo "<strong>Fichier :</strong> $file<br>";
            echo "<strong>Taille :</strong> " . number_format($size) . " octets<br>";
            echo "<strong>Lisible :</strong> $readable<br>";
            echo "<strong>URL web :</strong> <a href='$webUrl' target='_blank'>$webUrl</a><br>";
            
            // Test direct de l'image
            echo "<br><strong>Test d'affichage :</strong><br>";
            echo "<img src='$webUrl' style='max-width: 200px; max-height: 150px; border: 1px solid red;' onerror='this.style.display=\"none\"; this.nextSibling.style.display=\"block\";'>";
            echo "<div style='display:none; color:red;'>❌ Impossible de charger l'image</div>";
            echo "</div>";
        }
    }
} else {
    echo "<p>❌ Répertoire uploads/images n'existe pas</p>";
}

// Test des permissions
echo "<h2>Test des permissions :</h2>";
$testFile = $uploadsDir . 'test-permission.txt';
if (file_put_contents($testFile, 'test') !== false) {
    echo "<p>✅ Écriture possible</p>";
    unlink($testFile);
} else {
    echo "<p>❌ Écriture impossible</p>";
}
?>