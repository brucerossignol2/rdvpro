<?php
// src/Controller/ServiceController.php
namespace App\Controller;

use App\Entity\Service;
use App\Form\ServiceType;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface; // Import SluggerInterface
use Symfony\Component\HttpFoundation\File\Exception\FileException; // Import FileException

#[Route('/service')]
#[IsGranted('ROLE_USER')] // Restrict access to authenticated users
class ServiceController extends AbstractController
{
    #[Route('/', name: 'app_service_index', methods: ['GET'])]
    public function index(ServiceRepository $serviceRepository): Response
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->getUser();

        return $this->render('service/index.html.twig', [
            'services' => $serviceRepository->findAllByProfessional($professional),
        ]);
    }

    #[Route('/new', name: 'app_service_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->getUser();

        $service = new Service();
        $service->setProfessional($professional); // Associate the service with the current professional

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            // this condition is needed because the 'imageFile' field is not required
            // so the PDF file must be processed only when a file is uploaded
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                // Move the file to the directory where brochures are stored
                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    // ... handle exception if something goes wrong during file upload
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'upload de l\'image : ' . $e->getMessage());
                    return $this->render('service/new.html.twig', [
                        'service' => $service,
                        'form' => $form,
                    ]);
                }
                $service->setImage($newFilename);
            }

            $entityManager->persist($service);
            $entityManager->flush();

            $this->addFlash('success', 'Le service a été créé avec succès !');

            return $this->redirectToRoute('app_service_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('service/new.html.twig', [
            'service' => $service,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_service_show', methods: ['GET'])]
    public function show(Service $service): Response
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->getUser();

        // Ensure the service belongs to the logged-in professional
        if ($service->getProfessional() !== $professional) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à voir ce service.');
        }

        return $this->render('service/show.html.twig', [
            'service' => $service,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_service_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Service $service, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->getUser();

        // Ensure the service belongs to the logged-in professional
        if ($service->getProfessional() !== $professional) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier ce service.');
        }

        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                    // Delete old image if it exists
                    if ($service->getImage()) {
                        $oldImagePath = $this->getParameter('images_directory') . '/' . $service->getImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'upload de l\'image : ' . $e->getMessage());
                    return $this->render('service/edit.html.twig', [
                        'service' => $service,
                        'form' => $form,
                    ]);
                }
                $service->setImage($newFilename);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Le service a été mis à jour avec succès !');

            return $this->redirectToRoute('app_service_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('service/edit.html.twig', [
            'service' => $service,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_service_delete', methods: ['POST'])]
    public function delete(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User $professional */
        $professional = $this->getUser();

        // Ensure the service belongs to the logged-in professional
        if ($service->getProfessional() !== $professional) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer ce service.');
        }

        if ($this->isCsrfTokenValid('delete'.$service->getId(), $request->request->get('_token'))) {
            // Delete associated image file
            if ($service->getImage()) {
                $imagePath = $this->getParameter('images_directory') . '/' . $service->getImage();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            $entityManager->remove($service);
            $entityManager->flush();

            $this->addFlash('success', 'Le service a été supprimé avec succès !');
        } else {
            $this->addFlash('error', 'Token CSRF invalide. La suppression n\'a pas pu être effectuée.');
        }

        return $this->redirectToRoute('app_service_index', [], Response::HTTP_SEE_OTHER);
    }
}
