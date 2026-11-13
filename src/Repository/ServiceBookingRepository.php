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

    /** @return list<ServiceBooking> */
    public function findForStaff(string $search, string $status, ?\DateTimeImmutable $date, string $source): array
    {
        $query = $this->createQueryBuilder('booking')
            ->innerJoin('booking.user', 'customer')
            ->addSelect('customer')
            ->innerJoin('booking.service', 'service')
            ->addSelect('service')
            ->leftJoin('booking.motorcycle', 'motorcycle')
            ->addSelect('motorcycle')
            ->leftJoin('booking.createdByStaff', 'staff')
            ->addSelect('staff')
            ->orderBy('booking.bookingDate', 'DESC')
            ->addOrderBy('booking.bookingTime', 'DESC')
            ->addOrderBy('booking.id', 'DESC');

        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $expressions = [
                'LOWER(CONCAT(CONCAT(customer.firstName, \' \'), customer.lastName)) LIKE :term',
                'LOWER(customer.email) LIKE :term',
                'LOWER(customer.phone) LIKE :term',
                'LOWER(booking.vehicleModel) LIKE :term',
                'LOWER(motorcycle.model) LIKE :term',
                'LOWER(motorcycle.plateNumber) LIKE :term',
                'LOWER(service.name) LIKE :term',
            ];
            if (ctype_digit($search)) {
                $expressions[] = 'booking.id = :bookingId';
                $query->setParameter('bookingId', (int) $search);
            }
            $query->andWhere($query->expr()->orX(...$expressions))->setParameter('term', $term);
        }

        if ($status === 'Pending') {
            $query->andWhere('booking.status IN (:statuses)')->setParameter('statuses', ['Pending', 'Confirmed']);
        } elseif ($status === 'Cancelled') {
            $query->andWhere('booking.status IN (:statuses)')->setParameter('statuses', ['Cancelled', 'Rejected']);
        } elseif ($status === 'Completed') {
            $query->andWhere('booking.status = :status')->setParameter('status', $status);
        }

        if ($date !== null) {
            $query->andWhere('booking.bookingDate = :date')->setParameter('date', $date);
        }

        $sourceValue = match ($source) {
            'online' => 'customer_online',
            'staff' => 'staff',
            'admin' => 'admin',
            default => '',
        };
        if ($sourceValue !== '') {
            $query->andWhere('booking.source = :source')->setParameter('source', $sourceValue);
        }

        return $query->getQuery()->getResult();
    }

    /** @return list<ServiceBooking> */
    public function findForAdmin(string $search, string $status): array
    {
        $query = $this->createQueryBuilder('booking')
            ->innerJoin('booking.user', 'customer')
            ->addSelect('customer')
            ->innerJoin('booking.service', 'service')
            ->addSelect('service')
            ->leftJoin('booking.motorcycle', 'motorcycle')
            ->addSelect('motorcycle')
            ->orderBy('booking.bookingDate', 'ASC')
            ->addOrderBy('booking.bookingTime', 'ASC');

        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $searchExpressions = [
                'LOWER(customer.firstName) LIKE :term',
                'LOWER(customer.lastName) LIKE :term',
                'LOWER(customer.email) LIKE :term',
                'LOWER(customer.phone) LIKE :term',
                'LOWER(booking.vehicleModel) LIKE :term',
                'LOWER(motorcycle.model) LIKE :term',
                'LOWER(motorcycle.plateNumber) LIKE :term',
                'LOWER(service.name) LIKE :term',
                'LOWER(booking.bundleSlug) LIKE :term',
                'LOWER(booking.bundleOption) LIKE :term',
            ];

            if (ctype_digit($search)) {
                $searchExpressions[] = 'booking.id = :bookingId';
                $query->setParameter('bookingId', (int) $search);
            }
            $query->andWhere($query->expr()->orX(...$searchExpressions))->setParameter('term', $term);
        }

        if ($status === 'Pending') {
            $query->andWhere('booking.status IN (:statuses)')->setParameter('statuses', ['Pending', 'Confirmed']);
        } elseif ($status === 'Cancelled') {
            $query->andWhere('booking.status IN (:statuses)')->setParameter('statuses', ['Cancelled', 'Rejected']);
        } elseif ($status === 'Completed') {
            $query->andWhere('booking.status = :status')->setParameter('status', $status);
        }

        return $query->getQuery()->getResult();
    }

    /** @return array{total: int, pending: int, cancelled: int, completed: int} */
    public function getAdminStatusCounts(): array
    {
        $counts = ['total' => 0, 'pending' => 0, 'cancelled' => 0, 'completed' => 0];
        $rows = $this->createQueryBuilder('booking')
            ->select('booking.status AS status, COUNT(booking.id) AS statusCount')
            ->groupBy('booking.status')
            ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            $count = (int) $row['statusCount'];
            $counts['total'] += $count;

            if (in_array($row['status'], ['Pending', 'Confirmed'], true)) {
                $counts['pending'] += $count;
            } elseif (in_array($row['status'], ['Cancelled', 'Rejected'], true)) {
                $counts['cancelled'] += $count;
            } elseif ($row['status'] === 'Completed') {
                $counts['completed'] += $count;
            }
        }

        return $counts;
    }
}
