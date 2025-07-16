<?php
// src/Controller/ClientController.php
namespace App\Controller;

use App\Entity\Client;
use App\Entity\User; // Don't forget to import User entity
use App\Form\ClientType; // Assuming ClientTypeForm.php defines ClientType
use App\Repository\ClientRepository;
use App\Repository\UserRepository; // Import UserRepository
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse; // Added for AJAX response
use Symfony\Component\Validator\Validator\ValidatorInterface; // Added for manual validation
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface; // Import the password hasher
use Symfony\Component\Mailer\MailerInterface; // Import MailerInterface
use Symfony\Component\Mime\Email; // Import Email for sending
use Symfony\Component\Routing\Generator\UrlGeneratorInterface; // Import UrlGeneratorInterface
use Symfony\Component\Dotenv\Dotenv; // Import Dotenv
use Symfony\Component\Mailer\Transport; // Import Transport
use Symfony\Component\Mailer\Mailer; // Import Mailer

#[Route('/client')]
#[IsGranted('ROLE_USER')] // Restrict access to all client routes to authenticated users
class ClientController extends AbstractController
{
    private UserPasswordHasherInterface $userPasswordHasher;
    private MailerInterface $mailer; // Declare MailerInterface
    private UrlGeneratorInterface $urlGenerator; // Declare UrlGeneratorInterface

    public function __construct(UserPasswordHasherInterface $userPasswordHasher, MailerInterface $mailer, UrlGeneratorInterface $urlGenerator)
    {
        $this->userPasswordHasher = $userPasswordHasher;
        $this->mailer = $mailer;
        $this->urlGenerator = $urlGenerator;
    }

    #[Route('/', name: 'app_client_index', methods: ['GET'])]
    public function index(ClientRepository $clientRepository): Response
    {
        /** @var User $professional */
        $professional = $this->getUser();

        if (!$professional) {
            throw $this->createAccessDeniedException();
        }

        $clients = $clientRepository->findBy(['professional' => $professional]);

        return $this->render('client/index.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/new', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, ValidatorInterface $validator, ClientRepository $clientRepository): Response // Injected ClientRepository
    {
        /** @var User $professional */
        $professional = $this->getUser();

        if (!$professional) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour créer un client.');
        }

        $client = new Client();
        // Set the professional for the new client
        $client->setProfessional($professional);
        $client->setCreatedAt(new \DateTimeImmutable());
        $client->setIsVerified(true); // Clients created by professional are automatically verified
        $client->setRoles(['ROLE_CLIENT']); // Assign ROLE_CLIENT

        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if a client with this email already exists for this professional
            $existingClient = $clientRepository->findOneByEmailAndProfessional($client->getEmail(), $professional);

            if ($existingClient) {
                $this->addFlash('error', 'Un client avec cette adresse email existe déjà pour votre compte.');
                // Re-render the form with the error message
                return $this->render('client/new.html.twig', [
                    'client' => $client,
                    'form' => $form,
                ]);
            }

            // Generate a random password
            $randomPassword = bin2hex(random_bytes(8)); // Generates a 16-character hex string
            $client->setPassword(
                $this->userPasswordHasher->hashPassword(
                    $client,
                    $randomPassword
                )
            );

            $entityManager->persist($client);
            $entityManager->flush();

            // --- Send email to the new client ---
            try {
                if ($client && $client->getEmail() && $professional && $professional->getEmail()) {
                    // Load .env file
                    $dotenv = new Dotenv();
                    $dotenv->loadEnv(dirname(__DIR__, 2) . '/.env');

                    $mailerDsn = $_ENV['MAILER_DSN'] ?? null;

                    if (!$mailerDsn) {
                        throw new \RuntimeException('MAILER_DSN environment variable is not set in .env');
                    }

                    $transport = Transport::fromDsn($mailerDsn);
                    $mailer = new Mailer($transport);

                    $professionalNameForEmail = $professional->getFirstName() . ' ' . $professional->getLastName();
                    $email = (new Email())
                        ->from($_ENV['MAILER_FROM_EMAIL'] ?? 'rdvpro@brelect.fr')
                        ->to($client->getEmail())
                        ->subject('Votre compte sur RDV Pro a été créé !')
                        ->html(
                            '<p>Bonjour ' . $client->getFirstName() . ',</p>' .
                            '<p>Votre compte sur RDV Pro a été créé par ' . $professionalNameForEmail . '.</p>' .
                            '<p>Votre mot de passe temporaire est : <strong>' . $randomPassword . '</strong></p>' .
                            '<p>Veuillez vous connecter et changer votre mot de passe dès que possible.</p>' .
                            '<p>Lien de connexion : <a href="https://rdvpro.brelect.fr/login">Se connecter</a></p>' .
                            '<p>Cordialement,</p>' .
                            '<p>L\'équipe RDV Pro</p>'
                        );
                        // Met répondre au professionnel si le mail existe
                        if ($professional->getBusinessEmail()) { // Assuming businessEmail is the reply-to
                            $email->addReplyTo($professional->getBusinessEmail());
                        }

                    $mailer->send($email);
                    $this->addFlash('success', 'Client créé avec succès et un mot de passe temporaire a été envoyé par email.');
                } else {
                    $this->addFlash('warning', 'Client créé, mais impossible d\'envoyer l\'email de confirmation au client (email manquant).');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Client créé, mais une erreur est survenue lors de l\'envoi de l\'email : ' . $e->getMessage());
            }
            // --- End Send email ---

            // Check if we need to return to the appointment creation page
            if ($request->query->get('returnToAppointment')) {
                $appointmentStart = $request->query->get('appointmentStart');
                $appointmentEnd = $request->query->get('appointmentEnd');

                return $this->redirectToRoute('app_appointment_new_prefilled', [
                    'start' => $appointmentStart,
                    'end' => $appointmentEnd,
                    'clientId' => $client->getId(), // Pass the newly created client's ID
                ], Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('client/new.html.twig', [
            'client' => $client,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_client_show', methods: ['GET'])]
    public function show(Client $client): Response
    {
        /** @var User $professional */
        $professional = $this->getUser();

        // Ensure the professional can only view their own clients
        if ($client->getProfessional() !== $professional) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce client.');
        }

        return $this->render('client/show.html.twig', [
            'client' => $client,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_client_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Client $client, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        /** @var User $professional */
        $professional = $this->getUser();

        // Ensure the professional can only edit their own clients
        if ($client->getProfessional() !== $professional) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour modifier ce client.');
        }

        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check for duplicate email on edit, excluding the current client being edited
            $existingClient = $this->getDoctrine()->getRepository(Client::class)->findOneBy([
                'email' => $client->getEmail(),
                'professional' => $professional
            ]);

            if ($existingClient && $existingClient->getId() !== $client->getId()) {
                $this->addFlash('error', 'Un autre client avec cette adresse email existe déjà pour votre compte.');
                return $this->render('client/edit.html.twig', [
                    'client' => $client,
                    'form' => $form,
                ]);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Client modifié avec succès.');

            return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('client/edit.html.twig', [
            'client' => $client,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_client_delete', methods: ['POST'])]
    public function delete(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        /** @var User $professional */
        $professional = $this->getUser();

        // Ensure the professional can only delete their own clients
        if ($client->getProfessional() !== $professional) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour supprimer ce client.');
        }

        if ($this->isCsrfTokenValid('delete' . $client->getId(), $request->request->get('_token'))) {
            $entityManager->remove($client);
            $entityManager->flush();
            $this->addFlash('success', 'Client supprimé avec succès.');
        }

        return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
    }
}
