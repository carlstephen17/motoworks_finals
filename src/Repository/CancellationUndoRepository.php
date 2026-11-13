<?php

namespace App\Repository;

use App\Entity\CancellationUndo;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CancellationUndo>
 */
class CancellationUndoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CancellationUndo::class);
    }

    /** @return list<CancellationUndo> */
    public function findPendingForUser(User $user): array
    {
        return $this->createQueryBuilder('undo')
            ->andWhere('undo.user = :user')
            ->andWhere('undo.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'Pending')
            ->orderBy('undo.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingForRecord(string $recordType, int $recordId): ?CancellationUndo
    {
        return $this->findOneBy([
            'recordType' => $recordType,
            'recordId' => $recordId,
            'status' => 'Pending',
        ]);
    }

    /** @return list<string> */
    public function findExpiredIds(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('undo')
            ->select('undo.id')
            ->andWhere('undo.status = :status')
            ->andWhere('undo.expiresAt <= :now')
            ->setParameter('status', 'Pending')
            ->setParameter('now', $now)
            ->orderBy('undo.expiresAt', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }
}
