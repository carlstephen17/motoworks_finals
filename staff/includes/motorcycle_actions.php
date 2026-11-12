<?php

function getOrCreateCustomerMotorcycle(
    PDO $pdo,
    int $customerId,
    string $mode,
    int $motorcycleId,
    string $model,
    string $plate
): array {
    if ($mode === 'existing') {
        $stmt = $pdo->prepare('SELECT motorcycleId, motorcycleModel, motorcyclePlateNumber FROM motorcycle WHERE motorcycleId = ? AND userId = ?');
        $stmt->execute([$motorcycleId, $customerId]);
        $motorcycle = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$motorcycle) {
            throw new InvalidArgumentException('Choose a motorcycle that belongs to the selected customer.');
        }
        return $motorcycle;
    }

    $duplicateSql = 'SELECT motorcycleId, motorcycleModel, motorcyclePlateNumber FROM motorcycle WHERE userId = ? AND LOWER(TRIM(motorcycleModel)) = LOWER(TRIM(?))';
    $duplicateParams = [$customerId, $model];
    if ($plate !== '') {
        $duplicateSql .= ' AND motorcyclePlateNumber = ?';
        $duplicateParams[] = $plate;
    } else {
        $duplicateSql .= ' AND motorcyclePlateNumber IS NULL';
    }
    $duplicateSql .= ' LIMIT 1';
    $stmt = $pdo->prepare($duplicateSql);
    $stmt->execute($duplicateParams);
    $motorcycle = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($motorcycle) {
        return $motorcycle;
    }

    $insert = $pdo->prepare('INSERT INTO motorcycle (userId, motorcycleModel, motorcyclePlateNumber) VALUES (?, ?, ?)');
    $insert->execute([$customerId, $model, $plate !== '' ? $plate : null]);
    return [
        'motorcycleId' => (int) $pdo->lastInsertId(),
        'motorcycleModel' => $model,
        'motorcyclePlateNumber' => $plate !== '' ? $plate : null,
    ];
}
