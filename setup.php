<?php
// setup.php
if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Setup can only be run from this computer.');
}

define('MOTOWORKS_SETUP', true);
require_once __DIR__ . '/includes/config.php';

try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $pdo->exec("USE " . DB_NAME);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `user` (
            `userId` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `userFirstName` VARCHAR(50) NOT NULL,
            `userLastName` VARCHAR(50) NOT NULL,
            `userEmail` VARCHAR(100) NOT NULL UNIQUE,
            `userPhone` VARCHAR(20) NOT NULL,
            `userAddress` TEXT DEFAULT NULL,
            `userPassword` VARCHAR(255) NOT NULL,
            `userRole` ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer',
            `userRememberToken` VARCHAR(255) DEFAULT NULL,
            `userResetToken` VARCHAR(255) DEFAULT NULL,
            `userResetExpiry` DATETIME DEFAULT NULL,
            `userAvatar` VARCHAR(255) DEFAULT NULL,
            `userCreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("ALTER TABLE `user` MODIFY `userRole` ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer'");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `motorcycle` (
            `motorcycleId` INT(11) NOT NULL AUTO_INCREMENT,
            `userId` INT(11) NOT NULL,
            `motorcycleModel` VARCHAR(255) NOT NULL,
            `motorcyclePlateNumber` VARCHAR(32) DEFAULT NULL,
            `motorcycleCreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`motorcycleId`),
            UNIQUE KEY `uq_motorcycle_plate_number` (`motorcyclePlateNumber`),
            KEY `idx_motorcycle_user` (`userId`),
            CONSTRAINT `fk_motorcycle_user`
                FOREIGN KEY (`userId`) REFERENCES `user` (`userId`)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $ordersTable = $pdo->query("SHOW TABLES LIKE 'order'")->fetchColumn();
    if ($ordersTable) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `customer_order` (
                `orderGroupId` INT(11) NOT NULL AUTO_INCREMENT,
                `userId` INT(11) NOT NULL,
                `orderDeliveryAddress` TEXT DEFAULT NULL,
                `orderPaymentMethod` VARCHAR(50) DEFAULT NULL,
                `orderPaymentReference` VARCHAR(32) DEFAULT NULL,
                `orderPaymentStatus` VARCHAR(32) NOT NULL DEFAULT 'Unverified',
                `orderStatus` VARCHAR(50) NOT NULL DEFAULT 'Pending',
                `orderCancellationReason` TEXT DEFAULT NULL,
                `orderCreatedByStaffId` INT(11) DEFAULT NULL,
                `orderCreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`orderGroupId`),
                KEY `idx_customer_order_user` (`userId`),
                KEY `idx_customer_order_status` (`orderStatus`),
                KEY `idx_customer_order_date` (`orderCreatedAt`),
                KEY `idx_customer_order_staff_creator` (`orderCreatedByStaffId`),
                CONSTRAINT `fk_customer_order_user`
                    FOREIGN KEY (`userId`) REFERENCES `user` (`userId`)
                    ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `fk_customer_order_staff_creator`
                    FOREIGN KEY (`orderCreatedByStaffId`) REFERENCES `user` (`userId`)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $staffOrderCreatorColumn = $pdo->query("SHOW COLUMNS FROM `customer_order` LIKE 'orderCreatedByStaffId'")->fetchColumn();
        if (!$staffOrderCreatorColumn) {
            $pdo->exec("
                ALTER TABLE `customer_order`
                ADD COLUMN `orderCreatedByStaffId` INT(11) DEFAULT NULL,
                ADD KEY `idx_customer_order_staff_creator` (`orderCreatedByStaffId`),
                ADD CONSTRAINT `fk_customer_order_staff_creator`
                    FOREIGN KEY (`orderCreatedByStaffId`) REFERENCES `user` (`userId`)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ");
        }

        $orderGroupColumn = $pdo->query("SHOW COLUMNS FROM `order` LIKE 'orderGroupId'")->fetchColumn();
        if (!$orderGroupColumn) {
            $pdo->exec("ALTER TABLE `order` ADD COLUMN `orderGroupId` INT(11) DEFAULT NULL AFTER `orderCreatedAt`");
        }

        $orderUnitPriceColumn = $pdo->query("SHOW COLUMNS FROM `order` LIKE 'orderUnitPrice'")->fetchColumn();
        if (!$orderUnitPriceColumn) {
            $pdo->exec("ALTER TABLE `order` ADD COLUMN `orderUnitPrice` DECIMAL(10,2) DEFAULT NULL AFTER `orderGroupId`");
        }

        $orderGroupIndex = $pdo->query("SHOW INDEX FROM `order` WHERE Key_name = 'idx_order_group'")->fetchColumn();
        if (!$orderGroupIndex) {
            $pdo->exec("ALTER TABLE `order` ADD KEY `idx_order_group` (`orderGroupId`)");
        }

        $orderGroupForeignKey = $pdo->query("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'order'
              AND CONSTRAINT_NAME = 'fk_order_group'
            LIMIT 1
        ")->fetchColumn();
        if (!$orderGroupForeignKey) {
            $pdo->exec("
                ALTER TABLE `order`
                ADD CONSTRAINT `fk_order_group`
                    FOREIGN KEY (`orderGroupId`) REFERENCES `customer_order` (`orderGroupId`)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            ");
        }

        $pdo->exec("
            INSERT INTO `customer_order`
                (orderGroupId, userId, orderDeliveryAddress, orderPaymentMethod,
                 orderPaymentReference, orderPaymentStatus, orderStatus, orderCreatedAt)
            SELECT MIN(o.orderId), o.userId, MAX(u.userAddress), MAX(o.orderPaymentMethod),
                   o.orderPaymentReference,
                   CASE WHEN MAX(o.orderPaymentMethod) = 'Cash on Delivery' THEN 'Due on delivery' ELSE 'Unverified' END,
                   MAX(o.orderStatus), MIN(o.orderCreatedAt)
            FROM `order` o
            INNER JOIN `user` u ON u.userId = o.userId
            LEFT JOIN `customer_order` existing_group
              ON existing_group.userId = o.userId
             AND existing_group.orderPaymentReference = o.orderPaymentReference
            WHERE o.orderGroupId IS NULL
              AND NULLIF(o.orderPaymentReference, '') IS NOT NULL
              AND existing_group.orderGroupId IS NULL
            GROUP BY o.userId, o.orderPaymentReference
        ");

        $pdo->exec("
            INSERT INTO `customer_order`
                (orderGroupId, userId, orderDeliveryAddress, orderPaymentMethod,
                 orderPaymentReference, orderPaymentStatus, orderStatus, orderCreatedAt)
            SELECT o.orderId, o.userId, u.userAddress, o.orderPaymentMethod, NULL,
                   CASE WHEN o.orderPaymentMethod = 'Cash on Delivery' THEN 'Due on delivery' ELSE 'Unverified' END,
                   o.orderStatus, o.orderCreatedAt
            FROM `order` o
            INNER JOIN `user` u ON u.userId = o.userId
            LEFT JOIN `customer_order` existing_group ON existing_group.orderGroupId = o.orderId
            WHERE o.orderGroupId IS NULL
              AND NULLIF(o.orderPaymentReference, '') IS NULL
              AND existing_group.orderGroupId IS NULL
        ");

        $pdo->exec("
            UPDATE `order` o
            INNER JOIN `customer_order` co
              ON co.userId = o.userId
             AND co.orderPaymentReference = o.orderPaymentReference
            SET o.orderGroupId = co.orderGroupId
            WHERE o.orderGroupId IS NULL
              AND NULLIF(o.orderPaymentReference, '') IS NOT NULL
        ");
        $pdo->exec("
            UPDATE `order` o
            INNER JOIN `customer_order` co ON co.orderGroupId = o.orderId
            SET o.orderGroupId = co.orderGroupId
            WHERE o.orderGroupId IS NULL
              AND NULLIF(o.orderPaymentReference, '') IS NULL
        ");
        $pdo->exec("
            UPDATE `order`
            SET orderUnitPrice = ROUND(orderTotalAmount / GREATEST(orderQuantity, 1), 2)
            WHERE orderUnitPrice IS NULL
        ");
        $pdo->exec("
            UPDATE `order` o
            INNER JOIN `customer_order` co ON co.orderGroupId = o.orderGroupId
            SET o.orderStatus = co.orderStatus
            WHERE o.orderStatus <> co.orderStatus
        ");
    }

    $bookingTable = $pdo->query("SHOW TABLES LIKE 'service_booking'")->fetchColumn();
    if ($bookingTable) {
        $staffCreatorColumn = $pdo->query("SHOW COLUMNS FROM `service_booking` LIKE 'createdByStaffId'")->fetchColumn();
        if (!$staffCreatorColumn) {
            $pdo->exec("
                ALTER TABLE `service_booking`
                ADD COLUMN `createdByStaffId` INT(11) DEFAULT NULL,
                ADD KEY `fk_booking_staff_creator` (`createdByStaffId`),
                ADD CONSTRAINT `fk_booking_staff_creator`
                    FOREIGN KEY (`createdByStaffId`) REFERENCES `user` (`userId`)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ");
        }

        $bookingMotorcycleColumn = $pdo->query("SHOW COLUMNS FROM `service_booking` LIKE 'bookingMotorcycleId'")->fetchColumn();
        if (!$bookingMotorcycleColumn) {
            $pdo->exec("
                    ALTER TABLE `service_booking`
                    ADD COLUMN `bookingMotorcycleId` INT(11) DEFAULT NULL,
                    ADD KEY `idx_booking_motorcycle` (`bookingMotorcycleId`),
                    ADD CONSTRAINT `fk_booking_motorcycle`
                        FOREIGN KEY (`bookingMotorcycleId`) REFERENCES `motorcycle` (`motorcycleId`)
                        ON DELETE SET NULL ON UPDATE CASCADE
            ");
        }

        $bookingRejectionColumn = $pdo->query("SHOW COLUMNS FROM `service_booking` LIKE 'bookingRejectionReason'")->fetchColumn();
        if (!$bookingRejectionColumn) {
            $pdo->exec("ALTER TABLE `service_booking` ADD COLUMN `bookingRejectionReason` TEXT DEFAULT NULL");
        }

        $bookingSourceColumn = $pdo->query("SHOW COLUMNS FROM `service_booking` LIKE 'bookingSource'")->fetchColumn();
        if (!$bookingSourceColumn) {
            $pdo->exec("ALTER TABLE `service_booking` ADD COLUMN `bookingSource` VARCHAR(32) NOT NULL DEFAULT 'customer_online'");
        }
        $pdo->exec("UPDATE service_booking SET bookingSource = 'staff' WHERE createdByStaffId IS NOT NULL AND bookingSource = 'customer_online'");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `service_record` (
            `recordId` INT(11) NOT NULL AUTO_INCREMENT,
            `customerId` INT(11) NOT NULL,
            `motorcycleId` INT(11) DEFAULT NULL,
            `serviceId` INT(11) NOT NULL,
            `createdByStaffId` INT(11) NOT NULL,
            `recordMotorcycleModel` VARCHAR(255) NOT NULL,
            `recordPlateNumber` VARCHAR(32) DEFAULT NULL,
            `recordServiceDate` DATE NOT NULL,
            `recordWorkDescription` TEXT NOT NULL,
            `recordPartsUsed` TEXT DEFAULT NULL,
            `recordCost` DECIMAL(10,2) DEFAULT NULL,
            `recordStatus` VARCHAR(50) NOT NULL DEFAULT 'In Progress',
            `recordNotes` TEXT DEFAULT NULL,
            `recordCreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `recordUpdatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`recordId`),
            KEY `idx_service_record_customer` (`customerId`),
            KEY `idx_service_record_motorcycle` (`motorcycleId`),
            KEY `idx_service_record_service` (`serviceId`),
            KEY `idx_service_record_staff` (`createdByStaffId`),
            KEY `idx_service_record_status` (`recordStatus`),
            CONSTRAINT `fk_service_record_customer`
                    FOREIGN KEY (`customerId`) REFERENCES `user` (`userId`)
                    ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `fk_service_record_motorcycle`
                    FOREIGN KEY (`motorcycleId`) REFERENCES `motorcycle` (`motorcycleId`)
                    ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `fk_service_record_service`
                    FOREIGN KEY (`serviceId`) REFERENCES `service` (`serviceId`)
                    ON DELETE RESTRICT ON UPDATE CASCADE,
            CONSTRAINT `fk_service_record_staff`
                    FOREIGN KEY (`createdByStaffId`) REFERENCES `user` (`userId`)
                    ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `promo` (
            `promoId` INT(11) NOT NULL AUTO_INCREMENT,
            `promoSlug` VARCHAR(100) NOT NULL,
            `promoTitle` VARCHAR(255) NOT NULL,
            `promoBadge` VARCHAR(50) NOT NULL,
            `promoDescription` TEXT NOT NULL,
            `promoItemLabel` VARCHAR(255) NOT NULL,
            `promoItemOptions` TEXT NOT NULL,
            `promoPerks` TEXT DEFAULT NULL,
            `promoProductImage` VARCHAR(255) DEFAULT NULL,
            `promoServiceImage` VARCHAR(255) DEFAULT NULL,
            `promoProductIcon` VARCHAR(100) DEFAULT NULL,
            `promoServiceIcon` VARCHAR(100) DEFAULT NULL,
            `promoActive` TINYINT(1) NOT NULL DEFAULT 1,
            `promoCreatedByStaffId` INT(11) DEFAULT NULL,
            `promoCreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`promoId`),
            UNIQUE KEY `uq_promo_slug` (`promoSlug`),
            KEY `idx_promo_created_by_staff` (`promoCreatedByStaffId`),
            CONSTRAINT `fk_promo_created_by_staff` FOREIGN KEY (`promoCreatedByStaffId`)
                REFERENCES `user` (`userId`) ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $promoOwnerColumn = $pdo->query("SHOW COLUMNS FROM `promo` LIKE 'promoCreatedByStaffId'")->fetch(PDO::FETCH_ASSOC);
    if (!$promoOwnerColumn) {
        $pdo->exec("
            ALTER TABLE `promo`
            ADD `promoCreatedByStaffId` INT(11) DEFAULT NULL,
            ADD KEY `idx_promo_created_by_staff` (`promoCreatedByStaffId`),
            ADD CONSTRAINT `fk_promo_created_by_staff` FOREIGN KEY (`promoCreatedByStaffId`)
                REFERENCES `user` (`userId`) ON DELETE SET NULL ON UPDATE CASCADE
        ");
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM `promo`')->fetchColumn() === 0) {
        $pdo->exec("
        INSERT INTO `promo`
            (`promoId`, `promoSlug`, `promoTitle`, `promoBadge`, `promoDescription`, `promoItemLabel`,
             `promoItemOptions`, `promoPerks`, `promoProductImage`, `promoServiceImage`,
             `promoProductIcon`, `promoServiceIcon`, `promoActive`)
        VALUES
            (1, 'tire_service', 'Tire + Mounting Service Bundle', '15% OFF',
             'Purchase any eligible motorcycle tire and pair it with a professional mounting, balancing, and alignment service at the same time.',
             'Select Tire Model / Size',
             'Michelin Pilot Street 2 (90/80-14 Tubeless)\nPirelli Diablo Rosso Sport (100/80-14 Tubeless)\nIRC NR73 Tubeless Tire (80/90-17)',
             'Eligible on all standard and tubeless tires\nIncludes labor and wheel balancing adjustment',
             'images/featured_products/tire.webp', 'images/featured_services/wheel_and_tire_service.png',
             'fa-solid fa-circle-dot', 'fa-solid fa-screwdriver-wrench', 1),
            (2, 'oil_tuneup', 'Synthetic Oil Change + Tune-Up', '20% OFF',
             'Keep your engine running smoothly with high-grade synthetic oil paired with a complete carb/FI diagnostic tune-up service.',
             'Select Oil Viscosity & Type',
             'Motul 7100 4T 10W-40 Synthetic (1L)\nShell Advance Ultra 10W-40 Fully Synthetic (1L)\nCastrol Power1 Ultimate 10W-50 (1L)',
             'Includes 1L high-performance synthetic oil\nFull engine diagnostic & idle tuning',
             'images/featured_products/synthetic_oil.png', 'images/featured_services/premium_oil_and_change_service.png',
             'fa-solid fa-oil-can', 'fa-solid fa-gauge-high', 1),
            (3, 'chain_sprocket', 'Heavy Duty Chain Kit + Install', '10% OFF',
             'Upgrade your drive train with a heavy-duty chain and sprocket set bundled together with precision professional installation.',
             'Select Chain & Sprocket Set',
             'SSS Heavy Duty Chain & Sprocket Set (428H x 14T/38T)\nDID O-Ring Heavy Duty Chain Combo (520V)\nRK Racing Gold Chain & Steel Sprocket Kit',
             'O-ring or X-ring heavy duty durability\nIncludes chain cleaning and tension adjustment',
             'images/products/chain.png', 'images/services/chain.png',
             'fa-solid fa-link', 'fa-solid fa-gear', 1),
            (4, 'brake_overhaul', 'Brake Pad Set + Fluid Bleeding', '15% OFF',
             'Ensure maximum stopping power by bundling front/rear ceramic brake pads with a complete hydraulic fluid flush and bleed service.',
             'Select Brake Pad Compound',
             'Brembo Ceramic Front/Rear Brake Pads\nEBC FA Series Sintered Brake Pads\nBendix Heavy Duty Street Moto Pads',
             'High-friction ceramic compound pads\nComplete DOT-4 brake fluid replacement',
             'images/products/brake.png', 'images/services/brake.png',
             'fa-solid fa-compact-disc', 'fa-solid fa-droplet', 1)
        ");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `promo_claim` (
            `claimId` INT(11) NOT NULL AUTO_INCREMENT,
            `userId` INT(11) DEFAULT NULL,
            `claimFullName` VARCHAR(255) NOT NULL,
            `claimPhone` VARCHAR(50) NOT NULL,
            `claimBundleSlug` VARCHAR(100) NOT NULL,
            `claimSelectedOption` VARCHAR(255) NOT NULL,
            `claimAppointmentDate` DATE NOT NULL,
            `claimAppointmentTime` TIME NOT NULL,
            `claimStatus` VARCHAR(50) DEFAULT 'Pending',
            `claimCreatedByStaffId` INT(11) DEFAULT NULL,
            `claimCreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`claimId`),
            KEY `fk_promo_user` (`userId`),
            KEY `idx_promo_claim_staff_creator` (`claimCreatedByStaffId`),
            CONSTRAINT `fk_promo_user` FOREIGN KEY (`userId`) REFERENCES `user` (`userId`)
                ON DELETE SET NULL ON UPDATE CASCADE,
            CONSTRAINT `fk_promo_claim_staff_creator` FOREIGN KEY (`claimCreatedByStaffId`) REFERENCES `user` (`userId`)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $claimCreatorColumn = $pdo->query("SHOW COLUMNS FROM `promo_claim` LIKE 'claimCreatedByStaffId'")->fetchColumn();
    if (!$claimCreatorColumn) {
        $pdo->exec("
            ALTER TABLE `promo_claim`
            ADD COLUMN `claimCreatedByStaffId` INT(11) DEFAULT NULL,
            ADD KEY `idx_promo_claim_staff_creator` (`claimCreatedByStaffId`),
            ADD CONSTRAINT `fk_promo_claim_staff_creator` FOREIGN KEY (`claimCreatedByStaffId`) REFERENCES `user` (`userId`)
                ON DELETE SET NULL ON UPDATE CASCADE
        ");
    }
    $stmt = $pdo->prepare("SELECT userId FROM `user` WHERE username = ?");
    $stmt->execute([DEFAULT_ADMIN_USERNAME]);

    if (!$stmt->fetch()) {
        if (
            strlen(DEFAULT_ADMIN_PASSWORD) < 12 ||
            !filter_var(DEFAULT_ADMIN_EMAIL, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException('Configure a valid administrator email and a password of at least 12 characters before setup.');
        }

        $hashedPassword = password_hash(DEFAULT_ADMIN_PASSWORD, PASSWORD_DEFAULT);

        $insert = $pdo->prepare("
            INSERT INTO `user` (username, userFirstName, userLastName, userEmail, userPhone, userPassword, userRole)
            VALUES (?, ?, ?, ?, ?, ?, 'admin')
        ");
        $insert->execute([
            DEFAULT_ADMIN_USERNAME,
            DEFAULT_ADMIN_FIRSTNAME,
            DEFAULT_ADMIN_LASTNAME,
            DEFAULT_ADMIN_EMAIL,
            DEFAULT_ADMIN_PHONE,
            $hashedPassword
        ]);
        echo "<h3>Success: Database initialized and default administrator seeded securely!</h3>";
    } else {
        echo "<h3>Notice: Admin account already exists in the database.</h3>";
    }

    if (STAFF_USERNAME !== '' || STAFF_PASSWORD !== '') {
        if (
            STAFF_USERNAME === '' ||
            STAFF_PASSWORD === '' ||
            strlen(STAFF_USERNAME) > 50 ||
            strlen(STAFF_PASSWORD) < 8
        ) {
            throw new RuntimeException('Configure both STAFF_USERNAME and a STAFF_PASSWORD of at least 8 characters.');
        }

        $staffStmt = $pdo->prepare("SELECT userId, userRole FROM `user` WHERE username = ? LIMIT 1");
        $staffStmt->execute([STAFF_USERNAME]);
        $staffAccount = $staffStmt->fetch(PDO::FETCH_ASSOC);

        if (!$staffAccount) {
            $staffEmail = 'staff@example.invalid';
            $emailStmt = $pdo->prepare('SELECT userId FROM `user` WHERE userEmail = ? LIMIT 1');
            $emailStmt->execute([$staffEmail]);
            if ($emailStmt->fetchColumn()) {
                throw new RuntimeException('The reserved Staff demo email is already in use.');
            }

            $insertStaff = $pdo->prepare("
                INSERT INTO `user` (username, userFirstName, userLastName, userEmail, userPhone, userPassword, userRole)
                VALUES (?, 'Demo', 'Staff', ?, '', ?, 'staff')
            ");
            $insertStaff->execute([
                STAFF_USERNAME,
                $staffEmail,
                password_hash(STAFF_PASSWORD, PASSWORD_DEFAULT),
            ]);
            echo "<h3>Success: Staff account created securely.</h3>";
        } elseif ($staffAccount['userRole'] !== 'staff') {
            throw new RuntimeException('The configured Staff username already belongs to a different role.');
        } else {
            echo "<h3>Notice: Staff account already exists; its password was not changed.</h3>";
        }
    }

    echo "<br><a href='login.php'>Go to Login</a> | <a href='index.php'>Go to Home</a>";

} catch (Throwable $e) {
    error_log('Database setup failed: ' . $e->getMessage());
    http_response_code(500);
    echo "Database setup failed. Check the server error log for details.";
}
?>