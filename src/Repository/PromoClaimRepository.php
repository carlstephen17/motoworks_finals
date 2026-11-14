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
            ->leftJoin('claim.promo', 'promo')
            ->addSelect('promo')
            ->andWhere('claim.user = :user')
            ->setParameter('user', $user)
            ->orderBy('claim.appointmentDate', 'ASC')
            ->addOrderBy('claim.appointmentTime', 'ASC')
            ->addOrderBy('claim.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<PromoClaim> */
    public function findRecentForAdmin(int $limit = 5): array
    {
        return $this->createQueryBuilder('claim')
            ->leftJoin('claim.user', 'user')
            ->addSelect('user')
            ->leftJoin('claim.promo', 'promo')
            ->addSelect('promo')
            ->orderBy('claim.createdAt', 'DESC')
            ->addOrderBy('claim.id', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /** @return list<PromoClaim> */
    public function findForStaff(string $search, string $status): array
    {
        $query = $this->createQueryBuilder('claim')
            ->leftJoin('claim.user', 'user')
            ->addSelect('user')
            ->leftJoin('claim.promo', 'promo')
            ->addSelect('promo')
            ->orderBy('claim.id', 'DESC');

        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->andWhere(
                'LOWER(claim.fullName) LIKE :term
                OR LOWER(claim.phone) LIKE :term
                OR LOWER(claim.bundleSlug) LIKE :term
                OR LOWER(claim.selectedOption) LIKE :term
                OR LOWER(promo.title) LIKE :term
                OR LOWER(user.username) LIKE :term'
            )->setParameter('term', $term);
        }

        if ($status === 'Pending') {
            $query->andWhere('(claim.status NOT IN (:terminalStatuses) OR claim.status IS NULL)')
                ->setParameter('terminalStatuses', ['Completed', 'Cancelled']);
        } elseif (in_array($status, ['Completed', 'Cancelled'], true)) {
            $query->andWhere('claim.status = :status')->setParameter('status', $status);
        }

        return $query->getQuery()->getResult();
    }

    /** @return array{total: int, pending: int, completed: int, cancelled: int} */
    public function getStaffStatusCounts(): array
    {
        $counts = ['total' => 0, 'pending' => 0, 'completed' => 0, 'cancelled' => 0];
        $rows = $this->createQueryBuilder('claim')
            ->select('claim.status AS status, COUNT(claim.id) AS statusCount')
            ->groupBy('claim.status')
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $count = (int) $row['statusCount'];
            $counts['total'] += $count;
            $status = $row['status'] ?? 'Pending';
            if ($status === 'Completed') {
                $counts['completed'] += $count;
            } elseif ($status === 'Cancelled') {
                $counts['cancelled'] += $count;
            } else {
                $counts['pending'] += $count;
            }
        }

        return $counts;
    }
}
