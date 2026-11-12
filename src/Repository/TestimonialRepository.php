<?php

namespace App\Repository;

use App\Entity\Testimonial;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Testimonial>
 */
class TestimonialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Testimonial::class);
    }

    /** @return list<Testimonial> */
    public function findApprovedLatest(int $limit = 4): array
    {
        return $this->createQueryBuilder('testimonial')
            ->andWhere('testimonial.status = :status')
            ->setParameter('status', 'approved')
            ->orderBy('testimonial.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
