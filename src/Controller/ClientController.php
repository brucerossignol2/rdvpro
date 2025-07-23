<?php
// src/Controller/ClientController.php
namespace App\Controller;

use App\Entity\Client;
use App\Entity\User;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use App\Repository\AppointmentRepository; // Import the AppointmentRepository
use App\Repository\ClientProfessionalHistoryRepository; 

#[Route('/client')]
#[IsGranted('ROLE_USER')]
class ClientController extends AbstractController
{
    private UserPasswordHasherInterface $userPasswordHasher;
    private MailerInterface $mailer;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher, MailerInterface $mailer, UrlGeneratorInterface $urlGenerator)
    {
        $this->userPasswordHasher = $userPasswordHasher;
        $this->mailer = $mailer;
        $this->urlGenerator = $urlGenerator;
    }

    #[Route('/', name: 'app_client_index', methods: ['GET'])]
    public function index(ClientRepository $clientRepository, ClientProfessionalHistoryRepository $historyRepository): Response
    {
        /** @var User $professional */
        $professional = $this->getUser();

        // 1. Récupérer les clients du professionnel principal
        $clients = $clientRepository->findBy(['professional' => $professional]);

        // 2. Récupérer les clients associés via l'historique
        $clientsFromHistory = $historyRepository->findBy(['user' => $professional]);

        // 3. Extraire les objets Client de l'historique
        $historicalClients = array_map(function($history) {
            return $history->getClient();
        }, $clientsFromHistory);

        // 4. Fusionner et retirer les doublons en utilisant les IDs
        $allClientsMap = [];
        foreach ($clients as $client) {
            $allClientsMap[$client->getId()] = $client;
        }
        foreach ($historicalClients as $client) {
            $allClientsMap[$client->getId()] = $client;
        }

        // 5. Récupérer les valeurs du tableau pour obtenir la liste finale des clients uniques
        $allClients = array_values($allClientsMap);

        return $this->render('client/index.html.twig', [
            'clients' => $allClients,
        ]);
    }

    #[Route('/new', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, ValidatorInterface $validator, ClientRepository $clientRepository): Response
    {
        /** @var User $professional */
        $professional = $this->getUser();

        if (!$professional) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour créer un client.');
        }

        $client = new Client();
        $client->setProfessional($professional);
        $client->setCreatedAt(new \DateTimeImmutable());
        $client->setIsVerified(true);
        $client->setRoles(['ROLE_CLIENT']);

        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existingClient = $clientRepository->findOneByEmailAndProfessional($client->getEmail(), $professional);

            if ($existingClient) {
                $this->addFlash('error', 'Un client avec cette adresse email existe déjà pour votre compte.');
                return $this->render('client/new.html.twig', [
                    'client' => $client,
                    'form' => $form,
                ]);
            }

            $randomPassword = bin2hex(random_bytes(8));
            $client->setPassword(
                $this->userPasswordHasher->hashPassword(
                    $client,
                    $randomPassword
                )
            );

            $entityManager->persist($client);
            $entityManager->flush();

            try {
                if ($client && $client->getEmail() && $professional && $professional->getEmail()) {
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

                        if ($professional->getBusinessEmail()) {
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

            if ($request->query->get('returnToAppointment')) {
                $appointmentStart = $request->query->get('appointmentStart');
                $appointmentEnd = $request->query->get('appointmentEnd');

                return $this->redirectToRoute('app_appointment_new_prefilled', [
                    'start' => $appointmentStart,
                    'end' => $appointmentEnd,
                    'clientId' => $client->getId(),
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
    public function show(Client $client, AppointmentRepository $appointmentRepository): Response
    {
        /** @var User $professional */
        $professional = $this->getUser();

        // Vérifier si l'utilisateur a le rôle ROLE_ADMIN (accès complet)
        if ($this->isGranted('ROLE_ADMIN')) {
            // L'admin a accès à tous les clients
        }
        // Ou si le professionnel est le professionnel principal OU fait partie des autres professionnels
        else if ($client->getProfessional() === $professional || $client->getOtherProfessionals()->contains($professional)) {
            // Accès autorisé
        }
        // Sinon, l'accès est refusé
        else {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce client.');
        }
        
        $upcomingAppointments = $appointmentRepository->findByProfessionalAndClientUpcomingAppointments($professional, $client);
        
        return $this->render('client/show.html.twig', [
            'client' => $client,
            'appointments' => $upcomingAppointments,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_client_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Client $client, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        /** @var User $professional */
        $professional = $this->getUser();

        // Vérifier si l'utilisateur a le rôle ROLE_ADMIN (accès complet)
        if ($this->isGranted('ROLE_ADMIN')) {
            // L'admin a accès à tous les clients
        }
        // Ou si le professionnel est le professionnel principal OU fait partie des autres professionnels
        else if ($client->getProfessional() === $professional || $client->getOtherProfessionals()->contains($professional)) {
            // Accès autorisé
        }
        // Sinon, l'accès est refusé
        else {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour modifier ce client.');
        }

        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existingClient = $entityManager->getRepository(Client::class)->findOneBy([
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

        // Condition pour autoriser la suppression :
        $canDelete = $client->getProfessional() === $professional && $client->getOtherProfessionals()->isEmpty();

        // On vérifie si l'utilisateur est un administrateur ou s'il remplit les conditions de suppression
        if ($this->isGranted('ROLE_ADMIN') || $canDelete) {
            if ($this->isCsrfTokenValid('delete' . $client->getId(), $request->request->get('_token'))) {
                $entityManager->remove($client);
                $entityManager->flush();
                $this->addFlash('success', 'Client supprimé avec succès.');
            } else {
                $this->addFlash('error', 'Token CSRF invalide.');
            }
        } else {
            // Remplacer l'exception par un message flash et une redirection
            $this->addFlash(
                'error',
                'Vous n\'avez pas les droits pour supprimer ce client ou il est associé à d\'autres professionnels.'
            );
        }

        // Dans tous les cas, on redirige vers la liste des clients.
        return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
    }
}