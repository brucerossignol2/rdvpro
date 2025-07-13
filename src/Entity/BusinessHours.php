<?php

namespace App\Entity;

use App\Repository\BusinessHoursRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BusinessHoursRepository::class)]
#[ORM\Table(name: 'business_hours')] // Assurez-vous que le nom de la table correspond
class BusinessHours
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'businessHours')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $professional = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le jour de la semaine est obligatoire.')]
    #[Assert\Range(min: 1, max: 7, notInRangeMessage: 'Le jour de la semaine doit être entre 1 (Lundi) et 7 (Dimanche).')]
    private ?int $dayOfWeek = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column]
    private ?bool $isOpen = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startTime2 = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endTime2 = null;

    // Nouvelle propriété pour indiquer si le jour est un jour de repos
    #[ORM\Column]
    private ?bool $isDayOff = false; // Initialiser à false par défaut


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

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(int $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeInterface $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeInterface $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function isIsOpen(): ?bool
    {
        return $this->isOpen;
    }

    public function setIsOpen(bool $isOpen): static
    {
        $this->isOpen = $isOpen;

        return $this;
    }

    public function getStartTime2(): ?\DateTimeInterface
    {
        return $this->startTime2;
    }

    public function setStartTime2(?\DateTimeInterface $startTime2): static
    {
        $this->startTime2 = $startTime2;

        return $this;
    }

    public function getEndTime2(): ?\DateTimeInterface
    {
        return $this->endTime2;
    }

    public function setEndTime2(?\DateTimeInterface $endTime2): static
    {
        $this->endTime2 = $endTime2;

        return $this;
    }

    public function isDayOff(): ?bool
    {
        return $this->isDayOff;
    }

    public function setIsDayOff(bool $isDayOff): static
    {
        $this->isDayOff = $isDayOff;

        return $this;
    }

    // Helper method to get day name from integer (for display in Twig)
    public function getDayName(): string
    {
        return match ($this->dayOfWeek) {
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            7 => 'Dimanche',
            default => 'Inconnu',
        };
    }
}
