<?php

declare(strict_types=1);

namespace Tests\Keboola\TableBackendUtils\Functional\Teradata\Table;

use Doctrine\DBAL\Exception as DBALException;
use Generator;
use Keboola\TableBackendUtils\Column\ColumnCollection;
use Keboola\TableBackendUtils\Column\Teradata\TeradataColumn;
use Keboola\TableBackendUtils\Table\Teradata\TeradataTableDefinition;
use Keboola\TableBackendUtils\Table\Teradata\TeradataTableQueryBuilder;
use Keboola\TableBackendUtils\Table\Teradata\TeradataTableReflection;
use Tests\Keboola\TableBackendUtils\Functional\Teradata\TeradataBaseCase;

/**
 * @covers TeradataTableQueryBuilder
 * @uses   ColumnCollection
 */
class TeradataTableQueryBuilderTest extends TeradataBaseCase
{
    private TeradataTableQueryBuilder $qb;

    public function setUp(): void
    {
        $this->qb = new TeradataTableQueryBuilder();
        parent::setUp();

        $this->cleanDatabase($this->getDatabaseName());
        $this->createDatabase($this->getDatabaseName());
    }

    public function testGetRenameTableCommand(): void
    {
        $testDb = $this->getDatabaseName();
        $testTable = self::TABLE_GENERIC;
        $testTableNew = 'newName';
        $this->initTable();

        // reflection to old table
        $refOld = new TeradataTableReflection($this->connection, $testDb, $testTable);

        // get, test and run command
        $sql = $this->qb->getRenameTableCommand($this->getDatabaseName(), self::TABLE_GENERIC, $testTableNew);
        self::assertEquals("RENAME TABLE \"{$testDb}\".\"{$testTable}\" AS \"{$testDb}\".\"{$testTableNew}\"", $sql);
        $this->connection->executeQuery($sql);

        // reflection to new table and check the existence via counting
        $refNew = new TeradataTableReflection($this->connection, $testDb, $testTableNew);
        $refNew->getRowsCount();

        // test NON existence of old table via counting
        $this->expectException(DBALException::class);
        $refOld->getRowsCount();
    }

    public function testGetTruncateTableCommand(): void
    {
        $testDb = $this->getDatabaseName();
        $testTable = self::TABLE_GENERIC;
        $this->initTable();

        // reflection to the table
        $ref = new TeradataTableReflection($this->connection, $testDb, $testTable);

        // check that table is empty
        self::assertEquals(0, $ref->getRowsCount());

        // insert some data, table wont be empty
        $this->insertRowToTable($testDb, $testTable, 1, 'franta', 'omacka');
        self::assertEquals(1, $ref->getRowsCount());

        // get, test and run query
        $sql = $this->qb->getTruncateTableCommand($this->getDatabaseName(), self::TABLE_GENERIC);
        self::assertEquals("DELETE \"{$testDb}\".\"{$testTable}\" ALL", $sql);
        $this->connection->executeQuery($sql);

        // check that table is empty again
        self::assertEquals(0, $ref->getRowsCount());
    }

    public function testGetDropTableCommand(): void
    {
        $testDb = $this->getDatabaseName();
        $testTable = self::TABLE_GENERIC;
        $this->initTable();

        // reflection to the table
        $ref = new TeradataTableReflection($this->connection, $testDb, $testTable);

        // get, test and run query
        $sql = $this->qb->getDropTableCommand($this->getDatabaseName(), self::TABLE_GENERIC);
        self::assertEquals("DROP TABLE \"{$testDb}\".\"{$testTable}\"", $sql);
        $this->connection->executeQuery($sql);

        // test NON existence of old table via counting
        $this->expectException(DBALException::class);
        $ref->getRowsCount();
    }

