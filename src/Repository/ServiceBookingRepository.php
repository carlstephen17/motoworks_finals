<?php

namespace App\Repository;

use App\Entity\ServiceBooking;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceBooking>
 */
class ServiceBookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceBooking::class);
    }

    /** @return list<ServiceBooking> */
    public function findForUser(User $user, bool $promosOnly = false): array
    {
        $query = $this->createQueryBuilder('booking')
            ->leftJoin('booking.service', 'service')
            ->addSelect('service')
            ->andWhere('booking.user = :user')
            ->setParameter('user', $user)
            ->orderBy('booking.id', 'DESC');

        if ($promosOnly) {
            $query->andWhere('booking.bundleSlug IS NOT NULL');
        }

        return $query->getQuery()->getResult();
    }
}
