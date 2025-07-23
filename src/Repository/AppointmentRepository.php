<?php
// src/Repository/AppointmentRepository.php
namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\User;
use App\Entity\Client; // Import Client entity
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DateTimeImmutable; // Import DateTimeImmutable

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

    /**
     * Rappel de rendez-vous Confirmé 24 heurs avant - Finds appointments that are scheduled to start within a specific time window
     * (e.g., exactly 24 hours from now) for sending reminders.
     *
     * @param DateTimeImmutable $startWindow The start of the time window (e.g., now + 23 hours).
     * @param DateTimeImmutable $endWindow The end of the time window (e.g., now + 25 hours).
     * @return Appointment[] Returns an array of Appointment objects due for a reminder.
     */
    public function findAppointmentsDueForReminder(DateTimeImmutable $startWindow, DateTimeImmutable $endWindow): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.isPersonalUnavailability = :isPersonal') // Only consider client appointments
            ->andWhere('a.startTime >= :startWindow')
            ->andWhere('a.startTime < :endWindow')
            ->andWhere('a.client IS NOT NULL') // Ensure a client is associated
            ->andWhere('a.status = :status') // Ajout de la condition sur le statut
            ->setParameter('isPersonal', false)
            ->setParameter('startWindow', $startWindow)
            ->setParameter('endWindow', $endWindow)
            ->setParameter('status', 'confirmed') // Définir le statut à "confirmed"
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds all appointments and unavailabilities for a given professional within a date range.
     *
     * @param User $professional
     * @param \DateTimeInterface $start
     * @param \DateTimeInterface $end
     * @return Appointment[] Returns an array of Appointment objects
     */
    public function findAppointmentsInDateRange(User $professional, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.professional = :professional')
            ->andWhere('a.startTime < :end AND a.endTime > :start')
            ->setParameter('professional', $professional)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
    /**
     * @return Appointment[] Returns an array of upcoming Appointment objects for a specific professional and client
     */
    public function findByProfessionalAndClientUpcomingAppointments(User $professional, Client $client): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.professional = :professional')
            ->andWhere('a.client = :client')
            ->andWhere('a.endTime >= :now')
            ->setParameter('professional', $professional)
            ->setParameter('client', $client)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}