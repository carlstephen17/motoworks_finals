<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112165213 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce the promo staff-owner foreign key.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_B0139AFB15B11F43 ON promo (promoCreatedByStaffId)');
        $this->addSql('ALTER TABLE promo ADD CONSTRAINT FK_B0139AFB15B11F43 FOREIGN KEY (promoCreatedByStaffId) REFERENCES `user` (userId) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE promo DROP FOREIGN KEY FK_B0139AFB15B11F43');
        $this->addSql('DROP INDEX IDX_B0139AFB15B11F43 ON promo');
    }
}
