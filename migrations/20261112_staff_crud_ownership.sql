-- Track staff-created orders and promo claims for staff edit ownership checks.
ALTER TABLE `customer_order`
  ADD COLUMN `orderCreatedByStaffId` INT(11) DEFAULT NULL,
  ADD KEY `idx_customer_order_staff_creator` (`orderCreatedByStaffId`),
  ADD CONSTRAINT `fk_customer_order_staff_creator`
    FOREIGN KEY (`orderCreatedByStaffId`) REFERENCES `user` (`userId`)
    ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `promo_claim`
  ADD COLUMN `claimCreatedByStaffId` INT(11) DEFAULT NULL,
  ADD KEY `idx_promo_claim_staff_creator` (`claimCreatedByStaffId`),
  ADD CONSTRAINT `fk_promo_claim_staff_creator`
    FOREIGN KEY (`claimCreatedByStaffId`) REFERENCES `user` (`userId`)
    ON DELETE SET NULL ON UPDATE CASCADE;
