<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251110151523 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attachment (id INT AUTO_INCREMENT NOT NULL, post_parent INT DEFAULT NULL, post_title VARCHAR(255) NOT NULL, post_name VARCHAR(255) NOT NULL, post_type VARCHAR(50) NOT NULL, post_status VARCHAR(50) NOT NULL, guid VARCHAR(255) DEFAULT NULL, post_content LONGTEXT DEFAULT NULL, post_excerpt LONGTEXT DEFAULT NULL, post_mime_type VARCHAR(50) DEFAULT NULL, INDEX IDX_795FD9BBDAC842A (post_parent), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, parent INT NOT NULL, term_taxonomy_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE custom_order (order_id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, post_date DATETIME NOT NULL, post_status VARCHAR(50) NOT NULL, post_excerpt LONGTEXT DEFAULT NULL, post_title VARCHAR(255) NOT NULL, INDEX IDX_36246BF1A76ED395 (user_id), PRIMARY KEY(order_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE post (id INT AUTO_INCREMENT NOT NULL, category_id INT DEFAULT NULL, post_title VARCHAR(255) NOT NULL, post_name VARCHAR(255) NOT NULL, post_type VARCHAR(50) NOT NULL, post_status VARCHAR(50) NOT NULL, post_content LONGTEXT DEFAULT NULL, post_excerpt LONGTEXT DEFAULT NULL, guid VARCHAR(255) DEFAULT NULL, post_parent INT NOT NULL, post_mime_type VARCHAR(50) DEFAULT NULL, INDEX IDX_5A8A6C8D12469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE post_meta (id INT AUTO_INCREMENT NOT NULL, post_id INT DEFAULT NULL, meta_key VARCHAR(255) NOT NULL, meta_value LONGTEXT NOT NULL, INDEX IDX_1EA7733E4B89032C (post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, user_login VARCHAR(255) NOT NULL, user_email VARCHAR(255) NOT NULL, user_registered DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_meta (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, meta_key VARCHAR(255) NOT NULL, meta_value LONGTEXT NOT NULL, INDEX IDX_AD7358FCA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE attachment ADD CONSTRAINT FK_795FD9BBDAC842A FOREIGN KEY (post_parent) REFERENCES post (id)');
        $this->addSql('ALTER TABLE custom_order ADD CONSTRAINT FK_36246BF1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8D12469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE post_meta ADD CONSTRAINT FK_1EA7733E4B89032C FOREIGN KEY (post_id) REFERENCES post (id)');
        $this->addSql('ALTER TABLE user_meta ADD CONSTRAINT FK_AD7358FCA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attachment DROP FOREIGN KEY FK_795FD9BBDAC842A');
        $this->addSql('ALTER TABLE custom_order DROP FOREIGN KEY FK_36246BF1A76ED395');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8D12469DE2');
        $this->addSql('ALTER TABLE post_meta DROP FOREIGN KEY FK_1EA7733E4B89032C');
        $this->addSql('ALTER TABLE user_meta DROP FOREIGN KEY FK_AD7358FCA76ED395');
        $this->addSql('DROP TABLE attachment');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE custom_order');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE post_meta');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_meta');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
