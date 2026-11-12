<?php

namespace App\Repository;

use App\Entity\OrderItem;
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
            ->leftJoin('item.customerOrder', 'customerOrder')
            ->addSelect('customerOrder')
            ->andWhere('item.user = :user')
            ->setParameter('user', $user)
            ->orderBy('customerOrder.createdAt', 'DESC')
            ->addOrderBy('item.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<OrderItem> */
    public function findItemsInGroup(OrderItem $order): array
    {
        if (!$order->getCustomerOrder()) {
            return [$order];
        }

        return $this->createQueryBuilder('item')
            ->andWhere('item.customerOrder = :group')
            ->setParameter('group', $order->getCustomerOrder())
            ->getQuery()
            ->getResult();
    }
}
