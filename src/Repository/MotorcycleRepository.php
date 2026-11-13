<?php

namespace App\Repository;

use App\Entity\Motorcycle;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Motorcycle>
 */
class MotorcycleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Motorcycle::class);
    }

    /** @return list<Motorcycle> */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['id' => 'ASC']);
    }

    public function findDuplicate(User $user, string $model, ?string $plateNumber): ?Motorcycle
    {
        $query = $this->createQueryBuilder('motorcycle')
            ->andWhere('motorcycle.user = :user')
            ->andWhere('LOWER(TRIM(motorcycle.model)) = LOWER(TRIM(:model))')
            ->setParameter('user', $user)
            ->setParameter('model', $model);
        if ($plateNumber === null) {
            $query->andWhere('motorcycle.plateNumber IS NULL');
        } else {
            $query->andWhere('motorcycle.plateNumber = :plateNumber')
                ->setParameter('plateNumber', $plateNumber);
        }

        return $query->getQuery()->getOneOrNullResult();
    }

    public function findByPlateNumber(string $plateNumber): ?Motorcycle
    {
        return $this->findOneBy(['plateNumber' => $plateNumber]);
    }
}
