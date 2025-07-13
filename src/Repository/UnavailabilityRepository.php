<?php
// src/Repository/UnavailabilityRepository.php
namespace App\Repository;

use App\Entity\Unavailability;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UnavailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Unavailability::class);
    }

    public function findByProfessional(User $professional): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.professional = :professional')
            ->setParameter('professional', $professional)
            ->orderBy('u.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveByProfessional(User $professional): array
    {
        $today = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('u')
            ->where('u.professional = :professional')
            ->andWhere('u.endDate >= :today')
            ->setParameter('professional', $professional)
            ->setParameter('today', $today)
            ->orderBy('u.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByDateRange(User $professional, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.professional = :professional')
            ->andWhere('u.startDate <= :end')
            ->andWhere('u.endDate >= :start')
            ->setParameter('professional', $professional)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('u.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findConflictingUnavailabilities(
        User $professional,
        \DateTimeInterface $checkDate,
        ?\DateTimeInterface $startTime = null,
        ?\DateTimeInterface $endTime = null
    ): array {
        $qb = $this->createQueryBuilder('u')
            ->where('u.professional = :professional')
            ->andWhere('u.startDate <= :checkDate')
            ->andWhere('u.endDate >= :checkDate')
            ->setParameter('professional', $professional)
            ->setParameter('checkDate', $checkDate);

        return $qb->getQuery()->getResult();
    }
}