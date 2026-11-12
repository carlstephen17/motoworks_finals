<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /** @return list<Product> */
    public function findActiveProducts(): array
    {
        return $this->createQueryBuilder('product')
            ->andWhere('product.active = true')
            ->orderBy('product.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Product> */
    public function findCatalogProducts(): array
    {
        return $this->createQueryBuilder('product')
            ->andWhere('product.active = true')
            ->orderBy('product.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Product> */
    public function findHomepageProducts(): array
    {
        return $this->createQueryBuilder('product')
            ->andWhere('product.active = true')
            ->orderBy('product.id', 'ASC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();
    }
}
