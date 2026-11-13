<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113034813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Associate promo claims and promo bookings with their catalog promotion.';
    }

    public function up(Schema $schema): void
    {
        $this->addPromoRelation('promo_claim', 'claimPromoId', 'IDX_4E40E2B16422C69C', 'FK_4E40E2B16422C69C');
        $this->addPromoRelation('service_booking', 'bookingPromoId', 'IDX_2F88BF42403B542C', 'FK_2F88BF42403B542C');
        $this->addSql('UPDATE promo_claim claim INNER JOIN promo ON promo.promoSlug = claim.claimBundleSlug SET claim.claimPromoId = promo.promoId');
        $this->addSql('UPDATE service_booking booking INNER JOIN promo ON promo.promoSlug = booking.bookingBundleSlug SET booking.bookingPromoId = promo.promoId');
    }

    private function addPromoRelation(string $tableName, string $columnName, string $indexName, string $foreignKeyName): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $table = $schemaManager->introspectTable($tableName);

        if (!$table->hasColumn($columnName)) {
            $this->addSql(sprintf('ALTER TABLE %s ADD %s INT DEFAULT NULL', $tableName, $columnName));
        }
        if (!$table->hasForeignKey($foreignKeyName)) {
            $this->addSql(sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES promo (promoId) ON DELETE SET NULL',
                $tableName,
                $foreignKeyName,
                $columnName,
            ));
        }
        if (!$table->hasIndex($indexName)) {
            $this->addSql(sprintf('CREATE INDEX %s ON %s (%s)', $indexName, $tableName, $columnName));
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE promo_claim DROP FOREIGN KEY FK_4E40E2B16422C69C');
        $this->addSql('DROP INDEX IDX_4E40E2B16422C69C ON promo_claim');
        $this->addSql('ALTER TABLE promo_claim DROP claimPromoId');
        $this->addSql('ALTER TABLE service_booking DROP FOREIGN KEY FK_2F88BF42403B542C');
        $this->addSql('DROP INDEX IDX_2F88BF42403B542C ON service_booking');
        $this->addSql('ALTER TABLE service_booking DROP bookingPromoId');
    }
}