    /**
     * @param TeradataColumn[] $columns
     * @param string[] $primaryKeys
     * @param string[] $expectedColumnNames
     * @param string[] $expectedPKs
     * @throws DBALException
     * @dataProvider createTableTestSqlProvider
     */
    public function testGetCreateCommand(
        array $columns,
        array $primaryKeys,
        array $expectedColumnNames,
        array $expectedPKs,
        string $expectedSql
    ): void {
        $sql = $this->qb->getCreateTableCommand(
            $this->getDatabaseName(),
            self::TABLE_GENERIC,
            new ColumnCollection($columns),
            $primaryKeys
        );
        self::assertSame($expectedSql, $sql);
        $this->connection->executeQuery($sql);

        // test table properties
        $tableReflection = new TeradataTableReflection(
            $this->connection,
            $this->getDatabaseName(),
            self::TABLE_GENERIC
        );
        self::assertSame($expectedColumnNames, $tableReflection->getColumnsNames());
        self::assertSame($expectedPKs, $tableReflection->getPrimaryKeysNames());
    }

    /**
     * @return \Generator<string, mixed, mixed, mixed>
     */
    public function createTableTestSqlProvider(): Generator
    {
        $testDb = $this->getDatabaseName();
        $tableName = self::TABLE_GENERIC;

        yield 'no keys' => [
            'cols' => [
                TeradataColumn::createGenericColumn('col1'),
                TeradataColumn::createGenericColumn('col2'),
            ],
            'primaryKeys' => [],
            'expectedColumnNames' => ['col1', 'col2'],
            'expectedPrimaryKeys' => [],
            'query' => <<<EOT
CREATE MULTISET TABLE "$testDb"."$tableName", FALLBACK
("col1" VARCHAR (32000) NOT NULL DEFAULT '' CHARACTER SET UNICODE,
"col2" VARCHAR (32000) NOT NULL DEFAULT '' CHARACTER SET UNICODE) NO PRIMARY INDEX;
EOT
            ,
        ];
        yield 'with single pk' => [
            'cols' => [
                TeradataColumn::createGenericColumn('col1'),
                TeradataColumn::createGenericColumn('col2'),
            ],
            'primaryKeys' => ['col1'],
            'expectedColumnNames' => ['col1', 'col2'],
            'expectedPrimaryKeys' => ['col1'],
            'query' => <<<EOT
CREATE MULTISET TABLE "$testDb"."$tableName", FALLBACK
("col1" VARCHAR (32000) NOT NULL DEFAULT '' CHARACTER SET UNICODE,
"col2" VARCHAR (32000) NOT NULL DEFAULT '' CHARACTER SET UNICODE,
PRIMARY KEY ("col1"));
EOT
            ,
        ];
        yield 'with multiple pks' => [
            'cols' => [
                TeradataColumn::createGenericColumn('col1'),
                TeradataColumn::createGenericColumn('col2'),
            ],
            'primaryKeys' => ['col1', 'col2'],
            'expectedColumnNames' => ['col1', 'col2'],
            'expectedPrimaryKeys' => ['col1', 'col2'],
            'query' => <<<EOT
CREATE MULTISET TABLE "$testDb"."$tableName", FALLBACK
("col1" VARCHAR (32000) NOT NULL DEFAULT '' CHARACTER SET UNICODE,
"col2" VARCHAR (32000) NOT NULL DEFAULT '' CHARACTER SET UNICODE,
PRIMARY KEY ("col1", "col2"));
EOT
            ,
        ];
    }


