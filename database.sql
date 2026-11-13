-- Create missing tables without deleting existing application data.
CREATE TABLE IF NOT EXISTS `user` (
  `userId` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `userFirstName` VARCHAR(50) NOT NULL,
  `userLastName` VARCHAR(50) NOT NULL,
  `userEmail` VARCHAR(100) NOT NULL UNIQUE,
  `userPhone` VARCHAR(20) DEFAULT NULL,
  `userAddress` TEXT DEFAULT NULL,
  `userPassword` VARCHAR(255) NOT NULL,
  `userRole` ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer',
  `userRememberToken` VARCHAR(255) DEFAULT NULL,
  `userResetToken` VARCHAR(255) DEFAULT NULL,
  `userResetExpiry` DATETIME DEFAULT NULL,
  `userAvatar` VARCHAR(255) DEFAULT NULL,
  `userCreatedAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PRODUCT TABLE
CREATE TABLE IF NOT EXISTS `product` (
  `productId` INT(11) NOT NULL AUTO_INCREMENT,
  `productName` VARCHAR(255) NOT NULL,
  `productPrice` DECIMAL(10,2) NOT NULL,
  `productStock` INT(11) NOT NULL DEFAULT 0,
  `productImage` VARCHAR(255) DEFAULT NULL,
  `productActive` TINYINT(1) DEFAULT 1,
  PRIMARY KEY (`productId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CUSTOMER ORDER HEADER (ONE ROW PER CHECKOUT)
CREATE TABLE IF NOT EXISTS `customer_order` (
  `orderGroupId` INT(11) NOT NULL AUTO_INCREMENT,
  `userId` INT(11) NOT NULL,
  `orderDeliveryAddress` TEXT DEFAULT NULL,
  `orderPaymentMethod` VARCHAR(50) DEFAULT NULL,
  `orderPaymentReference` VARCHAR(32) DEFAULT NULL,
  `orderPaymentStatus` VARCHAR(32) NOT NULL DEFAULT 'Pending',
  `orderStatus` VARCHAR(50) NOT NULL DEFAULT 'Pending',
  `orderCancellationReason` TEXT DEFAULT NULL,
  `orderCreatedByStaffId` INT(11) DEFAULT NULL,
  `orderCreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`orderGroupId`),
  KEY `idx_customer_order_user` (`userId`),
  KEY `idx_customer_order_status` (`orderStatus`),
  KEY `idx_customer_order_date` (`orderCreatedAt`),
  KEY `idx_customer_order_staff_creator` (`orderCreatedByStaffId`),
  CONSTRAINT `fk_customer_order_user` FOREIGN KEY (`userId`) REFERENCES `user` (`userId`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_customer_order_staff_creator` FOREIGN KEY (`orderCreatedByStaffId`) REFERENCES `user` (`userId`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SERVICE TABLE
CREATE TABLE IF NOT EXISTS `service` (
  `serviceId` INT(11) NOT NULL AUTO_INCREMENT,
  `serviceName` VARCHAR(255) NOT NULL,
  `serviceImage` VARCHAR(255) DEFAULT NULL,
  `serviceDescription` TEXT DEFAULT NULL,
  `servicePrice` DECIMAL(10,2) NOT NULL,
  `serviceStatus` VARCHAR(50) DEFAULT 'Available',
  `serviceActive` TINYINT(1) DEFAULT 1,
  `serviceFeatured` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`serviceId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CUSTOMER MOTORCYCLE REGISTRY
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

-- ORDER TABLE
CREATE TABLE IF NOT EXISTS `order` (
  `orderId` INT(11) NOT NULL AUTO_INCREMENT,
  `userId` INT(11) NOT NULL,
  `productId` INT(11) NOT NULL,
  `orderQuantity` INT(11) NOT NULL DEFAULT 1,
  `orderTotalAmount` DECIMAL(10,2) NOT NULL,
  `orderPaymentMethod` VARCHAR(50) DEFAULT NULL,
  `orderPaymentReference` VARCHAR(32) DEFAULT NULL,
  `orderStatus` VARCHAR(50) DEFAULT 'Pending',
  `orderCreatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `orderGroupId` INT(11) DEFAULT NULL,
  `orderUnitPrice` DECIMAL(10,2) DEFAULT NULL,
  PRIMARY KEY (`orderId`),
  KEY `fk_orders_user` (`userId`),
  KEY `fk_orders_product` (`productId`),
  KEY `idx_order_group` (`orderGroupId`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`userId`) REFERENCES `user` (`userId`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_product` FOREIGN KEY (`productId`) REFERENCES `product` (`productId`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_group` FOREIGN KEY (`orderGroupId`) REFERENCES `customer_order` (`orderGroupId`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SERVICE BOOKING TABLE
CREATE TABLE IF NOT EXISTS `service_booking` (
  `bookingId` INT(11) NOT NULL AUTO_INCREMENT,
  `userId` INT(11) NOT NULL,
  `serviceId` INT(11) NOT NULL,
  `bookingVehicleModel` VARCHAR(255) DEFAULT NULL,
  `bookingDate` DATE NOT NULL,
  `bookingTime` TIME NOT NULL,
  `bookingBundleSlug` VARCHAR(100) DEFAULT NULL,
  `bookingBundleOption` VARCHAR(255) DEFAULT NULL,
  `bookingStatus` VARCHAR(50) DEFAULT 'Pending',
  `bookingNotes` TEXT DEFAULT NULL,
  `bookingRejectionReason` TEXT DEFAULT NULL,
  `bookingSource` VARCHAR(32) NOT NULL DEFAULT 'customer_online',
  `bookingMotorcycleId` INT(11) DEFAULT NULL,
  `createdByStaffId` INT(11) DEFAULT NULL,
  `bookingCreatedAt` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`bookingId`),
  KEY `fk_bookings_user` (`userId`),
  KEY `fk_bookings_service` (`serviceId`),
  KEY `fk_booking_staff_creator` (`createdByStaffId`),
  KEY `idx_booking_motorcycle` (`bookingMotorcycleId`),
  CONSTRAINT `fk_bookings_user` FOREIGN KEY (`userId`) REFERENCES `user` (`userId`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_service` FOREIGN KEY (`serviceId`) REFERENCES `service` (`serviceId`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_booking_staff_creator` FOREIGN KEY (`createdByStaffId`) REFERENCES `user` (`userId`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_booking_motorcycle` FOREIGN KEY (`bookingMotorcycleId`) REFERENCES `motorcycle` (`motorcycleId`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- STAFF-CREATED SERVICE RECORDS (SEPARATE FROM APPOINTMENTS)
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

-- TESTIMONIAL TABLE
CREATE TABLE IF NOT EXISTS `testimonial` (
  `testimonialId` INT(11) NOT NULL AUTO_INCREMENT,
  `testimonialName` VARCHAR(255) NOT NULL,
  `testimonialEmail` VARCHAR(100) NOT NULL,
  `testimonialSubject` VARCHAR(255) NOT NULL,
  `testimonialMessage` TEXT NOT NULL,
  `testimonialStatus` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `testimonialImage` VARCHAR(255) DEFAULT NULL,
  `testimonialCreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`testimonialId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PROMO CLAIM TABLE
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
  CONSTRAINT `fk_promo_user` FOREIGN KEY (`userId`) REFERENCES `user` (`userId`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_promo_claim_staff_creator` FOREIGN KEY (`claimCreatedByStaffId`) REFERENCES `user` (`userId`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PROMO CATALOG (kept separate from customer promo_claim history)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



USE `bencalo_motoworks_db`;

-- =====================================================
-- 1. CUSTOMER ORDERS
-- =====================================================

INSERT INTO `customer_order`
(`orderGroupId`, `userId`, `orderDeliveryAddress`, `orderPaymentMethod`,
 `orderPaymentReference`, `orderPaymentStatus`, `orderStatus`,
 `orderCancellationReason`, `orderCreatedAt`, `orderCreatedByStaffId`)
VALUES
(1, 2, 'asd', 'Cash on Delivery', 'MWO-20261101-72F5F414', 'Due on delivery', 'Pending', NULL, '2026-11-02 00:45:45', NULL),
(2, 2, 'asd', 'Cash on Delivery', 'MWO-20261101-569C6825', 'Due on delivery', 'Pending', NULL, '2026-11-02 00:51:18', NULL),
(3, 3, 'Bayawan City', 'Cash on Delivery', 'MWO-20261107-59483D02', 'Due on delivery', 'Pending', NULL, '2026-11-07 13:02:24', NULL),
(4, 3, 'Bayawan City', 'Cash on Delivery', 'MWO-20261107-313CC8E5', 'Due on delivery', 'Pending', NULL, '2026-11-07 13:02:43', NULL),
(5, 3, 'Bayawan City', 'Cash on Delivery', 'MWO-20261107-8DDE030A', 'Due on delivery', 'Completed', NULL, '2026-11-07 13:03:07', NULL);


-- =====================================================
-- 2. PRODUCTS
-- =====================================================

INSERT INTO `product`
(`productId`, `productName`, `productPrice`, `productStock`, `productImage`, `productActive`)
VALUES
(1, 'Motorcycle Helmet', 1500.00, 23, 'images/featured_products/helmet.png', 1),
(2, 'H3420 Side Mirror', 350.00, 29, 'images/featured_products/h3420_side_mirror.png', 1),
(3, 'High-Performance Synthetic Engine Oil 1L', 350.00, 40, 'images/featured_products/synthetic_oil.png', 1),
(4, 'Motorcycle Tire', 1200.00, 20, 'images/featured_products/tire.webp', 1),
(5, 'Ceramic Brake Pad Set', 450.00, 30, 'images/products/brake.png', 1),
(6, 'DOT-4 Brake Fluid / Brembo', 180.00, 40, 'images/products/brembo.png', 1),
(7, 'Heavy-Duty Chain and Sprocket Kit', 850.00, 25, 'images/products/chain.png', 1),
(8, 'Iridium Spark Plug', 250.00, 50, 'images/products/iridium.png', 1);


-- =====================================================
-- 3. PROMOTIONS
-- =====================================================

INSERT INTO `promo`
(`promoId`, `promoSlug`, `promoTitle`, `promoBadge`,
 `promoDescription`, `promoItemLabel`, `promoItemOptions`,
 `promoPerks`, `promoProductImage`, `promoServiceImage`,
 `promoProductIcon`, `promoServiceIcon`, `promoActive`,
 `promoCreatedAt`, `promoCreatedByStaffId`)
VALUES

(1, 'tire_service', 'Tire + Mounting Service Bundle', '15% OFF',
'Purchase any eligible motorcycle tire and pair it with a professional mounting, balancing, and alignment service at the same time.',
'Select Tire Model / Size',
'Michelin Pilot Street 2 (90/80-14 Tubeless)
Pirelli Diablo Rosso Sport (100/80-14 Tubeless)
IRC NR73 Tubeless Tire (80/90-17)',
'Eligible on all standard and tubeless tires
Includes labor and wheel balancing adjustment',
'images/featured_products/tire.webp',
'images/featured_services/wheel_and_tire_service.png',
'fa-solid fa-circle-dot',
'fa-solid fa-screwdriver-wrench',
1, '2026-11-12 13:17:05', NULL),

(2, 'oil_tuneup', 'Synthetic Oil Change + Tune-Up', '20% OFF',
'Keep your engine running smoothly with high-grade synthetic oil paired with a complete carb/FI diagnostic tune-up service.',
'Select Oil Viscosity & Type',
'Motul 7100 4T 10W-40 Synthetic (1L)
Shell Advance Ultra 10W-40 Fully Synthetic (1L)
Castrol Power1 Ultimate 10W-50 (1L)',
'Includes 1L high-performance synthetic oil
Full engine diagnostic & idle tuning',
'images/featured_products/synthetic_oil.png',
'images/featured_services/premium_oil_and_change_service.png',
'fa-solid fa-oil-can',
'fa-solid fa-gauge-high',
1, '2026-11-12 13:17:05', NULL),

(3, 'chain_sprocket', 'Heavy Duty Chain Kit + Install', '10% OFF',
'Upgrade your drive train with a heavy-duty chain and sprocket set bundled together with precision professional installation.',
'Select Chain & Sprocket Set',
'SSS Heavy Duty Chain & Sprocket Set (428H x 14T/38T)
DID O-Ring Heavy Duty Chain Combo (520V)
RK Racing Gold Chain & Steel Sprocket Kit',
'O-ring or X-ring heavy duty durability
Includes chain cleaning and tension adjustment',
'images/products/chain.png',
'images/services/chain.png',
'fa-solid fa-link',
'fa-solid fa-gear',
1, '2026-11-12 13:17:05', NULL),

(4, 'brake_overhaul', 'Brake Pad Set + Fluid Bleeding', '15% OFF',
'Ensure maximum stopping power by bundling front/rear ceramic brake pads with a complete hydraulic fluid flush and bleed service.',
'Select Brake Pad Compound',
'Brembo Ceramic Front/Rear Brake Pads
EBC FA Series Sintered Brake Pads
Bendix Heavy Duty Street Moto Pads',
'High-friction ceramic compound pads
Complete DOT-4 brake fluid replacement',
'images/products/brake.png',
'images/services/brake.png',
'fa-solid fa-compact-disc',
'fa-solid fa-droplet',
1, '2026-11-12 13:17:05', NULL);


-- =====================================================
-- 4. SERVICES
-- =====================================================

INSERT INTO `service`
(`serviceId`, `serviceName`, `serviceImage`, `serviceDescription`,
 `servicePrice`, `serviceStatus`, `serviceActive`, `serviceFeatured`)
VALUES
(1, 'Premium Oil & Change Service', 'images/featured_products/synthetic_oil.png', 'Full synthetic oil, new filter, comprehensive check-up.', 450.00, 'Available', 1, 0),
(2, 'Wheel & Tire Service', 'images/featured_products/tire.webp', 'Mounting, precision balancing, and pressure check.', 350.00, 'Available', 1, 0),
(3, 'Vehicle Washing Service', 'images/featured_services/vehicle_washing_service.png', 'Complete exterior wash, foam bath, wheel cleaning, and wax finishing.', 600.00, 'Available', 1, 0),
(4, 'Battery Service', 'images/featured_services/battery_service.png', 'Battery inspection, voltage testing, terminal cleaning, and battery replacement.', 250.00, 'Available', 1, 0),
(5, 'General Maintenance & Tune-Up', 'images/services/general.png', 'Comprehensive inspection, oil change, filter replacement, and engine tuning.', 450.00, 'Available', 1, 0),
(6, 'Chain & Sprocket Replacement', 'images/services/chain.png', 'Removal of worn components, installation of new high-durability chain and sprockets, and tension adjustment.', 200.00, 'Available', 1, 0),
(7, 'Brake System Servicing', 'images/services/brake.png', 'Professional brake pad replacement, rotor inspection, and hydraulic fluid flushing.', 350.00, 'Available', 1, 0),
(8, 'Electrical System & Wiring Repair', 'images/services/electrical.png', 'Diagnostic testing, wiring harness repair, lighting checks, and electrical troubleshooting.', 280.00, 'Available', 1, 0);


-- =====================================================
-- 5. SERVICE BOOKINGS
-- =====================================================

INSERT INTO `service_booking`
(`bookingId`, `userId`, `serviceId`, `bookingVehicleModel`,
 `bookingDate`, `bookingTime`, `bookingBundleSlug`,
 `bookingBundleOption`, `bookingStatus`, `bookingNotes`,
 `bookingCreatedAt`, `createdByStaffId`, `bookingMotorcycleId`,
 `bookingRejectionReason`, `bookingSource`)
VALUES
(1, 3, 1, 'Honda Click', '2026-11-14', '09:00:00',
 NULL, NULL, 'Completed', 'Brake Malfunction',
 '2026-11-06 14:03:52', NULL, NULL, NULL, 'customer_online'),

(3, 3, 1, 'Honda Click', '2026-11-17', '09:00:00',
 'oil_tuneup', 'Motul 7100 4T 10W-40 Synthetic (1L)',
 'Pending', NULL, '2026-11-08 16:59:55',
 NULL, NULL, NULL, 'customer_online');


-- =====================================================
-- 6. TESTIMONIALS
-- =====================================================

INSERT INTO `testimonial`
(`testimonialId`, `testimonialName`, `testimonialEmail`,
 `testimonialSubject`, `testimonialMessage`, `testimonialStatus`,
 `testimonialImage`, `testimonialCreatedAt`)
VALUES
(1, 'Bencalo, Carl Stephen Escaner', 'carlstephenbencalo17@gmail.com', 'Shop', 'carl', 'pending', NULL, '2026-09-09 07:28:22'),

(2, 'Bencalo, Carl Stephen Escaner', 'carlstephenbencalo17@gmail.com', 'Shop', 'Good', 'approved', NULL, '2026-09-09 07:32:11'),

(3, 'Bencalo, Carl Stephen Escaner', 'carlstephenbencalo17@gmail.com', 'Shop', 'Hello', 'approved', NULL, '2026-09-09 07:37:36'),

(4, 'Bencalo, Carl Stephen Escaner', 'carlstephenbencalo17@gmail.com', '', 'GOOD', 'approved', NULL, '2026-09-09 07:44:49'),

(5, 'Bencalo, Carl Stephen Escaner', 'carlstephenbencalo17@gmail.com', '', 'HELLOOOOOO', 'approved', 'images/testimonials/testimonial_1788911308_70000a44.jpg', '2026-09-09 07:48:28'),

(6, 'Bencalo, Carl Stephen Escaner', 'carlstephenbencalo17@gmail.com', '', 'Subject: Shop\n\nGOOOOOOOOOOD', 'approved', 'images/testimonials/testimonial_1788911569_be93037a.jpg', '2026-09-09 07:52:49'),

(7, 'Bencalo, Carl Stephen Escaner', 'carlstephenbencalo17@gmail.com', '', 'Subject: Shop\n\nhello po', 'approved', '', '2026-09-09 11:39:29'),

(8, 'Bencalo, Carl Stephen Escaner', 'carlstephenbencalo17@gmail.com', '', 'Subject: Shop\n\nHello', 'approved', '', '2026-09-10 10:02:41'),

(9, 'Carl', 'carlstephenbencalo17@gmail.com', 'Products', 'Nice', 'approved', '', '2026-09-29 15:40:32');


-- =====================================================
-- 7. USERS
-- =====================================================

INSERT INTO `user`
(`userId`, `username`, `userFirstName`, `userLastName`,
 `userEmail`, `userPhone`, `userAddress`, `userPassword`,
 `userRole`, `userRememberToken`, `userResetToken`,
 `userResetExpiry`, `userAvatar`, `userCreatedAt`)
VALUES

(1, 'carlstephen', 'Carl Stephen', 'Bencalo',
'carlstephenbencalo17@gmail.com', '09121212121', NULL,
'$2y$10$7.2pTPQNA0z/J05KTmhGUeLov5HMF8ixyReEi67fbi7ykTbHNMzvm',
'customer', NULL, NULL, NULL, NULL, '2026-09-29 15:35:33'),

(2, 'admin', 'System', 'Administrator',
'admin@bencalomotoworks.com', '09121212121', 'asd',
'$2y$10$SoeI1F.IvEAY6uj/MigyM.t6.oNjEL/b0.8fyY2PVGb5Vuvf0mdOi',
'admin', NULL, NULL, NULL, NULL, '2026-09-29 16:12:04'),

(3, 'carlstephen17', 'Carl Stephen', 'Bencalo',
'carlstephenbencalo@gmail.com', '09121212121', 'Bayawan City',
'$2y$10$AXuop8/mhcPqJljUBXWxaewotcg6FFNYrHLsx3gG0vxl3d7NE/u2.',
'customer', NULL, NULL, NULL, NULL, '2026-11-06 13:57:53'),

(6, 'staff', 'Demo', 'Staff',
'staff@example.invalid', '', NULL,
'$2y$12$uA5rC8qrZ.XSDw0HpD9tEupiMpKBjDWktznZ5hPF50t5SccxWz7iu',
'staff', NULL, NULL, NULL, NULL, '2026-11-11 15:17:31');




-- =====================================================
-- 8. ORDER
-- =====================================================

INSERT INTO `order`
(
    `orderId`,
    `userId`,
    `productId`,
    `orderQuantity`,
    `orderTotalAmount`,
    `orderPaymentMethod`,
    `orderPaymentReference`,
    `orderStatus`,
    `orderCreatedAt`,
    `orderGroupId`,
    `orderUnitPrice`
)
VALUES
-- ORDER GROUP 1
(1, 2, 1, 1, 1500.00, 'Cash on Delivery', 'MWO-20261101-72F5F414', 'Pending', '2026-11-02 00:45:45', 1, 1500.00),

-- ORDER GROUP 2
(2, 2, 2, 2, 700.00, 'Cash on Delivery', 'MWO-20261101-569C6825', 'Pending', '2026-11-02 00:51:18', 2, 350.00),

-- ORDER GROUP 3
(3, 3, 3, 1, 350.00, 'Cash on Delivery', 'MWO-20261107-59483D02', 'Pending', '2026-11-07 13:02:24', 3, 350.00),

-- ORDER GROUP 4
(4, 3, 4, 1, 1200.00, 'Cash on Delivery', 'MWO-20261107-313CC8E5', 'Pending', '2026-11-07 13:02:43', 4, 1200.00),

-- ORDER GROUP 5
(5, 3, 5, 1, 450.00, 'Cash on Delivery', 'MWO-20261107-8DDE030A', 'Completed', '2026-11-07 13:03:07', 5, 450.00);



-- =====================================================
-- END OF SUPPLIED RECORDS
-- =====================================================