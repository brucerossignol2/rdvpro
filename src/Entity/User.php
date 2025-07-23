<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')] // Use backticks for 'user' as it's a reserved keyword in some databases
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'Veuillez entrer une adresse email valide')]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire')]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    private ?string $lastName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom de l\'entreprise est obligatoire')]
    private ?string $businessName = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'L\'adresse de l\'entreprise est obligatoire')]
    private ?string $businessAddress = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le téléphone de l\'entreprise est obligatoire')]
    private ?string $businessPhone = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'L\'email de l\'entreprise est obligatoire')]
    #[Assert\Email(message: 'Veuillez entrer une adresse email valide pour l\'entreprise')]
    private ?string $businessEmail = null;

    #[ORM\Column(length: 255)]
    private ?string $bookingLink = null;

    #[ORM\OneToMany(mappedBy: 'professional', targetEntity: Client::class, orphanRemoval: true)]
    private Collection $clients;

    #[ORM\OneToMany(mappedBy: 'professional', targetEntity: Service::class, orphanRemoval: true)]
    private Collection $services;

    #[ORM\OneToMany(mappedBy: 'professional', targetEntity: Appointment::class, orphanRemoval: true)]
    private Collection $appointments;

    #[ORM\OneToMany(mappedBy: 'professional', targetEntity: BusinessHours::class, orphanRemoval: true)]
    private Collection $businessHours;

    #[ORM\OneToMany(mappedBy: 'professional', targetEntity: Unavailability::class, orphanRemoval: true)]
    private Collection $unavailabilities;

    // Nouvelles propriétés pour la présentation
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000, maxMessage: 'La description de la présentation ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $presentationDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $presentationImage = null;

    #[ORM\Column(length: 255, nullable: true)] // Nouvelle propriété pour le logo
    private ?string $presentationLogo = null;

    #[ORM\ManyToMany(targetEntity: Client::class, mappedBy: 'otherProfessionals')]
    private Collection $clientsHistorique;

    /**
     * @var Collection<int, ClientProfessionalHistory>
     */
    #[ORM\OneToMany(targetEntity: ClientProfessionalHistory::class, mappedBy: 'user')]
    private Collection $clientProfessionalHistories;

    public function __construct()
    {
        $this->clients = new ArrayCollection();
        $this->services = new ArrayCollection();
        $this->appointments = new ArrayCollection();
        $this->businessHours = new ArrayCollection();
        $this->unavailabilities = new ArrayCollection();
        $this->roles = ['ROLE_USER']; // Default role
        $this->clientsHistorique = new ArrayCollection();
        $this->clientProfessionalHistories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getBusinessName(): ?string
    {
        return $this->businessName;
    }

    public function setBusinessName(string $businessName): static
    {
        $this->businessName = $businessName;

        return $this;
    }

    public function getBusinessAddress(): ?string
    {
        return $this->businessAddress;
    }

    public function setBusinessAddress(string $businessAddress): static
    {
        $this->businessAddress = $businessAddress;

        return $this;
    }

    public function getBusinessPhone(): ?string
    {
        return $this->businessPhone;
    }

    public function setBusinessPhone(string $businessPhone): static
    {
        $this->businessPhone = $businessPhone;

        return $this;
    }

    public function getBusinessEmail(): ?string
    {
        return $this->businessEmail;
    }

    public function setBusinessEmail(string $businessEmail): static
    {
        $this->businessEmail = $businessEmail;

        return $this;
    }

    public function getBookingLink(): ?string
    {
        return $this->bookingLink;
    }

    public function setBookingLink(string $bookingLink): static
    {
        $this->bookingLink = $bookingLink;

        return $this;
    }

    /**
     * @return Collection<int, Client>
     */
    public function getClients(): Collection
    {
        return $this->clients;
    }

    public function addClient(Client $client): static
    {
        if (!$this->clients->contains($client)) {
            $this->clients->add($client);
            $client->setProfessional($this);
        }

        return $this;
    }

    public function removeClient(Client $client): static
    {
        if ($this->clients->removeElement($client)) {
            // set the owning side to null (unless already changed)
            if ($client->getProfessional() === $this) {
                $client->setProfessional(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Service>
     */
    public function getServices(): Collection
    {
        return $this->services;
    }

    public function addService(Service $service): static
    {
        if (!$this->services->contains($service)) {
            $this->services->add($service);
            $service->setProfessional($this);
        }

        return $this;
    }

    public function removeService(Service $service): static
    {
        if ($this->services->removeElement($service)) {
            // set the owning side to null (unless already changed)
            if ($service->getProfessional() === $this) {
                $service->setProfessional(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function getAppointments(): Collection
    {
        return $this->appointments;
    }

    public function addAppointment(Appointment $appointment): static
    {
        if (!$this->appointments->contains($appointment)) {
            $this->appointments->add($appointment);
            $appointment->setProfessional($this);
        }

        return $this;
    }

    public function removeAppointment(Appointment $appointment): static
    {
        if ($this->appointments->removeElement($appointment)) {
            // set the owning side to null (unless already changed)
            if ($appointment->getProfessional() === $this) {
                $appointment->setProfessional(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, BusinessHours>
     */
    public function getBusinessHours(): Collection
    {
        return $this->businessHours;
    }

    public function addBusinessHour(BusinessHours $businessHour): static
    {
        if (!$this->businessHours->contains($businessHour)) {
            $this->businessHours->add($businessHour);
            $businessHour->setProfessional($this);
        }

        return $this;
    }

    public function removeBusinessHour(BusinessHours $businessHour): static
    {
        if ($this->businessHours->removeElement($businessHour)) {
            // set the owning side to null (unless already changed)
            if ($businessHour->getProfessional() === $this) {
                $businessHour->setProfessional(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Unavailability>
     */
    public function getUnavailabilities(): Collection
    {
        return $this->unavailabilities;
    }

    public function addUnavailability(Unavailability $unavailability): static
    {
        if (!$this->unavailabilities->contains($unavailability)) {
            $this->unavailabilities->add($unavailability);
            $unavailability->setProfessional($this);
        }

        return $this;
    }

    public function removeUnavailability(Unavailability $unavailability): static
    {
        if ($this->unavailabilities->removeElement($unavailability)) {
            // set the owning side to null (unless already changed)
            if ($unavailability->getProfessional() === $this) {
                $unavailability->setProfessional(null);
            }
        }

        return $this;
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function getPresentationDescription(): ?string
    {
        return $this->presentationDescription;
    }

    public function setPresentationDescription(?string $presentationDescription): static
    {
        $this->presentationDescription = $presentationDescription;

        return $this;
    }

    public function getPresentationImage(): ?string
    {
        return $this->presentationImage;
    }

    public function setPresentationImage(?string $presentationImage): static
    {
        $this->presentationImage = $presentationImage;

        return $this;
    }

    // Getter et Setter pour presentationLogo
    public function getPresentationLogo(): ?string
    {
        return $this->presentationLogo;
    }

    public function setPresentationLogo(?string $presentationLogo): static
    {
        $this->presentationLogo = $presentationLogo;

        return $this;
    }
    public function getClientsHistorique(): Collection
    {
        return $this->clientsHistorique;
    }

    /**
     * @return Collection<int, ClientProfessionalHistory>
     */
    public function getClientProfessionalHistories(): Collection
    {
        return $this->clientProfessionalHistories;
    }

    public function addClientProfessionalHistory(ClientProfessionalHistory $clientProfessionalHistory): static
    {
        if (!$this->clientProfessionalHistories->contains($clientProfessionalHistory)) {
            $this->clientProfessionalHistories->add($clientProfessionalHistory);
            $clientProfessionalHistory->setUser($this);
        }

        return $this;
    }

    public function removeClientProfessionalHistory(ClientProfessionalHistory $clientProfessionalHistory): static
    {
        if ($this->clientProfessionalHistories->removeElement($clientProfessionalHistory)) {
            // set the owning side to null (unless already changed)
            if ($clientProfessionalHistory->getUser() === $this) {
                $clientProfessionalHistory->setUser(null);
            }
        }

        return $this;
    }
}
