<?php
// src/Controller/PresentationController.php
namespace App\Controller;

use App\Form\PresentationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Route('/profile/presentation')]
#[IsGranted('ROLE_USER')]
class PresentationController extends AbstractController
{
    #[Route('/', name: 'app_profile_presentation', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $form = $this->createForm(PresentationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $presentationImageFile */
            $presentationImageFile = $form->get('presentationImageFile')->getData();
            /** @var UploadedFile $presentationLogoFile */
            $presentationLogoFile = $form->get('presentationLogoFile')->getData(); // Récupérer le nouveau fichier de logo

            if ($presentationImageFile) {
                $originalFilename = pathinfo($presentationImageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$presentationImageFile->guessExtension();

                try {
                    $presentationImageFile->move(
                        $this->getParameter('images_directory'), // Assurez-vous que ce paramètre est configuré dans services.yaml
                        $newFilename
                    );
                    // Supprimer l'ancienne image si elle existe
                    if ($user->getPresentationImage()) {
                        $oldImagePath = $this->getParameter('images_directory') . '/' . $user->getPresentationImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'upload de l\'image de présentation : ' . $e->getMessage());
                    return $this->render('profile/presentation.html.twig', [
                        'presentationForm' => $form->createView(),
                        'user' => $user,
                    ]);
                }
                $user->setPresentationImage($newFilename);
            }

            if ($presentationLogoFile) { // Gérer le téléchargement du fichier de logo
                $originalFilename = pathinfo($presentationLogoFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$presentationLogoFile->guessExtension();

                try {
                    $presentationLogoFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    // Supprimer l'ancien logo si elle existe
                    if ($user->getPresentationLogo()) {
                        $oldLogoPath = $this->getParameter('images_directory') . '/' . $user->getPresentationLogo();
                        if (file_exists($oldLogoPath)) {
                            unlink($oldLogoPath);
                        }
                    }
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'upload du logo : ' . $e->getMessage());
                    return $this->render('profile/presentation.html.twig', [
                        'presentationForm' => $form->createView(),
                        'user' => $user,
                    ]);
                }
                $user->setPresentationLogo($newFilename); // Définir le nouveau nom de fichier du logo
            }

            $entityManager->flush();

            $this->addFlash('success', 'Votre présentation a été mise à jour avec succès !');

            return $this->redirectToRoute('app_profile_presentation', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('profile/presentation.html.twig', [
            'presentationForm' => $form->createView(),
            'user' => $user, // Passer l'objet user pour afficher l'image existante
        ]);
    }
}
