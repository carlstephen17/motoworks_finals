<?php

namespace App\Repository;

use App\Entity\CustomerOrder;
use App\Entity\OrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomerOrder>
 */
class CustomerOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomerOrder::class);
    }

    public function findBySubmissionKey(string $submissionKey): ?CustomerOrder
    {
        return $this->findOneBy(['submissionKey' => $submissionKey]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findForStaff(
        string $search = '',
        string $status = '',
        string $paymentStatus = '',
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateToExclusive = null,
        ?int $limit = 50,
        int $offset = 0,
    ): array {
        $query = $this->createFilteredQueryBuilder($search, $status, $paymentStatus, $dateFrom, $dateToExclusive)
            ->select(
                'customerOrder.id AS orderGroupId',
                'customerOrder.deliveryAddress AS deliveryAddress',
                'customerOrder.paymentMethod AS paymentMethod',
                'customerOrder.paymentReference AS paymentReference',
                'customerOrder.paymentStatus AS paymentStatus',
                'customerOrder.paymentVerifiedAt AS paymentVerifiedAt',
                'customerOrder.status AS currentStatus',
                'CASE WHEN customerOrder.status IN (\'Confirmed\', \'Shipped\') THEN \'Pending\' ELSE customerOrder.status END AS displayStatus',
                'customerOrder.cancellationReason AS cancellationReason',
                'customerOrder.createdAt AS createdAt',
                'customer.firstName AS firstName',
                'customer.lastName AS lastName',
                'customer.email AS email',
                'customer.phone AS phone',
                'COUNT(item.id) AS itemCount',
                'COALESCE(SUM(item.totalAmount), 0) AS orderTotal',
                'IDENTITY(customerOrder.createdByStaff) AS createdByStaffId',
            )
            ->groupBy(
                'customerOrder.id',
                'customerOrder.deliveryAddress',
                'customerOrder.paymentMethod',
                'customerOrder.paymentReference',
                'customerOrder.paymentStatus',
                'customerOrder.paymentVerifiedAt',
                'customerOrder.status',
                'customerOrder.cancellationReason',
                'customerOrder.createdAt',
                'customerOrder.createdByStaff',
                'customer.id',
                'customer.firstName',
                'customer.lastName',
                'customer.email',
                'customer.phone',
            )
            ->orderBy('customerOrder.createdAt', 'DESC')
            ->addOrderBy('customerOrder.id', 'DESC');

        if ($limit !== null) {
            $query->setMaxResults($limit);
        }
        if ($offset > 0) {
            $query->setFirstResult($offset);
        }

        return $query->getQuery()->getArrayResult();
    }

    public function countForStaff(
        string $search = '',
        string $status = '',
        string $paymentStatus = '',
        ?\DateTimeImmutable $dateFrom = null,
        ?\DateTimeImmutable $dateToExclusive = null,
    ): int {
        return (int) $this->createFilteredQueryBuilder($search, $status, $paymentStatus, $dateFrom, $dateToExclusive)
            ->select('COUNT(DISTINCT customerOrder.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRecentForAdmin(int $limit = 5): array
    {
        return $this->findForStaff(limit: max(1, $limit));
    }

    /**
     * @param list<int> $orderGroupIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function findItemsForGroups(array $orderGroupIds): array
    {
        if ($orderGroupIds === []) {
            return [];
        }

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select(
                'IDENTITY(item.customerOrder) AS orderGroupId',
                'product.id AS productId',
                'product.name AS productName',
                'product.image AS productImage',
                'item.quantity AS quantity',
                'item.totalAmount AS totalAmount',
                'COALESCE(item.unitPrice, CASE WHEN item.quantity > 0 THEN item.totalAmount / item.quantity ELSE item.totalAmount END) AS unitPrice',
            )
            ->from(OrderItem::class, 'item')
            ->innerJoin('item.customerOrder', 'customerOrder')
            ->innerJoin('item.product', 'product')
            ->andWhere('customerOrder.id IN (:orderGroupIds)')
            ->setParameter('orderGroupIds', $orderGroupIds)
            ->orderBy('customerOrder.id', 'ASC')
            ->addOrderBy('item.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $itemsByGroup = [];
        foreach ($rows as $row) {
            $itemsByGroup[(int) $row['orderGroupId']][] = $row;
        }

        return $itemsByGroup;
    }

    /** @return array{total: int, pending: int, cancelled: int, completed: int} */
    public function getStatusCounts(): array
    {
        $counts = ['total' => 0, 'pending' => 0, 'cancelled' => 0, 'completed' => 0];
        $rows = $this->createQueryBuilder('customerOrder')
            ->select('customerOrder.status AS status, COUNT(customerOrder.id) AS statusCount')
            ->groupBy('customerOrder.status')
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $row) {
            $count = (int) $row['statusCount'];
            $counts['total'] += $count;
            if (in_array($row['status'], ['Pending', 'Cancellation Pending', 'Confirmed', 'Processing', 'Shipped'], true)) {
                $counts['pending'] += $count;
            } elseif ($row['status'] === 'Cancelled') {
                $counts['cancelled'] += $count;
            } elseif ($row['status'] === 'Completed') {
                $counts['completed'] += $count;
            }
        }

        return $counts;
    }

    private function createFilteredQueryBuilder(
        string $search,
        string $status,
        string $paymentStatus,
        ?\DateTimeImmutable $dateFrom,
        ?\DateTimeImmutable $dateToExclusive,
    ): QueryBuilder {
        $query = $this->createQueryBuilder('customerOrder')
            ->innerJoin('customerOrder.user', 'customer')
            ->leftJoin(OrderItem::class, 'item', 'WITH', 'item.customerOrder = customerOrder')
            ->leftJoin('item.product', 'product');

        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $expressions = [
                'LOWER(CONCAT(CONCAT(customer.firstName, \' \'), customer.lastName)) LIKE :term',
                'LOWER(customer.firstName) LIKE :term',
                'LOWER(customer.lastName) LIKE :term',
                'LOWER(customer.email) LIKE :term',
                'LOWER(customer.phone) LIKE :term',
                'LOWER(product.name) LIKE :term',
            ];
            if (ctype_digit($search)) {
                $expressions[] = 'customerOrder.id = :orderGroupId';
                $query->setParameter('orderGroupId', (int) $search);
            }
            $query->andWhere($query->expr()->orX(...$expressions))->setParameter('term', $term);
        }

        if ($status === 'Pending') {
            $query->andWhere('customerOrder.status IN (:statuses)')->setParameter('statuses', ['Pending', 'Cancellation Pending', 'Confirmed', 'Processing', 'Shipped']);
        } elseif ($status === 'Processing') {
            $query->andWhere('customerOrder.status = :status')->setParameter('status', 'Processing');
        } elseif ($status === 'Cancelled') {
            $query->andWhere('customerOrder.status = :status')->setParameter('status', 'Cancelled');
        } elseif ($status === 'Completed') {
            $query->andWhere('customerOrder.status = :status')->setParameter('status', 'Completed');
        }

        if ($paymentStatus !== '') {
            $query->andWhere('customerOrder.paymentStatus = :paymentStatus')->setParameter('paymentStatus', $paymentStatus);
        }
        if ($dateFrom !== null) {
            $query->andWhere('customerOrder.createdAt >= :dateFrom')->setParameter('dateFrom', $dateFrom);
        }
        if ($dateToExclusive !== null) {
            $query->andWhere('customerOrder.createdAt < :dateToExclusive')->setParameter('dateToExclusive', $dateToExclusive);
        }

        return $query;
    }
}
