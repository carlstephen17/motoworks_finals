<?php

namespace App\Repository;

use App\Entity\OrderItem;
use App\Entity\CustomerOrder;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 */
class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    /** @return list<OrderItem> */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('item')
            ->innerJoin('item.customerOrder', 'customerOrder')
            ->addSelect('customerOrder')
            ->innerJoin('item.product', 'product')
            ->addSelect('product')
            ->andWhere('item.user = :user')
            ->setParameter('user', $user)
            ->orderBy('customerOrder.id', 'ASC')
            ->addOrderBy('item.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<OrderItem> */
    public function findItemsInGroup(OrderItem|CustomerOrder $order): array
    {
        $group = $order instanceof CustomerOrder ? $order : $order->getCustomerOrder();
        if (!$group instanceof CustomerOrder) {
            return [$order];
        }

        return $this->createQueryBuilder('item')
            ->andWhere('item.customerOrder = :group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getResult();
    }
}
