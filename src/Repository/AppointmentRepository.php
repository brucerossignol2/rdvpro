<?php
// src/Repository/AppointmentRepository.php
namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\User;
use App\Entity\Client; // Import Client entity
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 *
 * @method Appointment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Appointment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Appointment[]    findAll()
 * @method Appointment[]    findBy(array [] $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    /**
     * Finds overlapping appointments for a given professional within a time range.
     * Excludes an optional appointment ID (useful for editing an existing appointment).
     *
     * @param User $professional The professional to check appointments for.
     * @param \DateTimeInterface $startTime The start time of the new/edited appointment.
     * @param \DateTimeInterface $endTime The end time of the new/edited appointment.
     * @param int|null $excludeAppointmentId Optional ID of an appointment to exclude from the check.
     * @return Appointment[] Returns an array of overlapping Appointment objects.
     */
    public function findOverlappingAppointments(
        User $professional,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        ?int $excludeAppointmentId = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->where('a.professional = :professional')
            ->andWhere('a.startTime < :endTime AND a.endTime > :startTime') // Overlap condition
            ->setParameter('professional', $professional)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime);

        if ($excludeAppointmentId !== null) {
            $qb->andWhere('a.id != :excludeId')
               ->setParameter('excludeId', $excludeAppointmentId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Finds upcoming appointments for a given client, including those currently in progress.
     * Appointments are considered "upcoming" if their end time is greater than or equal to the current time.
     *
     * @param Client $client The client to check appointments for.
     * @param \DateTimeImmutable $now The current date and time.
     * @return Appointment[] Returns an array of upcoming Appointment objects.
     */
    public function findByClientUpcomingAppointments(Client $client, \DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.client = :client')
            ->andWhere('a.endTime >= :now') // Modifié pour inclure les RDV en cours (fin du RDV >= maintenant)
            ->orderBy('a.startTime', 'ASC')
            ->setParameter('client', $client)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds upcoming appointments for a given professional, including those currently in progress.
     * Appointments are considered "upcoming" if their end time is greater than or equal to the current time.
     *
     * @param User $professional The professional to check appointments for.
     * @param \DateTimeImmutable $now The current date and time.
     * @return Appointment[] Returns an array of upcoming Appointment objects.
     */
    public function findByProfessionalUpcomingAppointments(User $professional, \DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.professional = :professional')
            ->andWhere('a.endTime >= :now') // Modifié pour inclure les RDV en cours (fin du RDV >= maintenant)
            ->orderBy('a.startTime', 'ASC')
            ->setParameter('professional', $professional)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
