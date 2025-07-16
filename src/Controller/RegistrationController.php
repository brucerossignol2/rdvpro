<?php
// src/Controller/RegistrationController.php
namespace App\Controller;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;

class RegistrationController extends AbstractController
{
    private VerifyEmailHelperInterface $verifyEmailHelper;
    private LoggerInterface $logger;

    public function __construct(VerifyEmailHelperInterface $verifyEmailHelper, LoggerInterface $logger)
    {
        $this->verifyEmailHelper = $verifyEmailHelper;
        $this->logger = $logger;
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // === PHASE 1: CRÉATION DE L'UTILISATEUR ===
            $userCreated = false;
            
            try {
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
                    // Ensure uniqueness of the slug
                    $originalSlug = $slug;
                    $i = 1;
                    while ($entityManager->getRepository(User::class)->findOneBy(['bookingLink' => $slug])) {
                        $slug = $originalSlug . '-' . $i++;
                    }
                    $user->setBookingLink($slug);
                } else {
                    // Fallback if business name is empty
                    $user->setBookingLink('lien-a-definir-' . uniqid());
                }

                // Persist and flush user - Cette étape DOIT réussir
                $entityManager->persist($user);
                $entityManager->flush();
                
                $userCreated = true;
                $this->logger->info('User successfully created', ['user_id' => $user->getId(), 'email' => $user->getEmail()]);
                
            } catch (\Exception $e) {
                $this->logger->error('Failed to create user', ['error' => $e->getMessage()]);
                $this->addFlash('error', 'Erreur lors de la création du compte. Veuillez réessayer.');
                
                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }

            // === PHASE 2: ENVOI DE L'EMAIL (OPTIONNEL) ===
            // L'utilisateur est créé, maintenant on tente d'envoyer l'email
            if ($userCreated) {
                $this->sendConfirmationEmail($user);
                
                // Redirection vers la page de connexion dans tous les cas
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /**
     * Méthode séparée pour l'envoi d'email de confirmation
     */
    private function sendConfirmationEmail(User $user): void
    {
        try {
            // Generate a signed URL for email confirmation
            $signatureComponents = $this->verifyEmailHelper->generateSignature(
                'app_verify_email',
                $user->getId(),
                $user->getEmail(),
                ['id' => $user->getId()]
            );

            $this->logger->info('Attempting to send confirmation email', ['user_id' => $user->getId()]);

            // Load .env file
            $dotenv = new Dotenv();
            $dotenv->loadEnv(dirname(__DIR__, 2) . '/.env');

            $mailerDsn = $_ENV['MAILER_DSN'] ?? null;
            if (!$mailerDsn) {
                throw new \RuntimeException('MAILER_DSN environment variable is not set in .env');
            }
            
            $transport = Transport::fromDsn($mailerDsn);
            $mailer = new Mailer($transport);

            // Create and send email
            $email = (new Email())
                ->from($_ENV['MAILER_FROM_EMAIL'] ?? 'rdvpro@brelect.fr')
                ->to($user->getEmail())
                ->subject('Confirmation de votre adresse e-mail')
                ->html($this->renderView('registration/confirmation_email.html.twig', [
                    'signedUrl' => $signatureComponents->getSignedUrl(),
                    'user' => $user
                ]));

            // Add reply-to if business email exists
            if ($user->getBusinessEmail()) {
                $email->addReplyTo($user->getBusinessEmail());
            }

            $mailer->send($email);
            
            $this->logger->info('Confirmation email sent successfully', ['user_id' => $user->getId()]);
            $this->addFlash('success', 'Votre compte a été créé avec succès ! Un email de confirmation vient de vous être envoyé.');
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send confirmation email', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->addFlash('warning', 'Votre compte a été créé avec succès, mais l\'email de confirmation n\'a pas pu être envoyé. Vous pouvez vous connecter dès maintenant.');
        }
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY'); 

        try {
            $this->verifyEmailHelper->validateEmailConfirmation(
                $request->getUri(), 
                $this->getUser()->getId(), 
                $this->getUser()->getEmail()
            );

            $this->logger->info('Email verified successfully', ['user_id' => $this->getUser()->getId()]);

        } catch (VerifyEmailExceptionInterface $exception) {
            $this->logger->error('Email verification failed', [
                'user_id' => $this->getUser()->getId(),
                'reason' => $exception->getReason()
            ]);
            
            $this->addFlash('error', $exception->getReason());
            return $this->redirectToRoute('app_register');
        }

        $this->addFlash('success', 'Votre adresse e-mail a été vérifiée avec succès ! Vous pouvez maintenant vous connecter.');
        return $this->redirectToRoute('app_login');
    }
    
    #[Route('/cgu-pro', name: 'app_cgu_pro')]
    public function cguPro(): Response
    {
        return $this->render('legal/terms_pro.html.twig');
    }
}