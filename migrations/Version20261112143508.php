<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112143508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed the legacy promotions defined in database.sql.';
    }

    public function up(Schema $schema): void
    {
        $promos = [
            [
                1,
                'tire_service',
                'Tire + Mounting Service Bundle',
                '15% OFF',
                'Purchase any eligible motorcycle tire and pair it with a professional mounting, balancing, and alignment service at the same time.',
                'Select Tire Model / Size',
                "Michelin Pilot Street 2 (90/80-14 Tubeless)\nPirelli Diablo Rosso Sport (100/80-14 Tubeless)\nIRC NR73 Tubeless Tire (80/90-17)",
                "Eligible on all standard and tubeless tires\nIncludes labor and wheel balancing adjustment",
                'images/featured_products/tire.webp',
                'images/featured_services/wheel_and_tire_service.png',
                'fa-solid fa-circle-dot',
                'fa-solid fa-screwdriver-wrench',
                1,
            ],
            [
                2,
                'oil_tuneup',
                'Synthetic Oil Change + Tune-Up',
                '20% OFF',
                'Keep your engine running smoothly with high-grade synthetic oil paired with a complete carb/FI diagnostic tune-up service.',
                'Select Oil Viscosity & Type',
                "Motul 7100 4T 10W-40 Synthetic (1L)\nShell Advance Ultra 10W-40 Fully Synthetic (1L)\nCastrol Power1 Ultimate 10W-50 (1L)",
                "Includes 1L high-performance synthetic oil\nFull engine diagnostic & idle tuning",
                'images/featured_products/synthetic_oil.png',
                'images/featured_services/premium_oil_and_change_service.png',
                'fa-solid fa-oil-can',
                'fa-solid fa-gauge-high',
                1,
            ],
            [
                3,
                'chain_sprocket',
                'Heavy Duty Chain Kit + Install',
                '10% OFF',
                'Upgrade your drive train with a heavy-duty chain and sprocket set bundled together with precision professional installation.',
                'Select Chain & Sprocket Set',
                "SSS Heavy Duty Chain & Sprocket Set (428H x 14T/38T)\nDID O-Ring Heavy Duty Chain Combo (520V)\nRK Racing Gold Chain & Steel Sprocket Kit",
                "O-ring or X-ring heavy duty durability\nIncludes chain cleaning and tension adjustment",
                'images/products/chain.png',
                'images/services/chain.png',
                'fa-solid fa-link',
                'fa-solid fa-gear',
                1,
            ],
            [
                4,
                'brake_overhaul',
                'Brake Pad Set + Fluid Bleeding',
                '15% OFF',
                'Ensure maximum stopping power by bundling front/rear ceramic brake pads with a complete hydraulic fluid flush and bleed service.',
                'Select Brake Pad Compound',
                "Brembo Ceramic Front/Rear Brake Pads\nEBC FA Series Sintered Brake Pads\nBendix Heavy Duty Street Moto Pads",
                "High-friction ceramic compound pads\nComplete DOT-4 brake fluid replacement",
                'images/products/brake.png',
                'images/services/brake.png',
                'fa-solid fa-compact-disc',
                'fa-solid fa-droplet',
                1,
            ],
        ];

        foreach ($promos as $promo) {
            $this->addSql(
                'INSERT IGNORE INTO promo (promoId, promoSlug, promoTitle, promoBadge, promoDescription, promoItemLabel, promoItemOptions, promoPerks, promoProductImage, promoServiceImage, promoProductIcon, promoServiceIcon, promoActive, promoCreatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
                $promo,
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DELETE FROM promo WHERE promoSlug IN (?, ?, ?, ?)',
            ['tire_service', 'oil_tuneup', 'chain_sprocket', 'brake_overhaul'],
        );
    }
}
