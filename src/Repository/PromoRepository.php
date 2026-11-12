<?php

namespace App\Repository;

use App\Entity\Promo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Promo>
 */
class PromoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Promo::class);
    }

    /** @return list<Promo> */
    public function findActivePromos(): array
    {
        return $this->createQueryBuilder('promo')
            ->andWhere('promo.active = true')
            ->orderBy('promo.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
