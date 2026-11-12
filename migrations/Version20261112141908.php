<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112141908 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE customer_order (orderGroupId INT AUTO_INCREMENT NOT NULL, orderDeliveryAddress LONGTEXT DEFAULT NULL, orderPaymentMethod VARCHAR(50) DEFAULT NULL, orderPaymentReference VARCHAR(32) DEFAULT NULL, orderPaymentStatus VARCHAR(32) NOT NULL, orderStatus VARCHAR(50) NOT NULL, orderCancellationReason LONGTEXT DEFAULT NULL, orderCreatedAt DATETIME NOT NULL, userId INT NOT NULL, orderCreatedByStaffId INT DEFAULT NULL, INDEX IDX_3B1CE6A364B64DCC (userId), INDEX IDX_3B1CE6A3AA098DCE (orderCreatedByStaffId), PRIMARY KEY (orderGroupId)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE motorcycle (motorcycleId INT AUTO_INCREMENT NOT NULL, motorcycleModel VARCHAR(255) NOT NULL, motorcyclePlateNumber VARCHAR(32) DEFAULT NULL, motorcycleCreatedAt DATETIME NOT NULL, userId INT NOT NULL, UNIQUE INDEX UNIQ_21E380E17449F0B8 (motorcyclePlateNumber), INDEX IDX_21E380E164B64DCC (userId), PRIMARY KEY (motorcycleId)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `order` (orderId INT AUTO_INCREMENT NOT NULL, orderQuantity INT NOT NULL, orderTotalAmount NUMERIC(10, 2) NOT NULL, orderPaymentMethod VARCHAR(50) DEFAULT NULL, orderPaymentReference VARCHAR(32) DEFAULT NULL, orderStatus VARCHAR(50) DEFAULT NULL, orderCreatedAt DATETIME DEFAULT NULL, orderUnitPrice NUMERIC(10, 2) DEFAULT NULL, userId INT NOT NULL, productId INT NOT NULL, orderGroupId INT DEFAULT NULL, INDEX IDX_F529939864B64DCC (userId), INDEX IDX_F529939836799605 (productId), INDEX IDX_F52993981880467A (orderGroupId), PRIMARY KEY (orderId)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product (productId INT AUTO_INCREMENT NOT NULL, productName VARCHAR(255) NOT NULL, productPrice NUMERIC(10, 2) NOT NULL, productStock INT NOT NULL, productImage VARCHAR(255) DEFAULT NULL, productActive TINYINT DEFAULT NULL, PRIMARY KEY (productId)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE promo (promoId INT AUTO_INCREMENT NOT NULL, promoSlug VARCHAR(100) NOT NULL, promoTitle VARCHAR(255) NOT NULL, promoBadge VARCHAR(50) NOT NULL, promoDescription LONGTEXT NOT NULL, promoItemLabel VARCHAR(255) NOT NULL, promoItemOptions LONGTEXT NOT NULL, promoPerks LONGTEXT DEFAULT NULL, promoProductImage VARCHAR(255) DEFAULT NULL, promoServiceImage VARCHAR(255) DEFAULT NULL, promoProductIcon VARCHAR(100) DEFAULT NULL, promoServiceIcon VARCHAR(100) DEFAULT NULL, promoActive TINYINT NOT NULL, promoCreatedByStaffId INT DEFAULT NULL, promoCreatedAt DATETIME NOT NULL, UNIQUE INDEX UNIQ_B0139AFB33C26AC2 (promoSlug), PRIMARY KEY (promoId)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE promo_claim (claimId INT AUTO_INCREMENT NOT NULL, claimFullName VARCHAR(255) NOT NULL, claimPhone VARCHAR(50) NOT NULL, claimBundleSlug VARCHAR(100) NOT NULL, claimSelectedOption VARCHAR(255) NOT NULL, claimAppointmentDate DATE NOT NULL, claimAppointmentTime TIME NOT NULL, claimStatus VARCHAR(50) DEFAULT NULL, claimCreatedAt DATETIME NOT NULL, userId INT DEFAULT NULL, claimCreatedByStaffId INT DEFAULT NULL, INDEX IDX_4E40E2B164B64DCC (userId), INDEX IDX_4E40E2B1979C3E59 (claimCreatedByStaffId), PRIMARY KEY (claimId)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE service (serviceId INT AUTO_INCREMENT NOT NULL, serviceName VARCHAR(255) NOT NULL, serviceImage VARCHAR(255) DEFAULT NULL, serviceDescription LONGTEXT DEFAULT NULL, servicePrice NUMERIC(10, 2) NOT NULL, serviceStatus VARCHAR(50) DEFAULT NULL, serviceActive TINYINT DEFAULT NULL, serviceFeatured TINYINT DEFAULT NULL, PRIMARY KEY (serviceId)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE service_booking (bookingId INT AUTO_INCREMENT NOT NULL, bookingVehicleModel VARCHAR(255) DEFAULT NULL, bookingDate DATE NOT NULL, bookingTime TIME NOT NULL, bookingBundleSlug VARCHAR(100) DEFAULT NULL, bookingBundleOption VARCHAR(255) DEFAULT NULL, bookingStatus VARCHAR(50) DEFAULT NULL, bookingNotes LONGTEXT DEFAULT NULL, bookingRejectionReason LONGTEXT DEFAULT NULL, bookingSource VARCHAR(32) NOT NULL, bookingCreatedAt DATETIME DEFAULT NULL, userId INT NOT NULL, serviceId INT NOT NULL, bookingMotorcycleId INT DEFAULT NULL, createdByStaffId INT DEFAULT NULL, INDEX IDX_2F88BF4264B64DCC (userId), INDEX IDX_2F88BF4289697FA8 (serviceId), INDEX IDX_2F88BF42E1779153 (bookingMotorcycleId), INDEX IDX_2F88BF42BE4D203 (createdByStaffId), PRIMARY KEY (bookingId)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE service_record (recordId INT AUTO_INCREMENT NOT NULL, recordMotorcycleModel VARCHAR(255) NOT NULL, recordPlateNumber VARCHAR(32) DEFAULT NULL, recordServiceDate DATE NOT NULL, recordWorkDescription LONGTEXT NOT NULL, recordPartsUsed LONGTEXT DEFAULT NULL, recordCost NUMERIC(10, 2) DEFAULT NULL, recordStatus VARCHAR(50) NOT NULL, recordNotes LONGTEXT DEFAULT NULL, recordCreatedAt DATETIME NOT NULL, recordUpdatedAt DATETIME NOT NULL, customerId INT NOT NULL, motorcycleId INT DEFAULT NULL, serviceId INT NOT NULL, createdByStaffId INT NOT NULL, INDEX IDX_A5F39AA7F17FD7A5 (customerId), INDEX IDX_A5F39AA78060239C (motorcycleId), INDEX IDX_A5F39AA789697FA8 (serviceId), INDEX IDX_A5F39AA7BE4D203 (createdByStaffId), PRIMARY KEY (recordId)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE testimonial (testimonialId INT AUTO_INCREMENT NOT NULL, testimonialName VARCHAR(255) NOT NULL, testimonialEmail VARCHAR(100) NOT NULL, testimonialSubject VARCHAR(255) NOT NULL, testimonialMessage LONGTEXT NOT NULL, testimonialStatus VARCHAR(20) DEFAULT NULL, testimonialImage VARCHAR(255) DEFAULT NULL, testimonialCreatedAt DATETIME NOT NULL, PRIMARY KEY (testimonialId)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (userId INT AUTO_INCREMENT NOT NULL, username VARCHAR(50) NOT NULL, userFirstName VARCHAR(50) NOT NULL, userLastName VARCHAR(50) NOT NULL, userEmail VARCHAR(100) NOT NULL, userPhone VARCHAR(20) DEFAULT NULL, userAddress LONGTEXT DEFAULT NULL, userPassword VARCHAR(255) NOT NULL, userRole VARCHAR(20) DEFAULT \'customer\' NOT NULL, userRememberToken VARCHAR(255) DEFAULT NULL, userResetToken VARCHAR(255) DEFAULT NULL, userResetExpiry DATETIME DEFAULT NULL, userAvatar VARCHAR(255) DEFAULT NULL, userCreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), UNIQUE INDEX UNIQ_8D93D6491F76FA2 (userEmail), PRIMARY KEY (userId)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT FK_3B1CE6A364B64DCC FOREIGN KEY (userId) REFERENCES `user` (userId) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT FK_3B1CE6A3AA098DCE FOREIGN KEY (orderCreatedByStaffId) REFERENCES `user` (userId) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE motorcycle ADD CONSTRAINT FK_21E380E164B64DCC FOREIGN KEY (userId) REFERENCES `user` (userId) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F529939864B64DCC FOREIGN KEY (userId) REFERENCES `user` (userId) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F529939836799605 FOREIGN KEY (productId) REFERENCES product (productId) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `order` ADD CONSTRAINT FK_F52993981880467A FOREIGN KEY (orderGroupId) REFERENCES customer_order (orderGroupId) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE promo_claim ADD CONSTRAINT FK_4E40E2B164B64DCC FOREIGN KEY (userId) REFERENCES `user` (userId) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE promo_claim ADD CONSTRAINT FK_4E40E2B1979C3E59 FOREIGN KEY (claimCreatedByStaffId) REFERENCES `user` (userId) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE service_booking ADD CONSTRAINT FK_2F88BF4264B64DCC FOREIGN KEY (userId) REFERENCES `user` (userId) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_booking ADD CONSTRAINT FK_2F88BF4289697FA8 FOREIGN KEY (serviceId) REFERENCES service (serviceId) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_booking ADD CONSTRAINT FK_2F88BF42E1779153 FOREIGN KEY (bookingMotorcycleId) REFERENCES motorcycle (motorcycleId) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE service_booking ADD CONSTRAINT FK_2F88BF42BE4D203 FOREIGN KEY (createdByStaffId) REFERENCES `user` (userId) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE service_record ADD CONSTRAINT FK_A5F39AA7F17FD7A5 FOREIGN KEY (customerId) REFERENCES `user` (userId) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE service_record ADD CONSTRAINT FK_A5F39AA78060239C FOREIGN KEY (motorcycleId) REFERENCES motorcycle (motorcycleId) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE service_record ADD CONSTRAINT FK_A5F39AA789697FA8 FOREIGN KEY (serviceId) REFERENCES service (serviceId) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE service_record ADD CONSTRAINT FK_A5F39AA7BE4D203 FOREIGN KEY (createdByStaffId) REFERENCES `user` (userId) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_3B1CE6A364B64DCC');
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_3B1CE6A3AA098DCE');
        $this->addSql('ALTER TABLE motorcycle DROP FOREIGN KEY FK_21E380E164B64DCC');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F529939864B64DCC');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F529939836799605');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993981880467A');
        $this->addSql('ALTER TABLE promo_claim DROP FOREIGN KEY FK_4E40E2B164B64DCC');
        $this->addSql('ALTER TABLE promo_claim DROP FOREIGN KEY FK_4E40E2B1979C3E59');
        $this->addSql('ALTER TABLE service_booking DROP FOREIGN KEY FK_2F88BF4264B64DCC');
        $this->addSql('ALTER TABLE service_booking DROP FOREIGN KEY FK_2F88BF4289697FA8');
        $this->addSql('ALTER TABLE service_booking DROP FOREIGN KEY FK_2F88BF42E1779153');
        $this->addSql('ALTER TABLE service_booking DROP FOREIGN KEY FK_2F88BF42BE4D203');
        $this->addSql('ALTER TABLE service_record DROP FOREIGN KEY FK_A5F39AA7F17FD7A5');
        $this->addSql('ALTER TABLE service_record DROP FOREIGN KEY FK_A5F39AA78060239C');
        $this->addSql('ALTER TABLE service_record DROP FOREIGN KEY FK_A5F39AA789697FA8');
        $this->addSql('ALTER TABLE service_record DROP FOREIGN KEY FK_A5F39AA7BE4D203');
        $this->addSql('DROP TABLE customer_order');
        $this->addSql('DROP TABLE motorcycle');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE promo');
        $this->addSql('DROP TABLE promo_claim');
        $this->addSql('DROP TABLE service');
        $this->addSql('DROP TABLE service_booking');
        $this->addSql('DROP TABLE service_record');
        $this->addSql('DROP TABLE testimonial');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
