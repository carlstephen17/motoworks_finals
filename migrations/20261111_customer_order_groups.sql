-- Add one customer-order header per checkout while retaining existing order rows as line items.
CREATE TABLE IF NOT EXISTS `customer_order` (
  `orderGroupId` INT(11) NOT NULL AUTO_INCREMENT,
  `userId` INT(11) NOT NULL,
  `orderDeliveryAddress` TEXT DEFAULT NULL,
  `orderPaymentMethod` VARCHAR(50) DEFAULT NULL,
  `orderPaymentReference` VARCHAR(32) DEFAULT NULL,
  `orderPaymentStatus` VARCHAR(32) NOT NULL DEFAULT 'Pending',
  `orderStatus` VARCHAR(50) NOT NULL DEFAULT 'Pending',
  `orderCancellationReason` TEXT DEFAULT NULL,
  `orderCreatedAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`orderGroupId`),
  KEY `idx_customer_order_user` (`userId`),
  KEY `idx_customer_order_status` (`orderStatus`),
  KEY `idx_customer_order_date` (`orderCreatedAt`),
  CONSTRAINT `fk_customer_order_user` FOREIGN KEY (`userId`) REFERENCES `user` (`userId`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `order`
  ADD COLUMN `orderGroupId` INT(11) DEFAULT NULL AFTER `orderCreatedAt`,
  ADD COLUMN `orderUnitPrice` DECIMAL(10,2) DEFAULT NULL AFTER `orderGroupId`,
  ADD KEY `idx_order_group` (`orderGroupId`),
  ADD CONSTRAINT `fk_order_group` FOREIGN KEY (`orderGroupId`) REFERENCES `customer_order` (`orderGroupId`) ON DELETE RESTRICT ON UPDATE CASCADE;

INSERT INTO `customer_order`
  (orderGroupId, userId, orderDeliveryAddress, orderPaymentMethod,
   orderPaymentReference, orderPaymentStatus, orderStatus, orderCreatedAt)
SELECT MIN(o.orderId), o.userId, MAX(u.userAddress), MAX(o.orderPaymentMethod),
       o.orderPaymentReference,
       CASE WHEN MAX(o.orderPaymentMethod) = 'Cash on Delivery' THEN 'Due on delivery' ELSE 'Pending' END,
       MAX(o.orderStatus), MIN(o.orderCreatedAt)
FROM `order` o
INNER JOIN `user` u ON u.userId = o.userId
WHERE NULLIF(o.orderPaymentReference, '') IS NOT NULL
GROUP BY o.userId, o.orderPaymentReference;

INSERT INTO `customer_order`
  (orderGroupId, userId, orderDeliveryAddress, orderPaymentMethod,
   orderPaymentReference, orderPaymentStatus, orderStatus, orderCreatedAt)
SELECT o.orderId, o.userId, u.userAddress, o.orderPaymentMethod, NULL,
       CASE WHEN o.orderPaymentMethod = 'Cash on Delivery' THEN 'Due on delivery' ELSE 'Pending' END,
       o.orderStatus, o.orderCreatedAt
FROM `order` o
INNER JOIN `user` u ON u.userId = o.userId
WHERE NULLIF(o.orderPaymentReference, '') IS NULL;

UPDATE `order` o
INNER JOIN `customer_order` co
  ON co.userId = o.userId
 AND co.orderPaymentReference = o.orderPaymentReference
SET o.orderGroupId = co.orderGroupId,
    o.orderUnitPrice = ROUND(o.orderTotalAmount / GREATEST(o.orderQuantity, 1), 2)
WHERE NULLIF(o.orderPaymentReference, '') IS NOT NULL;

UPDATE `order` o
INNER JOIN `customer_order` co ON co.orderGroupId = o.orderId
SET o.orderGroupId = co.orderGroupId,
    o.orderUnitPrice = ROUND(o.orderTotalAmount / GREATEST(o.orderQuantity, 1), 2)
WHERE NULLIF(o.orderPaymentReference, '') IS NULL;

UPDATE `order` o
INNER JOIN `customer_order` co ON co.orderGroupId = o.orderGroupId
SET o.orderStatus = co.orderStatus
WHERE o.orderStatus <> co.orderStatus;
