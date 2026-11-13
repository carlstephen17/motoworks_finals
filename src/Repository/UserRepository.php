<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByLoginIdentifier(string $identifier): ?User
    {
        return $this->createQueryBuilder('user')
            ->andWhere('LOWER(user.username) = :identifier OR LOWER(user.email) = :identifier')
            ->setParameter('identifier', strtolower(trim($identifier)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByEmailInsensitive(string $email): ?User
    {
        return $this->createQueryBuilder('user')
            ->andWhere('LOWER(user.email) = :email')
            ->setParameter('email', strtolower($email))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByRole(string $role): int
    {
        return (int) $this->createQueryBuilder('user')
            ->select('COUNT(user.id)')
            ->andWhere('user.role = :role')
            ->setParameter('role', $role)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