    /**
     * @return \Generator<string, array{
     *     definition: TeradataTableDefinition,
     *     query: string,
     *     createPrimaryKeys: bool
     * }>
     */
    public function createTableTestFromDefinitionSqlProvider(): Generator
    {
        $testDb = $this->getDatabaseName();
        $tableName = self::TABLE_GENERIC;

        yield 'no keys' => [
            'definition' => new TeradataTableDefinition(
                $testDb,
                $tableName,
                false,
                new ColumnCollection(
                    [
                        TeradataColumn::createGenericColumn('col1'),
                        TeradataColumn::createGenericColumn('col2'),
                    ]
                ),
                []
            ),
            'query' => <<<EOT
CREATE MULTISET TABLE "$testDb"."$tableName", FALLBACK
("col1" VARCHAR (32000) NOT NULL DEFAULT '' CHARACTER SET UNICODE,
"col2" VARCHAR (32000) NOT NULL DEFAULT '' CHARACTER SET UNICODE) NO PRIMARY INDEX;
EOT
            ,
            'createPrimaryKeys' => true,
        ];
        yield 'with single pk' => [
            'definition' => new TeradataTableDefinition(
                $testDb,
                $tableName,
                false,
                new ColumnCollection(
                    [
                        TeradataColumn::createGenericColumn('col1'),
                        TeradataColumn::createGenericColumn('col2'),
                    ]
                ),
                ['col1']
            ),
            'query' => <<<EOT
CREATE MULTISET TABLE "$testDb"."$tableName", FALLBACK
("col1" VARCHAR (32000) NOT NULL DEFAULT '' CHARACTER SET UNICODE,
"col2" VARCHAR (32000) NOT NULL DEFAULT '' CHARACTER SET UNICODE,
PRIMARY KEY ("col1"));
EOT
            ,
            'createPrimaryKeys' => true,
        ];
        yield 'with multiple pks' => [
            'definition' => new TeradataTableDefinition(
                $testDb,
                $tableName,
                false,
                new ColumnCollection(
                    [
                        TeradataColumn::createGenericColumn('col1'),
                        TeradataColumn::createGenericColumn('col2'),
                    ]
                ),
                ['col1', 'col2']
            ),
            'query' => <<<EOT
CREATE MULTISET TABLE "$testDb"."$tableName", FALLBACK
("col1" VARCHAR (32000) NOT NULL DEFAULT '' CHARACTER SET UNICODE,
"col2" VARCHAR (32000) NOT NULL DEFAULT '' CHARACTER SET UNICODE,
PRIMARY KEY ("col1", "col2"));
EOT
            ,
            'createPrimaryKeys' => true,
        ];

        yield 'with multiple pks no definition' => [
            'definition' => new TeradataTableDefinition(
                $testDb,
                $tableName,
                false,
                new ColumnCollection(
                    [
                        TeradataColumn::createGenericColumn('col1'),
                        TeradataColumn::createGenericColumn('col2'),
                    ]
                ),
                ['col1', 'col2']
            ),
            'query' => <<<EOT
CREATE MULTISET TABLE "$testDb"."$tableName", FALLBACK
("col1" VARCHAR (32000) NOT NULL DEFAULT '' CHARACTER SET UNICODE,
"col2" VARCHAR (32000) NOT NULL DEFAULT '' CHARACTER SET UNICODE) NO PRIMARY INDEX;
EOT
            ,
            'createPrimaryKeys' => false,
        ];
    }

    /**
     * @dataProvider createTableTestFromDefinitionSqlProvider
     */
    public function testGetCreateTableCommandFromDefinition(
        TeradataTableDefinition $definition,
        string $expectedSql,
        bool $createPrimaryKeys
    ): void {
        $this->cleanDatabase($this->getDatabaseName());
        $this->createDatabase($this->getDatabaseName());
        $sql = $this->qb->getCreateTableCommandFromDefinition($definition, $createPrimaryKeys);
        self::assertSame($expectedSql, $sql);
        $this->connection->executeQuery($sql);

        // test table properties
        $tableReflection = new TeradataTableReflection(
            $this->connection,
            $this->getDatabaseName(),
            self::TABLE_GENERIC
        );
        self::assertSame($definition->getColumnsNames(), $tableReflection->getColumnsNames());
        if ($createPrimaryKeys) {
            self::assertSame($definition->getPrimaryKeysNames(), $tableReflection->getPrimaryKeysNames());
        } else {
            self::assertSame([], $tableReflection->getPrimaryKeysNames());
        }
    }
}
