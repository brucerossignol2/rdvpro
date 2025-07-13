<?php
// src/Controller/ClientProfileController.php
namespace App\Controller;

use App\Entity\Client;
use App\Entity\User; // Import User entity if it's used elsewhere or for type checking
use App\Form\ClientProfileType;
use App\Form\ClientPasswordChangeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException; // Import AccessDeniedException

#[Route('/my-profile')] // Changed route prefix from '/client/profile' to '/my-profile'
#[IsGranted('ROLE_CLIENT')] // Only clients can access their profile
class ClientProfileController extends AbstractController
{
    #[Route('/', name: 'app_client_profile_show', methods: ['GET'])]
    public function show(): Response
    {
        $user = $this->getUser();

        // Ensure the logged-in user is an instance of Client
        if (!$user instanceof Client) {
            // This should ideally not happen if IsGranted('ROLE_CLIENT') works correctly
            // but it's a safeguard. If a professional somehow gets here, deny access.
            throw new AccessDeniedException('Vous n\'avez pas les droits pour accéder à cette page.');
        }

        /** @var Client $client */
        $client = $user; // Now we are sure $client is a Client instance

        return $this->render('client_profile/show.html.twig', [
            'client' => $client,
        ]);
    }

    #[Route('/edit', name: 'app_client_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Client) {
            throw new AccessDeniedException('Vous n\'avez pas les droits pour accéder à cette page.');
        }

        /** @var Client $client */
        $client = $user;

        $form = $this->createForm(ClientProfileType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Votre profil a été mis à jour avec succès !');

            return $this->redirectToRoute('app_client_profile_show', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('client_profile/edit.html.twig', [
            'client' => $client,
            'form' => $form,
        ]);
    }

    #[Route('/change-password', name: 'app_client_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user instanceof Client) {
            throw new AccessDeniedException('Vous n\'avez pas les droits pour accéder à cette page.');
        }

        /** @var Client $client */
        $client = $user;

        $form = $this->createForm(ClientPasswordChangeType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('newPassword')->getData();

            $client->setPassword(
                $userPasswordHasher->hashPassword(
                    $client,
                    $newPassword
                )
            );
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès !');

            return $this->redirectToRoute('app_client_profile_show', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('client_profile/change_password.html.twig', [
            'form' => $form,
        ]);
    }
}
