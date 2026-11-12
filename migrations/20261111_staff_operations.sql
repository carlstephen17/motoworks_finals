-- Add Staff motorcycle and service-record support without modifying existing bookings.
-- Run once against an existing MotoWorks database after taking a database backup.

CREATE TABLE IF NOT EXISTS `motorcycle` (
  `motorcycleId` INT(11) NOT NULL AUTO_INCREMENT,
  `userId` INT(11) NOT NULL,
  `motorcycleModel` VARCHAR(255) NOT NULL,
  `motorcyclePlateNumber` VARCHAR(32) DEFAULT NULL,
  `motorcycleCreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`motorcycleId`),
  UNIQUE KEY `uq_motorcycle_plate_number` (`motorcyclePlateNumber`),
  KEY `idx_motorcycle_user` (`userId`),
  CONSTRAINT `fk_motorcycle_user` FOREIGN KEY (`userId`) REFERENCES `user` (`userId`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `service_booking`
  ADD COLUMN `bookingMotorcycleId` INT(11) DEFAULT NULL,
  ADD COLUMN `bookingRejectionReason` TEXT DEFAULT NULL,
  ADD COLUMN `bookingSource` VARCHAR(32) NOT NULL DEFAULT 'customer_online',
  ADD KEY `idx_booking_motorcycle` (`bookingMotorcycleId`),
  ADD CONSTRAINT `fk_booking_motorcycle` FOREIGN KEY (`bookingMotorcycleId`) REFERENCES `motorcycle` (`motorcycleId`) ON DELETE SET NULL ON UPDATE CASCADE;

UPDATE `service_booking`
SET `bookingSource` = 'staff'
WHERE `createdByStaffId` IS NOT NULL AND `bookingSource` = 'customer_online';

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
  CONSTRAINT `fk_service_record_customer` FOREIGN KEY (`customerId`) REFERENCES `user` (`userId`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_service_record_motorcycle` FOREIGN KEY (`motorcycleId`) REFERENCES `motorcycle` (`motorcycleId`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_service_record_service` FOREIGN KEY (`serviceId`) REFERENCES `service` (`serviceId`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_service_record_staff` FOREIGN KEY (`createdByStaffId`) REFERENCES `user` (`userId`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
