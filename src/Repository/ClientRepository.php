<?php
// src/Repository/ClientRepository.php
namespace App\Repository;

use App\Entity\Client;
use App\Entity\User; // Import the User entity as it's used in your custom methods
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface; // Added UserInterface
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Client>
 *
 * @method Client|null find($id, $lockMode = null, $lockVersion = null)
 * @method Client|null findOneBy(array $criteria, array $orderBy = null)
 * @method Client[]    findAll()
 * @method Client[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClientRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $client, string $newHashedPassword): void
    {
        if (!$client instanceof Client) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($client)));
        }

        $client->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($client);
        $this->getEntityManager()->flush();
    }

    /**
     * Finds a client by email and professional.
     *
     * @param string $email The email address of the client.
     * @param User $professional The professional associated with the client.
     * @return Client|null Returns a Client object or null if not found.
     */
    public function findOneByEmailAndProfessional(string $email, User $professional): ?Client
    {
        return $this->createQueryBuilder('c')
            ->where('c.email = :email')
            ->andWhere('c.professional = :professional')
            ->setParameter('email', $email)
            ->setParameter('professional', $professional)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Searches clients for a given professional based on a search term.
     *
     * @param User $professional The professional whose clients to search.
     * @param string $search The search term.
     * @return Client[] Returns an array of matching Client objects.
     */
    public function searchClients(User $professional, string $search): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.professional = :professional')
            ->andWhere('c.lastName LIKE :search OR c.firstName LIKE :search OR c.email LIKE :search OR c.telephone LIKE :search')
            ->setParameter('professional', $professional)
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('c.lastName', 'ASC')
            ->addOrderBy('c.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Gets client statistics for a given professional.
     *
     * @param User $professional The professional for whom to get stats.
     * @return array Returns an array with totalClients and newThisMonth counts.
     */
    public function getClientStats(User $professional): array
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id) as totalClients')
            ->addSelect('COUNT(CASE WHEN c.createdAt >= :thisMonth THEN 1 END) as newThisMonth')
            ->where('c.professional = :professional')
            ->setParameter('professional', $professional)
            ->setParameter('thisMonth', new \DateTimeImmutable('first day of this month'))
            ->getQuery()
            ->getSingleResult();
    }
}
