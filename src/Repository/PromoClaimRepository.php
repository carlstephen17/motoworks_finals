<?php

namespace App\Repository;

use App\Entity\PromoClaim;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PromoClaim>
 */
class PromoClaimRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PromoClaim::class);
    }

    /** @return list<PromoClaim> */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('claim')
            ->andWhere('claim.user = :user')
            ->setParameter('user', $user)
            ->orderBy('claim.appointmentDate', 'DESC')
            ->addOrderBy('claim.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
