<?php
// src/Entity/Appointment.php
namespace App\Entity;

use App\Repository\AppointmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
class Appointment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'appointments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $professional = null;

    #[ORM\ManyToOne(inversedBy: 'appointments')]
    private ?Client $client = null; // Client can be null for personal unavailability

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: "La date et l'heure de début sont obligatoires.")]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: "La date et l'heure de fin sont obligatoires.")]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre du rendez-vous est obligatoire.")]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

     #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionPrive = null;

    #[ORM\Column]
    private ?bool $isPersonalUnavailability = false; // True if it's a personal block, not a client appointment

    // AJOUTEZ CETTE PROPRIÉTÉ
    #[ORM\Column(length: 50)] // Par exemple, 'pending', 'confirmed', 'cancelled'
    private ?string $status = null;


    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToMany(targetEntity: Service::class, inversedBy: 'appointments')]
    private Collection $services;

    public function __construct()
    {
        $this->services = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->status = 'pending'; // Définissez un statut par défaut lors de la création
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfessional(): ?User
    {
        return $this->professional;
    }

    public function setProfessional(?User $professional): static
    {
        $this->professional = $professional;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

        public function getDescriptionPrive(): ?string
    {
        return $this->descriptionPrive;
    }

    public function setDescriptionPrive(?string $descriptionPrive): static
    {
        $this->descriptionPrive = $descriptionPrive;

        return $this;
    }
    
    public function isIsPersonalUnavailability(): ?bool
    {
        return $this->isPersonalUnavailability;
    }

    public function setIsPersonalUnavailability(bool $isPersonalUnavailability): static
    {
        $this->isPersonalUnavailability = $isPersonalUnavailability;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

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
        }

        return $this;
    }

    public function removeService(Service $service): static
    {
        $this->services->removeElement($service);

        return $this;
    }

    // Helper method to calculate total duration of services
    public function getTotalServicesDuration(): int
    {
        $totalDuration = 0;
        foreach ($this->getServices() as $service) {
            $totalDuration += $service->getDuration();
        }
        return $totalDuration;
    }

    // AJOUTEZ CES MÉTHODES (GETTER ET SETTER)
    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }
}