<?php
// src/Controller/ProfilePasswordController.php
namespace App\Controller;

use App\Entity\User;
use App\Form\ProfilePasswordChangeType; // Nous allons créer ce formulaire
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/profile')]
#[IsGranted('ROLE_USER')] // Assurez-vous que seul un utilisateur authentifié peut accéder
class ProfilePasswordController extends AbstractController
{
    #[Route('/change-password', name: 'app_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Assurez-vous que l'utilisateur connecté est un professionnel (ou un type d'utilisateur géré par cette route)
        // Si vous avez des rôles spécifiques pour les professionnels, vous pouvez affiner cette vérification.
        // Pour l'instant, IsGranted('ROLE_USER') est suffisant si tous les utilisateurs peuvent changer leur mot de passe ici.

        $form = $this->createForm(ProfilePasswordChangeType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('newPassword')->getData();

            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $newPassword
                )
            );
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été modifié avec succès !');

            return $this->redirectToRoute('app_profile_edit', [], Response::HTTP_SEE_OTHER); // Rediriger vers la page de profil principale
        }

        return $this->render('profile/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
