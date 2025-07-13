<?php
// src/Repository/ServiceRepository.php
namespace App\Repository;

use App\Entity\Service;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    public function findActiveByProfessional(User $professional): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.professional = :professional')
            ->andWhere('s.active = :active')
            ->setParameter('professional', $professional)
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAllByProfessional(User $professional): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.professional = :professional')
            ->setParameter('professional', $professional) // <-- Ligne ajoutée pour lier le paramètre
            ->orderBy('s.active', 'DESC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getServiceStats(User $professional): array
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id) as totalServices')
            ->addSelect('COUNT(CASE WHEN s.active = true THEN 1 END) as activeServices')
            ->addSelect('AVG(s.price) as averagePrice')
            ->addSelect('AVG(s.duration) as averageDuration')
            ->where('s.professional = :professional')
            ->setParameter('professional', $professional)
            ->getQuery()
            ->getSingleResult();
    }

    public function findMostPopularServices(User $professional, int $limit = 5): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.appointments', 'a')
            ->where('s.professional = :professional')
            ->andWhere('s.active = :active')
            ->groupBy('s.id')
            ->orderBy('COUNT(a.id)', 'DESC')
            ->setParameter('professional', $professional)
            ->setParameter('active', true)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
