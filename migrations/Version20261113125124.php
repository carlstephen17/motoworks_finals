<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113125124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize legacy payment state to Pending.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE customer_order SET orderPaymentStatus = 'Pending' WHERE orderPaymentStatus = 'Unverified'");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Pending is the valid payment status; converting it back would corrupt new orders.');
    }
}
