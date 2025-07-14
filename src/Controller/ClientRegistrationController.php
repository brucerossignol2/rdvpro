<?php
// src/Controller/ClientRegistrationController.php
namespace App\Controller;

use App\Entity\Client; // Import Client entity
use App\Entity\User; // Import User entity for professional
use App\Form\ClientRegistrationFormType;
use App\Repository\UserRepository; // To find the professional by bookingLink
use App\Repository\ClientRepository; // Import ClientRepository for email check
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
use Symfony\Component\Dotenv\Dotenv; // Import Dotenv
use Symfony\Component\Mailer\Transport; // Import Transport
use Symfony\Component\Mailer\Mailer; // Import Mailer

class ClientRegistrationController extends AbstractController
{
    private VerifyEmailHelperInterface $verifyEmailHelper;

    public function __construct(VerifyEmailHelperInterface $verifyEmailHelper)
    {
        $this->verifyEmailHelper = $verifyEmailHelper;
    }
#[Route('/{bookingLink}/register/client', name: 'app_client_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer, // Keep MailerInterface for type hinting, but we'll instantiate our own Mailer
        UserRepository $userRepository, // Inject UserRepository
        ClientRepository $clientRepository // Inject ClientRepository
    ): Response {
        $professional = $userRepository->findOneBy(['bookingLink' => $request->get('bookingLink')]);

        if (!$professional) {
            throw $this->createNotFoundException('Lien de réservation invalide.');
        }

        $client = new Client();
        $client->setProfessional($professional); // Associate client with the professional
        $client->setCreatedAt(new \DateTimeImmutable()); // Set creation timestamp
        $client->setRoles(['ROLE_CLIENT']); // Assign ROLE_CLIENT

        $form = $this->createForm(ClientRegistrationFormType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();

            // 1. Check if email already exists in Client entity for this professional
            $existingClient = $clientRepository->findOneByEmailAndProfessional($email, $professional);
            if ($existingClient) {
                $this->addFlash('error', 'Cette adresse e-mail est déjà utilisée par un autre de vos clients.');
                return $this->render('client_registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                    'professional' => $professional,
                ]);
            }

            // 2. Check if email already exists in User (Professional) entity
            $existingProfessional = $userRepository->findOneBy(['email' => $email]);
            if ($existingProfessional) {
                $this->addFlash('error', 'Cette adresse e-mail est déjà utilisée par un professionnel. Veuillez utiliser une autre adresse.');
                return $this->render('client_registration/register.html.twig', [
                    'registrationForm' => $form->createView(),
                    'professional' => $professional,
                ]);
            }

            // Encode the plain password
            $client->setPassword(
                $userPasswordHasher->hashPassword(
                    $client,
                    $form->get('plainPassword')->getData()
                )
            );

            // Set isVerified to false initially, to be verified via email
            $client->setIsVerified(false);

            $entityManager->persist($client);
            $entityManager->flush();

            // Generate a signed URL for email confirmation
            $signatureComponents = $this->verifyEmailHelper->generateSignature(
                'app_verify_client_email', // This is the route name for client email verification
                $client->getId(),
                $client->getEmail(),
                ['id' => $client->getId()] // Optional: pass an extra parameter
            );

            // Récupérer la date d'expiration du lien signé
            $signatureExpiresAt = $signatureComponents->getExpiresAt();

            // Send email
            try {
                // Load .env file
                $dotenv = new Dotenv();
                // Adjust path as needed for your project structure.
                $dotenv->loadEnv(dirname(__DIR__, 2) . '/.env');

                $mailerDsn = $_ENV['MAILER_DSN'] ?? null;

                if (!$mailerDsn) {
                    throw new \RuntimeException('MAILER_DSN environment variable is not set in .env');
                }

                $transport = Transport::fromDsn($mailerDsn);
                $customMailer = new Mailer($transport); // Use a different variable name to avoid conflict with injected $mailer

                $emailMessage = (new Email())
                    ->from($_ENV['MAILER_FROM_EMAIL'] ?? 'rdvpro@brelect.fr') // Replace with your sender email, or from .env
                    ->to($client->getEmail())
                    ->subject('Veuillez confirmer votre adresse e-mail pour RDV Pro')
                    ->html($this->renderView('client_registration/confirmation_email.html.twig', [
                        'signedUrl' => $signatureComponents->getSignedUrl(),
                        'client' => $client,
                        'professional' => $professional,
                        'expiresAt' => $signatureExpiresAt, 
                    ]));

                $customMailer->send($emailMessage); // Send using the custom mailer
                $this->addFlash('success', 'Votre compte a été créé ! Veuillez vérifier votre email pour le lien de confirmation.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Votre compte a été créé, mais l\'e-mail de confirmation n\'a pas pu être envoyé. Erreur: ' . $e->getMessage());
            }

            // MODIFICATION: Rediriger vers la page de connexion avec le paramètre bookingLink
            return $this->redirectToRoute('app_login', ['bookingLink' => $professional->getBookingLink()]);
        }

        return $this->render('client_registration/register.html.twig', [
            'registrationForm' => $form->createView(),
            'professional' => $professional,
        ]);
    }

    #[Route('/verify/client/email', name: 'app_verify_client_email')]
    public function verifyClientEmail(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        // We need to fetch the Client entity, not User
        $id = $request->get('id'); // Retrieve the user id from the url

        if (null === $id) {
            return $this->redirectToRoute('app_login'); // Or a generic error page
        }

        $client = $entityManager->getRepository(Client::class)->find($id); // Find the client by ID

        if (null === $client) {
            return $this->redirectToRoute('app_login'); // Or a generic error page
        }

        try {
            $this->verifyEmailHelper->validateEmailConfirmation($request->getUri(), $client->getId(), $client->getEmail());
            $client->setIsVerified(true); // Mark client as verified
            $entityManager->flush();

        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('error', $exception->getReason());
            return $this->redirectToRoute('app_client_register', ['bookingLink' => $client->getProfessional()->getBookingLink()]); // Redirect back to client registration with error
        }

        $this->addFlash('success', 'Votre adresse e-mail a été vérifiée avec succès ! Vous pouvez maintenant vous connecter.');

        // MODIFICATION: Rediriger vers la page de connexion avec le paramètre bookingLink après vérification d'email
        if ($client->getProfessional()) {
            return $this->redirectToRoute('app_login', ['bookingLink' => $client->getProfessional()->getBookingLink()]);
        } else {
            // Fallback si, pour une raison quelconque, le professionnel n'est pas trouvé (ce qui ne devrait pas arriver)
            return $this->redirectToRoute('app_login');
        }
    }

    #[Route('/legal/terms-client', name: 'app_legal_terms_client')]
      public function termsClient(): Response
    {
        return $this->render('legal/terms_client.html.twig');
    }
}