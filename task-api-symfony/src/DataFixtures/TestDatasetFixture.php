<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Service\BenchmarkDataService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class TestDatasetFixture extends Fixture implements FixtureGroupInterface
{
    public function __construct(
        private readonly BenchmarkDataService $benchmark,
    ) {
    }

    public static function getGroups(): array
    {
        return ['thesis'];
    }

    public function load(ObjectManager $manager): void
    {
        $conn = $manager->getConnection();
        $faker = Factory::create();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $passwordHash = password_hash('password', PASSWORD_BCRYPT);

        $userRows = [];
        for ($i = 1; $i <= 100; ++$i) {
            $userRows[] = [
                'name' => $faker->name(),
                'email' => sprintf('user%d@example.test', $i),
                'email_verified_at' => null,
                'password' => $passwordHash,
                'remember_token' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($userRows) >= 50) {
                $this->multiInsert($conn, 'users', ['name', 'email', 'email_verified_at', 'password', 'remember_token', 'created_at', 'updated_at'], $userRows);
                $userRows = [];
            }
        }
        if ($userRows !== []) {
            $this->multiInsert($conn, 'users', ['name', 'email', 'email_verified_at', 'password', 'remember_token', 'created_at', 'updated_at'], $userRows);
        }

        $userIds = $conn->fetchFirstColumn('SELECT id FROM users ORDER BY id ASC');
        if ($userIds === []) {
            return;
        }

        $projectRows = [];
        for ($i = 0; $i < 50; ++$i) {
            $uid = (int) $userIds[array_rand($userIds)];
            $projectRows[] = [
                'user_id' => $uid,
                'name' => $faker->words(3, true).' Project',
                'description' => $faker->optional()->paragraph(),
                'status' => $faker->randomElement(['draft', 'active', 'archived']),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->multiInsert($conn, 'projects', ['user_id', 'name', 'description', 'status', 'created_at', 'updated_at'], $projectRows);

        $tagRows = [];
        for ($i = 0; $i < 100; ++$i) {
            $hex = $faker->hexColor();
            if (!str_starts_with($hex, '#')) {
                $hex = '#'.$hex;
            }
            $hex = substr($hex, 0, 32);
            $tagRows[] = [
                'name' => sprintf('tag-%d-%s', $i, bin2hex(random_bytes(5))),
                'color' => $hex,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->multiInsert($conn, 'tags', ['name', 'color', 'created_at', 'updated_at'], $tagRows);

        $projectIds = $conn->fetchFirstColumn('SELECT id FROM projects ORDER BY id ASC');
        foreach ($projectIds as $projectId) {
            $this->benchmark->bulkInsertTasks((int) $projectId, 200);
        }

        $tmin = (int) $conn->fetchOne('SELECT MIN(id) FROM tasks');
        $tmax = (int) $conn->fetchOne('SELECT MAX(id) FROM tasks');
        if ($tmin === 0 || $tmax === 0) {
            return;
        }

        $commentRows = [];
        for ($i = 0; $i < 50_000; ++$i) {
            $commentRows[] = [
                'task_id' => random_int($tmin, $tmax),
                'content' => $faker->realText(random_int(60, 280)),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($commentRows) >= 500) {
                $this->multiInsert($conn, 'comments', ['task_id', 'content', 'created_at', 'updated_at'], $commentRows);
                $commentRows = [];
            }
        }
        if ($commentRows !== []) {
            $this->multiInsert($conn, 'comments', ['task_id', 'content', 'created_at', 'updated_at'], $commentRows);
        }

        $tagIds = $conn->fetchFirstColumn('SELECT id FROM tags ORDER BY id ASC');
        $pivotRows = [];
        for ($tid = $tmin; $tid <= $tmax; ++$tid) {
            $pickCount = random_int(1, min(4, count($tagIds)));
            $picked = $tagIds;
            shuffle($picked);
            $picked = array_slice($picked, 0, $pickCount);
            foreach ($picked as $tagId) {
                $pivotRows[] = [
                    'task_id' => $tid,
                    'tag_id' => (int) $tagId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (count($pivotRows) >= 1000) {
                $this->insertPivotIgnore($conn, $pivotRows);
                $pivotRows = [];
            }
        }
        if ($pivotRows !== []) {
            $this->insertPivotIgnore($conn, $pivotRows);
        }

        $manager->clear();
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function multiInsert(\Doctrine\DBAL\Connection $conn, string $table, array $columns, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $quotedTable = $conn->quoteIdentifier($table);
        $quotedCols = array_map($conn->quoteIdentifier(...), $columns);
        $rowPlaceholder = '('.implode(',', array_fill(0, count($columns), '?')).')';
        $valuesSql = implode(',', array_fill(0, count($rows), $rowPlaceholder));
        $sql = 'INSERT INTO '.$quotedTable.' ('.implode(',', $quotedCols).') VALUES '.$valuesSql;

        $params = [];
        foreach ($rows as $row) {
            foreach ($columns as $c) {
                $params[] = $row[$c];
            }
        }

        $conn->executeStatement($sql, $params);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function insertPivotIgnore(\Doctrine\DBAL\Connection $conn, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $columns = ['task_id', 'tag_id', 'created_at', 'updated_at'];
        $quotedTable = $conn->quoteIdentifier('task_tag');
        $quotedCols = array_map($conn->quoteIdentifier(...), $columns);
        $rowPlaceholder = '('.implode(',', array_fill(0, count($columns), '?')).')';
        $valuesSql = implode(',', array_fill(0, count($rows), $rowPlaceholder));
        $sql = 'INSERT IGNORE INTO '.$quotedTable.' ('.implode(',', $quotedCols).') VALUES '.$valuesSql;

        $params = [];
        foreach ($rows as $row) {
            foreach ($columns as $c) {
                $params[] = $row[$c];
            }
        }

        $conn->executeStatement($sql, $params);
    }
}
