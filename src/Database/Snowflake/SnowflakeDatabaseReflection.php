<?php

declare(strict_types=1);

namespace Keboola\TableBackendUtils\Database\Snowflake;

use Doctrine\DBAL\Connection;
use Keboola\TableBackendUtils\Database\DatabaseReflectionInterface;
use Keboola\TableBackendUtils\DataHelper;
use Keboola\TableBackendUtils\Escaping\Snowflake\SnowflakeQuote;

final class SnowflakeDatabaseReflection implements DatabaseReflectionInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return string[]
     */
    public function getUsersNames(?string $like = null): array
    {
        $likeSql = '';
        if ($like !== null) {
            $likeSql .= sprintf(
                ' LIKE %s',
                SnowflakeQuote::quote('%' . $like . '%')
            );
        }

        // load the data
        /** @var array<array{name:string}> $users */
        $users = $this->connection->fetchAllAssociative(sprintf(
            'SHOW USERS%s',
            $likeSql
        ));

        // extract data to primitive array
        return array_map(static fn($record) => $record['name'], $users);
    }

    /**
     * @return string[]
     */
    public function getRolesNames(?string $like = null): array
    {
        $likeSql = '';
        if ($like !== null) {
            $likeSql .= sprintf(
                ' LIKE %s',
                SnowflakeQuote::quote('%' . $like . '%')
            );
        }

        // load the data
        /** @var array<array{name:string}> $roles */
        $roles = $this->connection->fetchAllAssociative(sprintf(
            'SHOW ROLES%s',
            $likeSql
        ));

        // extract data to primitive array
        return array_map(static fn($record) => $record['name'], $roles);
    }
}
