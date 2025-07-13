<?php
// src/Repository/BusinessHoursRepository.php
namespace App\Repository;

use App\Entity\BusinessHours;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BusinessHours>
 *
 * @method BusinessHours|null find($id, $lockMode = null, $lockVersion = null)
 * @method BusinessHours|null findOneBy(array $criteria, array $orderBy = null)
 * @method BusinessHours[]    findAll()
 * @method BusinessHours[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BusinessHoursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BusinessHours::class);
    }

    /**
     * @return BusinessHours[] Returns an array of BusinessHours objects for a given professional
     */
    public function findByProfessional(User $professional): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.professional = :professional')
            ->setParameter('professional', $professional)
            ->orderBy('b.dayOfWeek', 'ASC') // You might need a custom order for days of the week
            ->getQuery()
            ->getResult();
    }

    // Add other methods as needed
}
