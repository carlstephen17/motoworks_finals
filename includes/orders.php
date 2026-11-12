<?php

function updateCustomerOrderStatus(
    PDO $pdo,
    int $orderGroupId,
    string $nextStatus,
    string $cancellationReason = ''
): string {
    $validStatuses = ['Pending', 'Cancelled', 'Completed'];
    if (!in_array($nextStatus, $validStatuses, true)) {
        throw new DomainException('Invalid order status.');
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $orderStmt = $pdo->prepare("
            SELECT orderStatus, orderPaymentStatus, orderCancellationReason
            FROM customer_order
            WHERE orderGroupId = ?
            FOR UPDATE
        ");
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
        if ($currentStatus === null || $currentStatus === 'Completed') {
            throw new DomainException('Order cannot be updated from its current status.');
        }

        if ($nextStatus === 'Cancelled' && $currentStatus !== 'Cancelled') {
            $cancellationReason = trim($cancellationReason);
            if ($cancellationReason === '' || strlen($cancellationReason) > 500) {
                throw new DomainException('Provide a cancellation reason of no more than 500 characters.');
            }
        } elseif ($nextStatus === 'Cancelled') {
            $cancellationReason = trim($cancellationReason);
            if ($cancellationReason === '') {
                $cancellationReason = (string) ($order['orderCancellationReason'] ?? '');
            }
        } elseif ($nextStatus !== 'Cancelled' && $cancellationReason !== '') {
            throw new DomainException('A cancellation reason is only accepted when cancelling an order.');
        }

        $items = [];
        if ($nextStatus === 'Cancelled') {
            $itemsStmt = $pdo->prepare("
                SELECT productId, orderQuantity
                FROM `order`
                WHERE orderGroupId = ?
                ORDER BY productId
                FOR UPDATE
            ");
            $itemsStmt->execute([$orderGroupId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$items) {
                throw new RuntimeException('Order has no inventory items to restore.');
            }
        } elseif ($currentStatus === 'Cancelled' && $nextStatus !== 'Cancelled') {
            $itemsStmt = $pdo->prepare("
                SELECT productId, SUM(orderQuantity) AS quantity
                FROM `order`
                WHERE orderGroupId = ?
                GROUP BY productId
                ORDER BY productId
                FOR UPDATE
            ");
            $itemsStmt->execute([$orderGroupId]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$items) {
                throw new RuntimeException('Order has no inventory items to reserve.');
            }
        }

        if ($currentStatus === 'Pending' && $nextStatus === 'Cancelled') {
            $stockStmt = $pdo->prepare("
                UPDATE product
                SET productStock = productStock + ?
                WHERE productId = ?
            ");
            foreach ($items as $item) {
                $stockStmt->execute([(int) $item['orderQuantity'], (int) $item['productId']]);
                if ($stockStmt->rowCount() !== 1) {
                    throw new RuntimeException('Unable to restore inventory for a cancelled order.');
                }
            }
        } elseif ($currentStatus === 'Cancelled' && $nextStatus !== 'Cancelled') {
            $stockStmt = $pdo->prepare("
                UPDATE product
                SET productStock = productStock - ?
                WHERE productId = ? AND productStock >= ?
            ");
            foreach ($items as $item) {
                $quantity = (int) $item['quantity'];
                $stockStmt->execute([$quantity, (int) $item['productId'], $quantity]);
                if ($stockStmt->rowCount() !== 1) {
                    throw new DomainException('Insufficient stock to reactivate this cancelled order.');
                }
            }
        }

        $updateHeader = $pdo->prepare("
            UPDATE customer_order
            SET orderStatus = ?, orderCancellationReason = ?
            WHERE orderGroupId = ?
        ");
        $updateHeader->execute([
            $nextStatus,
            $nextStatus === 'Cancelled' ? $cancellationReason : null,
            $orderGroupId,
        ]);

        $updateItems = $pdo->prepare("
            UPDATE `order`
            SET orderStatus = ?
            WHERE orderGroupId = ?
        ");
        $updateItems->execute([$nextStatus, $orderGroupId]);
        if ($updateItems->rowCount() < 1) {
            throw new RuntimeException('The order has no items to update.');
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return "Order #{$orderGroupId} updated to {$nextStatus}.";
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
