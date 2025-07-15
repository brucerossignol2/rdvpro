<?php
// src/Controller/RegistrationController.php
namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Symfony\Component\String\Slugger\SluggerInterface; // Import SluggerInterface

class RegistrationController extends AbstractController
{
    private VerifyEmailHelperInterface $verifyEmailHelper;

    public function __construct(VerifyEmailHelperInterface $verifyEmailHelper)
    {
        $this->verifyEmailHelper = $verifyEmailHelper;
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        SluggerInterface $slugger // Inject SluggerInterface
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Encode the plain password
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Set default roles
            $user->setRoles(['ROLE_USER', 'ROLE_PROFESSIONAL']);

            // Generate booking link from business name
            $businessName = $user->getBusinessName();
            if ($businessName) {
                $slug = $slugger->slug($businessName)->lower();
                // Ensure uniqueness of the slug, e.g., by appending a number if it already exists
                $originalSlug = $slug;
                $i = 1;
                while ($entityManager->getRepository(User::class)->findOneBy(['bookingLink' => $slug])) {
                    $slug = $originalSlug . '-' . $i++;
                }
                $user->setBookingLink($slug);
            } else {
                // Fallback if business name is empty (should not happen with NotBlank constraint)
                $user->setBookingLink('lien-a-definir-' . uniqid());
            }


            $entityManager->persist($user);
            $entityManager->flush();

            // Generate a signed URL for email confirmation
            $signatureComponents = $this->verifyEmailHelper->generateSignature(
                'app_verify_email', // This is the route name for email verification
                $user->getId(),
                $user->getEmail(),
                ['id' => $user->getId()] // Optional: pass an extra parameter
            );

            // Send email
            $email = (new Email())
                ->from($_ENV['MAILER_FROM_EMAIL'] ?? 'rdvpro@brelect.fr') // Replace with your sender email
                ->to($user->getEmail())
                ->subject('Confirmation de votre adresse e-mail')
                ->html($this->renderView('registration/confirmation_email.html.twig', [
                    'signedUrl' => $signatureComponents->getSignedUrl(),
                    'user' => $user
                ]));
                if ($professional->getEmail()) {
                    $emailMessage->addReplyTo($professional->getEmail());
                }
            try {
                $mailer->send($email);
                $this->addFlash('success', 'Votre compte a été créé ! Un email de confirmation vient de vous être envoyé.');
            } catch (\Exception $e) {
                // Log the error for debugging, but don't prevent user creation
                $this->addFlash('error', 'Votre compte a été créé, mais l\'e-mail de confirmation n\'a pas pu être envoyé. Veuillez vérifier votre configuration de messagerie. Erreur: ' . $e->getMessage());
                // You might want to log $e->getMessage() to a file or a service like Sentry
            }
            
            return $this->redirectToRoute('app_login'); // Redirect to login page after registration
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request): Response
    {
        // The user must be logged in to verify their email, so we authenticate them first.
        // This is a common pattern for email verification after registration.
        // If the user isn't logged in, they will be redirected to the login page.
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY'); 

        // Validate email confirmation link
        try {
            // The method validateEmailConfirmation expects the user object, not just the ID and email
            // It also expects the full URI from the request
            $this->verifyEmailHelper->validateEmailConfirmation($request->getUri(), $this->getUser()->getId(), $this->getUser()->getEmail());

            // Mark user as verified (you might add a 'isVerified' field to your User entity)
            // For example: $this->getUser()->setIsVerified(true);
            // $entityManager->flush(); // If you add an isVerified field

        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('error', $exception->getReason());
            return $this->redirectToRoute('app_register'); // Or another appropriate route
        }

        $this->addFlash('success', 'Votre adresse e-mail a été vérifiée avec succès ! Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_login'); // Redirect to login page after successful verification
    }
    #[Route('/cgu-pro', name: 'app_cgu_pro')]
    public function cguPro(): Response
    {
        return $this->render('legal/terms_pro.html.twig');
    }

}
