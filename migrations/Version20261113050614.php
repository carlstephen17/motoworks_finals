<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113050614 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist customer cancellation Undo windows and their finalization state.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cancellation_undo (undoId VARCHAR(48) NOT NULL, undoRecordType VARCHAR(32) NOT NULL, undoRecordId INT NOT NULL, undoPreviousStatus VARCHAR(50) NOT NULL, undoExpiresAt DATETIME NOT NULL, undoStatus VARCHAR(20) NOT NULL, undoCreatedAt DATETIME NOT NULL, undoUserId INT NOT NULL, INDEX idx_cancellation_undo_expiry (undoStatus, undoExpiresAt), INDEX idx_cancellation_undo_record (undoRecordType, undoRecordId, undoStatus), INDEX IDX_2EBB72AB9CCC0788 (undoUserId), PRIMARY KEY (undoId)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE cancellation_undo ADD CONSTRAINT FK_2EBB72AB9CCC0788 FOREIGN KEY (undoUserId) REFERENCES `user` (userId) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cancellation_undo DROP FOREIGN KEY FK_2EBB72AB9CCC0788');
        $this->addSql('DROP TABLE cancellation_undo');
    }
}
