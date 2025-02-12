<?php

declare(strict_types=1);

namespace Tests\Keboola\TableBackendUtils\Functional\Synapse\Table;

use Doctrine\DBAL\Exception;
use Generator;
use Keboola\Datatype\Definition\Synapse;
use Keboola\TableBackendUtils\Column\ColumnCollection;
use Keboola\TableBackendUtils\Column\SynapseColumn;
use Keboola\TableBackendUtils\Table\SynapseTableDefinition;
use Keboola\TableBackendUtils\Table\SynapseTableQueryBuilder;
use Keboola\TableBackendUtils\Table\SynapseTableReflection;
use Keboola\TableBackendUtils\TableNotExistsReflectionException;
use Tests\Keboola\TableBackendUtils\Functional\Synapse\SynapseBaseCase;

/**
 * @covers SynapseTableQueryBuilder
 */
class SynapseTableQueryBuilderTest extends SynapseBaseCase
{
    public const TEST_SCHEMA = self::TESTS_PREFIX . 'qb-schema';
    public const TEST_SCHEMA_2 = self::TESTS_PREFIX . 'qb-schema2';
    public const TEST_STAGING_TABLE = '#stagingTable';
    public const TEST_STAGING_TABLE_2 = '#stagingTable2';
    public const TEST_TABLE = self::TESTS_PREFIX . 'test';
    public const TEST_TABLE_2 = self::TESTS_PREFIX . 'test2';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropAllWithinSchema(self::TEST_SCHEMA);
        $this->dropAllWithinSchema(self::TEST_SCHEMA_2);
    }

    public function testGetCreateTempTableCommand(): void
    {
        $this->createTestSchema();
        $qb = new SynapseTableQueryBuilder();
        $sql = $qb->getCreateTempTableCommand(
            self::TEST_SCHEMA,
            '#' . self::TEST_TABLE,
            new ColumnCollection([
                SynapseColumn::createGenericColumn('col1'),
                SynapseColumn::createGenericColumn('col2'),
            ])
        );

        $this->assertEquals(
        // phpcs:ignore
            'CREATE TABLE [utils-test_qb-schema].[#utils-test_test] ([col1] NVARCHAR(4000) NOT NULL DEFAULT \'\', [col2] NVARCHAR(4000) NOT NULL DEFAULT \'\') WITH (HEAP, LOCATION = USER_DB)',
            $sql
        );
        $this->connection->executeStatement($sql);
        // try to create same table
        $this->expectException(Exception::class);
        $this->connection->executeStatement($sql);
    }

    private function createTestSchema(): void
    {
        $this->connection->executeStatement($this->schemaQb->getCreateSchemaCommand(self::TEST_SCHEMA));
        $this->connection->executeStatement($this->schemaQb->getCreateSchemaCommand(self::TEST_SCHEMA_2));
    }

    public function testGetCreateTableCommand(): void
    {
        $this->createTestSchema();
        $cols = [
            SynapseColumn::createGenericColumn('col1'),
            SynapseColumn::createGenericColumn('col2'),
        ];
        $qb = new SynapseTableQueryBuilder();
        $sql = $qb->getCreateTableCommand(self::TEST_SCHEMA, self::TEST_TABLE, new ColumnCollection($cols));
        $this->assertEquals(
        // phpcs:ignore
            'CREATE TABLE [utils-test_qb-schema].[utils-test_test] ([col1] NVARCHAR(4000) NOT NULL DEFAULT \'\', [col2] NVARCHAR(4000) NOT NULL DEFAULT \'\')',
            $sql
        );
        $this->connection->executeStatement($sql);
        $ref = $this->getSynapseTableReflection();
        $this->assertNotNull($ref->getObjectId());
        $this->assertEqualsCanonicalizing(['col1', 'col2'], $ref->getColumnsNames());

        $this->expectException(Exception::class);
        $this->connection->executeStatement($sql);
    }

    private function getSynapseTableReflection(
        string $schema = self::TEST_SCHEMA,
        string $table = self::TEST_TABLE
    ): SynapseTableReflection {
        return new SynapseTableReflection($this->connection, $schema, $table);
    }

    public function testGetCreateTableCommandWithTimestamp(): void
    {
        $this->createTestSchema();
        $cols = [
            SynapseColumn::createGenericColumn('col1'),
            SynapseColumn::createGenericColumn('col2'),
            new SynapseColumn('_timestamp', new Synapse(Synapse::TYPE_DATETIME2)),
        ];
        $qb = new SynapseTableQueryBuilder();
        $sql = $qb->getCreateTableCommand(self::TEST_SCHEMA, self::TEST_TABLE, new ColumnCollection($cols));
        $this->assertEquals(
        // phpcs:ignore
            'CREATE TABLE [utils-test_qb-schema].[utils-test_test] ([col1] NVARCHAR(4000) NOT NULL DEFAULT \'\', [col2] NVARCHAR(4000) NOT NULL DEFAULT \'\', [_timestamp] DATETIME2)',
            $sql
        );
        $this->connection->executeStatement($sql);
        $ref = $this->getSynapseTableReflection();
        $this->assertNotNull($ref->getObjectId());
        $this->assertEqualsCanonicalizing(['col1', 'col2', '_timestamp'], $ref->getColumnsNames());
    }

    public function testGetCreateTableCommandWithTimestampAndPrimaryKeys(): void
    {
        $this->createTestSchema();
        $cols = [
            new SynapseColumn('pk1', new Synapse(Synapse::TYPE_INT)),
            SynapseColumn::createGenericColumn('col1'),
            SynapseColumn::createGenericColumn('col2'),
            new SynapseColumn('_timestamp', new Synapse(Synapse::TYPE_DATETIME2)),
        ];
        $qb = new SynapseTableQueryBuilder();
        $sql = $qb->getCreateTableCommand(
            self::TEST_SCHEMA,
            self::TEST_TABLE,
            new ColumnCollection($cols),
            ['pk1', 'col1']
        );
        $this->assertEquals(
        // phpcs:ignore
            'CREATE TABLE [utils-test_qb-schema].[utils-test_test] ([pk1] INT, [col1] NVARCHAR(4000) NOT NULL DEFAULT \'\', [col2] NVARCHAR(4000) NOT NULL DEFAULT \'\', [_timestamp] DATETIME2, PRIMARY KEY NONCLUSTERED([pk1],[col1]) NOT ENFORCED)',
            $sql
        );
        $this->connection->executeStatement($sql);
        $ref = $this->getSynapseTableReflection();
        $this->assertNotNull($ref->getObjectId());
        $this->assertEqualsCanonicalizing(['pk1', 'col1', 'col2', '_timestamp'], $ref->getColumnsNames());
    }

    /**
     * @return \Generator<string, array<int, string|bool>>
     */
    public function createTableTestSqlProvider(): Generator
    {
        $schema = self::TEST_SCHEMA;
        $tableName = 'createTableTest';

        yield 'multiple columns' => [
            <<<EOT
CREATE TABLE [$schema].[$tableName] (
          [int_def] INT NOT NULL DEFAULT 0,
          [var_def] nvarchar(1000) NOT NULL DEFAULT (''),
          [num_def] NUMERIC(10,5) DEFAULT ((1.00)),
          [_time] datetime2 NOT NULL DEFAULT '2020-02-01 00:00:00'
)  
WITH
    (
      CLUSTERED COLUMNSTORE INDEX,
      DISTRIBUTION = ROUND_ROBIN
    ) 
EOT
            ,
        ];

        yield 'hash distribution' => [
            <<<EOT
CREATE TABLE [$schema].[$tableName] (
    id int NOT NULL
)
WITH
    (
      CLUSTERED COLUMNSTORE INDEX,
      DISTRIBUTION = HASH(id)
    )
EOT
            ,
        ];

        yield 'single primary key' => [
            <<<EOT
CREATE TABLE [$schema].[$tableName] (
    id int NOT NULL,
    PRIMARY KEY NONCLUSTERED(id) NOT ENFORCED
)
WITH
    (
      CLUSTERED COLUMNSTORE INDEX,
      DISTRIBUTION = HASH(id)
    )
EOT
            ,
        ];

        yield 'multiple primary keys' => [
            <<<EOT
CREATE TABLE [$schema].[$tableName] (
    id int NOT NULL,
    id2 int NOT NULL,
    PRIMARY KEY NONCLUSTERED(id, id2) NOT ENFORCED
)
WITH
    (
      CLUSTERED COLUMNSTORE INDEX,
      DISTRIBUTION = HASH(id)
    )
EOT
            ,
        ];

        yield 'multiple primary keys, don\'t create PK' => [
            <<<EOT
CREATE TABLE [$schema].[$tableName] (
    id int NOT NULL,
    id2 int NOT NULL,
    PRIMARY KEY NONCLUSTERED(id, id2) NOT ENFORCED
)
WITH
    (
      CLUSTERED COLUMNSTORE INDEX,
      DISTRIBUTION = HASH(id)
    )
EOT
            ,
            false,
        ];
    }

    /**
     * @dataProvider createTableTestSqlProvider
     */
    public function testGetCreateTableCommandFromDefinition(string $sql, bool $definePrimaryKeys = true): void
    {
        $this->createTestSchema();

        $schema = self::TEST_SCHEMA;
        $tableName = 'createTableTest';
        $this->connection->executeStatement($sql);
        // get table definition
        $ref = new SynapseTableReflection($this->connection, $schema, $tableName);
        $definitionSource = $ref->getTableDefinition();
        $qb = new SynapseTableQueryBuilder();
        // drop source table
        $sql = $qb->getDropTableCommand($schema, $tableName);
        $this->connection->executeStatement($sql);
        // create table from definition
        $sql = $qb->getCreateTableCommandFromDefinition(
            $definitionSource,
            $definePrimaryKeys
        );
        $this->connection->executeStatement($sql);

        $ref = new SynapseTableReflection($this->connection, $schema, $tableName);
        $definitionCreated = $ref->getTableDefinition();

        $this->assertDefinitionsSame(
            $definitionSource,
            $definitionCreated,
            $definePrimaryKeys
        );
    }

    private function assertDefinitionsSame(
        SynapseTableDefinition $expectedDefinition,
        SynapseTableDefinition $actualDefinition,
        bool $expectPrimaryKeys = true
    ): void {
        if ($expectPrimaryKeys) {
            self::assertCount(
                count($expectedDefinition->getPrimaryKeysNames()),
                $actualDefinition->getPrimaryKeysNames()
            );
            self::assertSame(
                $expectedDefinition->getPrimaryKeysNames(),
                $actualDefinition->getPrimaryKeysNames()
            );
        } else {
            self::assertCount(0, $actualDefinition->getPrimaryKeysNames());
        }
        self::assertCount(
            count($expectedDefinition->getColumnsNames()),
            $actualDefinition->getColumnsNames()
        );
        self::assertSame($expectedDefinition->getColumnsNames(), $actualDefinition->getColumnsNames());
        self::assertSame($expectedDefinition->isTemporary(), $actualDefinition->isTemporary());
        self::assertSame(
            $expectedDefinition->getTableDistribution()->getDistributionColumnsNames(),
            $actualDefinition->getTableDistribution()->getDistributionColumnsNames()
        );
        self::assertSame(
            $expectedDefinition->getTableDistribution()->getDistributionName(),
            $actualDefinition->getTableDistribution()->getDistributionName()
        );
        self::assertSame(
            $expectedDefinition->getTableIndex()->getIndexType(),
            $actualDefinition->getTableIndex()->getIndexType()
        );
        self::assertSame(
            $expectedDefinition->getTableIndex()->getIndexedColumnsNames(),
            $actualDefinition->getTableIndex()->getIndexedColumnsNames()
        );
        self::assertCount(
            count($expectedDefinition->getColumnsDefinitions()),
            $actualDefinition->getColumnsDefinitions()
        );
        /** @var SynapseColumn[] $actualColumns */
        $actualColumns = iterator_to_array($actualDefinition->getColumnsDefinitions());
        /**
         * @var int $key
         * @var SynapseColumn $expectedColumn
         */
        foreach (iterator_to_array($expectedDefinition->getColumnsDefinitions()) as $key => $expectedColumn) {
            self::assertSame($expectedColumn->getColumnName(), $actualColumns[$key]->getColumnName());
            self::assertSame(
                $expectedColumn->getColumnDefinition()->getSQLDefinition(),
                $actualColumns[$key]->getColumnDefinition()->getSQLDefinition()
            );
        }
    }

    public function testGetDropCommand(): void
    {
        $this->createTestSchema();
        $this->createTestTable();
        $qb = new SynapseTableQueryBuilder();
        $sql = $qb->getDropTableCommand(self::TEST_SCHEMA, self::TEST_TABLE);

        $this->assertEquals(
            'DROP TABLE [utils-test_qb-schema].[utils-test_test]',
            $sql
        );

        $this->connection->executeStatement($sql);

        $ref = new SynapseTableReflection($this->connection, self::TEST_SCHEMA, self::TEST_TABLE_2);
        $this->assertNotNull($ref->getObjectId());

        $ref = $this->getSynapseTableReflection();
        $this->expectException(TableNotExistsReflectionException::class);
        $this->expectExceptionMessage('Table "utils-test_qb-schema.utils-test_test" does not exist.');
        $ref->getObjectId();
    }

    private function createTestTable(): void
    {
        foreach ([self::TEST_TABLE, self::TEST_TABLE_2] as $t) {
            $schema = self::TEST_SCHEMA;
            $this->connection->executeStatement(<<<EOT
CREATE TABLE [$schema].[$t] (
    id int NOT NULL
)  
WITH
    (
      PARTITION ( id RANGE LEFT FOR VALUES ( )),  
      CLUSTERED COLUMNSTORE INDEX  
    ) 
EOT
            );
        }
    }

    public function testGetRenameTableCommand(): void
    {
        $renameTo = 'newTable';
        $this->createTestSchema();
        $this->createTestTableWithColumns();
        $qb = new SynapseTableQueryBuilder();
        $sql = $qb->getRenameTableCommand(self::TEST_SCHEMA, self::TEST_TABLE, $renameTo);

        $this->assertEquals(
            'RENAME OBJECT [utils-test_qb-schema].[utils-test_test] TO [newTable]',
            $sql
        );

        $this->connection->executeStatement($sql);

        $ref = $this->getSynapseTableReflection(self::TEST_SCHEMA, $renameTo);
        $this->assertNotFalse($ref->getObjectId());

        $ref = $this->getSynapseTableReflection();
        $this->expectException(TableNotExistsReflectionException::class);
        $this->expectExceptionMessage('Table "utils-test_qb-schema.utils-test_test" does not exist.');
        $ref->getObjectId();
    }

    private function createTestTableWithColumns(bool $includeTimestamp = false, bool $includePrimaryKey = false): void
    {
        $table = sprintf('[%s].[%s]', self::TEST_SCHEMA, self::TEST_TABLE);
        $timestampDeclaration = '';
        if ($includeTimestamp) {
            $timestampDeclaration = ',_timestamp datetime';
        }
        $idDeclaration = 'id varchar';
        if ($includePrimaryKey) {
            $idDeclaration = 'id INT PRIMARY KEY NONCLUSTERED NOT ENFORCED';
        }

        $this->connection->executeStatement(<<<EOT
CREATE TABLE $table (  
    $idDeclaration,
    col1 varchar,
    col2 varchar
    $timestampDeclaration
)  
WITH
    (
      PARTITION ( id RANGE LEFT FOR VALUES ( )),  
      CLUSTERED COLUMNSTORE INDEX  
    ) 
EOT
        );
    }

    public function testGetTruncateTableCommandTempTable(): void
    {
        $this->createTestSchema();
        $this->createCreateTempTableCommandWithData();

        $ref = $this->getSynapseTableReflection(self::TEST_SCHEMA, self::TEST_STAGING_TABLE);
        $ref2 = $this->getSynapseTableReflection(self::TEST_SCHEMA, self::TEST_STAGING_TABLE_2);
        $this->assertEquals(3, $ref->getRowsCount());
        $this->assertEquals(3, $ref2->getRowsCount());

        $qb = new SynapseTableQueryBuilder();
        $sql = $qb->getTruncateTableCommand(self::TEST_SCHEMA, self::TEST_STAGING_TABLE);
        $this->assertEquals(
            'TRUNCATE TABLE [utils-test_qb-schema].[#stagingTable]',
            $sql
        );
        $this->connection->executeStatement($sql);

        $this->assertEquals(0, $ref->getRowsCount());
        $this->assertEquals(3, $ref2->getRowsCount());
    }


    public function testGetTruncateTableCommand(): void
    {
        $this->createTestSchema();
        $this->createTestTable();

        foreach ([self::TEST_TABLE, self::TEST_TABLE_2] as $t) {
            $this->connection->executeStatement(sprintf(
                'INSERT INTO [%s].[%s]([id]) VALUES (1)',
                self::TEST_SCHEMA,
                $t
            ));
            $this->connection->executeStatement(sprintf(
                'INSERT INTO [%s].[%s]([id]) VALUES (2)',
                self::TEST_SCHEMA,
                $t
            ));
            $this->connection->executeStatement(sprintf(
                'INSERT INTO [%s].[%s]([id]) VALUES (3)',
                self::TEST_SCHEMA,
                $t
            ));
        }

        $ref = $this->getSynapseTableReflection(self::TEST_SCHEMA, self::TEST_TABLE);
        $ref2 = $this->getSynapseTableReflection(self::TEST_SCHEMA, self::TEST_TABLE_2);
        $this->assertEquals(3, $ref->getRowsCount());
        $this->assertEquals(3, $ref2->getRowsCount());

        $qb = new SynapseTableQueryBuilder();
        $sql = $qb->getTruncateTableCommand(self::TEST_SCHEMA, self::TEST_TABLE);
        $this->assertEquals(
            'TRUNCATE TABLE [utils-test_qb-schema].[utils-test_test]',
            $sql
        );
        $this->connection->executeStatement($sql);

        $this->assertEquals(0, $ref->getRowsCount());
        $this->assertEquals(3, $ref2->getRowsCount());
    }

    private function createCreateTempTableCommandWithData(bool $includeEmptyValues = false): void
    {
        foreach ([self::TEST_STAGING_TABLE, self::TEST_STAGING_TABLE_2] as $t) {
            $this->connection->executeStatement($this->tableQb->getCreateTempTableCommand(
                self::TEST_SCHEMA,
                $t,
                new ColumnCollection([
                    SynapseColumn::createGenericColumn('pk1'),
                    SynapseColumn::createGenericColumn('pk2'),
                    SynapseColumn::createGenericColumn('col1'),
                    SynapseColumn::createGenericColumn('col2'),
                ])
            ));
            $this->connection->executeStatement(
                sprintf(
                    'INSERT INTO [%s].[%s]([pk1],[pk2],[col1],[col2]) VALUES (1,1,\'1\',\'1\')',
                    self::TEST_SCHEMA,
                    $t
                )
            );
            $this->connection->executeStatement(
                sprintf(
                    'INSERT INTO [%s].[%s]([pk1],[pk2],[col1],[col2]) VALUES (1,1,\'1\',\'1\')',
                    self::TEST_SCHEMA,
                    $t
                )
            );
            $this->connection->executeStatement(
                sprintf(
                    'INSERT INTO [%s].[%s]([pk1],[pk2],[col1],[col2]) VALUES (2,2,\'2\',\'2\')',
                    self::TEST_SCHEMA,
                    $t
                )
            );

            if ($includeEmptyValues) {
                $this->connection->executeStatement(
                    sprintf(
                        'INSERT INTO [%s].[%s]([pk1],[pk2],[col1],[col2]) VALUES (2,2,\'\',NULL)',
                        self::TEST_SCHEMA,
                        $t
                    )
                );
            }
        }
    }
}
