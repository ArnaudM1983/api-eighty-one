<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260527133423 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product ADD sale_price NUMERIC(10, 2) DEFAULT NULL, ADD special_price_from DATETIME DEFAULT NULL, ADD special_price_to DATETIME DEFAULT NULL, ADD promo_text VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE product_variant ADD sale_price NUMERIC(10, 2) DEFAULT NULL, ADD special_price_from DATETIME DEFAULT NULL, ADD special_price_to DATETIME DEFAULT NULL, ADD promo_text VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product DROP sale_price, DROP special_price_from, DROP special_price_to, DROP promo_text');
        $this->addSql('ALTER TABLE product_variant DROP sale_price, DROP special_price_from, DROP special_price_to, DROP promo_text');
    }
}
