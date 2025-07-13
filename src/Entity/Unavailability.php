<?php
// src/Entity/Unavailability.php
namespace App\Entity;

use App\Repository\UnavailabilityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UnavailabilityRepository::class)]
class Unavailability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $title = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column]
    private ?bool $allDay = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(inversedBy: 'unavailabilities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $professional = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters et Setters...
    public function getId(): ?int { return $this->id; }
    
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    
    public function getStartDate(): ?\DateTimeInterface { return $this->startDate; }
    public function setStartDate(\DateTimeInterface $startDate): static { $this->startDate = $startDate; return $this; }
    
    public function getEndDate(): ?\DateTimeInterface { return $this->endDate; }
    public function setEndDate(\DateTimeInterface $endDate): static { $this->endDate = $endDate; return $this; }
    
    public function getStartTime(): ?\DateTimeInterface { return $this->startTime; }
    public function setStartTime(?\DateTimeInterface $startTime): static { $this->startTime = $startTime; return $this; }
    
    public function getEndTime(): ?\DateTimeInterface { return $this->endTime; }
    public function setEndTime(?\DateTimeInterface $endTime): static { $this->endTime = $endTime; return $this; }
    
    public function isAllDay(): ?bool { return $this->allDay; }
    public function setAllDay(bool $allDay): static { $this->allDay = $allDay; return $this; }
    
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
    
    public function getProfessional(): ?User { return $this->professional; }
    public function setProfessional(?User $professional): static { $this->professional = $professional; return $this; }
    
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function isActive(\DateTimeInterface $date): bool
    {
        return $date >= $this->startDate && $date <= $this->endDate;
    }

    public function conflictsWith(\DateTimeInterface $checkStart, \DateTimeInterface $checkEnd): bool
    {
        if ($this->allDay) {
            return true;
        }
        
        if (!$this->startTime || !$this->endTime) {
            return true;
        }
        
        $unavailStart = \DateTime::createFromFormat('H:i:s', $this->startTime->format('H:i:s'));
        $unavailEnd = \DateTime::createFromFormat('H:i:s', $this->endTime->format('H:i:s'));
        $checkStartTime = \DateTime::createFromFormat('H:i:s', $checkStart->format('H:i:s'));
        $checkEndTime = \DateTime::createFromFormat('H:i:s', $checkEnd->format('H:i:s'));
        
        return !($checkEndTime <= $unavailStart || $checkStartTime >= $unavailEnd);
    }
}