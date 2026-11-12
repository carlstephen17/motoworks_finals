<?php

function deleteCustomerOrder(PDO $pdo, int $orderGroupId): string
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $orderStmt = $pdo->prepare('SELECT orderStatus FROM customer_order WHERE orderGroupId = ? FOR UPDATE');
        $orderStmt->execute([$orderGroupId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new DomainException('Order not found.');
        }

        $storedStatus = strtolower(trim((string) $order['orderStatus']));
        $currentStatus = match ($storedStatus) {
            'pending', 'cancelled', 'completed' => ucfirst($storedStatus),
            'confirmed', 'processing', 'shipped' => 'Pending',
            default => null,
        };
        if ($currentStatus === null) {
            throw new DomainException('Order cannot be deleted from its current status.');
        }

        $itemsStmt = $pdo->prepare("
            SELECT productId, orderQuantity
            FROM `order`
            WHERE orderGroupId = ?
            ORDER BY productId
            FOR UPDATE
        ");
        $itemsStmt->execute([$orderGroupId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        if ($currentStatus === 'Pending') {
            $stockStmt = $pdo->prepare('UPDATE product SET productStock = productStock + ? WHERE productId = ?');
            foreach ($items as $item) {
                $stockStmt->execute([(int) $item['orderQuantity'], (int) $item['productId']]);
                if ($stockStmt->rowCount() !== 1) {
                    throw new RuntimeException('Unable to restore inventory while deleting this order.');
                }
            }
        }

        $pdo->prepare('DELETE FROM `order` WHERE orderGroupId = ?')->execute([$orderGroupId]);
        $deleteStmt = $pdo->prepare('DELETE FROM customer_order WHERE orderGroupId = ?');
        $deleteStmt->execute([$orderGroupId]);
        if ($deleteStmt->rowCount() !== 1) {
            throw new RuntimeException('Unable to delete the order record.');
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
        return "Order #{$orderGroupId} deleted.";
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
