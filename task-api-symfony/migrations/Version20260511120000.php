<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schema aligned with task-api-laravel (MySQL, BIGINT UNSIGNED PKs, indexes).
 */
final class Version20260511120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: users, projects, tasks, comments, tags, task_tag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(180) NOT NULL,
            email_verified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            password VARCHAR(255) NOT NULL,
            remember_token VARCHAR(100) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE projects (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            status VARCHAR(32) NOT NULL DEFAULT \'draft\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_5C93A3A1F6BD1646 (status),
            INDEX IDX_5C93A3A1A76ED395 (user_id, status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93A3A1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE tasks (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            project_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            status VARCHAR(32) NOT NULL DEFAULT \'todo\',
            priority VARCHAR(32) NOT NULL DEFAULT \'medium\',
            due_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX tasks_status_idx (status),
            INDEX tasks_priority_idx (priority),
            INDEX tasks_due_date_idx (due_date),
            INDEX tasks_project_status_idx (project_id, status),
            INDEX tasks_project_priority_idx (project_id, priority),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT FK_tasks_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE comments (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            task_id BIGINT UNSIGNED NOT NULL,
            content LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_5F9E259A8DB60186 (task_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE comments ADD CONSTRAINT FK_5F9E259A8DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE tags (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            color VARCHAR(32) NOT NULL DEFAULT \'#6B7280\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_6FBC94265E237E06 (name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE task_tag (
            id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
            task_id BIGINT UNSIGNED NOT NULL,
            tag_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX task_tag_task_tag_unique (task_id, tag_id),
            INDEX IDX_FF807F8BBAD26311 (tag_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE task_tag ADD CONSTRAINT FK_FF807F8B8DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_tag ADD CONSTRAINT FK_FF807F8BBAD26311 FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_tag DROP FOREIGN KEY FK_FF807F8BBAD26311');
        $this->addSql('ALTER TABLE task_tag DROP FOREIGN KEY FK_FF807F8B8DB60186');
        $this->addSql('DROP TABLE task_tag');
        $this->addSql('ALTER TABLE comments DROP FOREIGN KEY FK_5F9E259A8DB60186');
        $this->addSql('DROP TABLE comments');
        $this->addSql('ALTER TABLE tasks DROP FOREIGN KEY FK_tasks_project');
        $this->addSql('DROP TABLE tasks');
        $this->addSql('ALTER TABLE projects DROP FOREIGN KEY FK_5C93A3A1A76ED395');
        $this->addSql('DROP TABLE projects');
        $this->addSql('DROP TABLE tags');
        $this->addSql('DROP TABLE users');
    }
}
