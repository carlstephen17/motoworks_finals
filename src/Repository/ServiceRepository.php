<?php

namespace App\Repository;

use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    /** @return list<Service> */
    public function findActiveServices(): array
    {
        return $this->createQueryBuilder('service')
            ->andWhere('service.active = true')
            ->orderBy('service.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Service> */
    public function findFeaturedServices(): array
    {
        return $this->createQueryBuilder('service')
            ->andWhere('service.active = true')
            ->andWhere('service.featured = true')
            ->orderBy('service.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Service> */
    public function findHomepageServices(): array
    {
        return $this->createQueryBuilder('service')
            ->andWhere('service.active = true')
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();
    }
}
