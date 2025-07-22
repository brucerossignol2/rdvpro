<?php
// src/Controller/ImageController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImageController extends AbstractController
{
    #[Route('/uploads/images/{filename}', name: 'serve_image', requirements: ['filename' => '.+\.(jpg|jpeg|png|gif|webp|svg)$'])]
    public function serveImage(string $filename): Response
    {
        $imagePath = $this->getParameter('images_directory') . '/' . $filename;
        
        // Vérifier que le fichier existe et est dans le bon répertoire
        if (!file_exists($imagePath) || !is_readable($imagePath)) {
            throw new NotFoundHttpException('Image not found');
        }
        
        // Vérifier que le fichier est bien dans le répertoire autorisé (sécurité)
        $realImagePath = realpath($imagePath);
        $realImagesDir = realpath($this->getParameter('images_directory'));
        
        if (!$realImagePath || !str_starts_with($realImagePath, $realImagesDir)) {
            throw new NotFoundHttpException('Image not found');
        }
        
        $response = new BinaryFileResponse($imagePath);
        
        // Définir les headers appropriés
        $mimeType = mime_content_type($imagePath);
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Cache-Control', 'public, max-age=31536000'); // 1 an
        
        return $response;
    }
}