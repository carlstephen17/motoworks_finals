-- Track staff-created promos so staff can edit only their own records.
ALTER TABLE `promo`
  ADD COLUMN `promoCreatedByStaffId` INT(11) DEFAULT NULL,
  ADD KEY `idx_promo_created_by_staff` (`promoCreatedByStaffId`),
  ADD CONSTRAINT `fk_promo_created_by_staff` FOREIGN KEY (`promoCreatedByStaffId`)
    REFERENCES `user` (`userId`) ON DELETE SET NULL ON UPDATE CASCADE;
