<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113043904 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add order submission idempotency and staff payment-verification metadata.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer_order ADD orderSubmissionKey VARCHAR(36) DEFAULT NULL, ADD orderPaymentVerifiedAt DATETIME DEFAULT NULL, ADD orderPaymentVerifiedById INT DEFAULT NULL');
        $this->addSql('ALTER TABLE customer_order ADD CONSTRAINT FK_3B1CE6A364CAA461 FOREIGN KEY (orderPaymentVerifiedById) REFERENCES `user` (userId) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3B1CE6A3D884DC7C ON customer_order (orderSubmissionKey)');
        $this->addSql('CREATE INDEX IDX_3B1CE6A364CAA461 ON customer_order (orderPaymentVerifiedById)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE customer_order DROP FOREIGN KEY FK_3B1CE6A364CAA461');
        $this->addSql('DROP INDEX UNIQ_3B1CE6A3D884DC7C ON customer_order');
        $this->addSql('DROP INDEX IDX_3B1CE6A364CAA461 ON customer_order');
        $this->addSql('ALTER TABLE customer_order DROP orderSubmissionKey, DROP orderPaymentVerifiedAt, DROP orderPaymentVerifiedById');
    }
}
