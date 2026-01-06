<?php
declare(strict_types=1);

namespace WPSharedHostingMCP;

use PDO;

final class DB
{
    private PDO $pdo;

    public function __construct(array $dbConfig)
    {
        $dsn = (string)($dbConfig['dsn'] ?? '');
        $user = (string)($dbConfig['user'] ?? '');
        $pass = (string)($dbConfig['pass'] ?? '');
        $options = $dbConfig['options'] ?? [];
        if (!is_array($options)) $options = [];

        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row is false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function execute(string $sql, array $params = []): int
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }
}
