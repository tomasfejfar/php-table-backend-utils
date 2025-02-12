<?php

declare(strict_types=1);

namespace Tests\Keboola\TableBackendUtils\Unit\Table\Exasol;

use Generator;
use Keboola\Datatype\Definition\Exasol;
use Keboola\TableBackendUtils\Column\ColumnCollection;
use Keboola\TableBackendUtils\Column\Exasol\ExasolColumn;
use Keboola\TableBackendUtils\QueryBuilderException;
use Keboola\TableBackendUtils\Table\Exasol\ExasolTableQueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers ExasolTableQueryBuilder
 * @uses   ColumnCollection
 */
class ExasolTableQueryBuilderTest extends TestCase
{
    private ExasolTableQueryBuilder $qb;

    public function setUp(): void
    {
        $this->qb = new ExasolTableQueryBuilder();
    }

    /**
     * @param ExasolColumn[] $columns
     * @param string[] $PKs
     * @dataProvider createTableInvalidPKsProvider
     * @throws \Exception
     */
    public function testGetCreateCommandWithInvalidPks(array $columns, array $PKs, string $exceptionString): void
    {
        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage($exceptionString);
        $this->qb->getCreateTableCommand('testDb', 'testTable', new ColumnCollection($columns), $PKs);
        self::fail('Should fail because of invalid PKs');
    }

    /**
     * @return \Generator<string, mixed, mixed, mixed>
     */
    public function createTableInvalidPKsProvider(): Generator
    {
        yield 'key of ouf columns' => [
            'cols' => [
                ExasolColumn::createGenericColumn('col1'),
                ExasolColumn::createGenericColumn('col2'),
            ],
            'primaryKeys' => ['colNotExisting'],
            'exceptionString' => 'Trying to set colNotExisting as PKs but not present in columns',
        ];
        yield 'pk on nullable type' => [
            'cols' => [
                new ExasolColumn('col1', new Exasol(Exasol::TYPE_VARCHAR, ['nullable' => true])),
            ],
            'primaryKeys' => ['col1'],
            'exceptionString' => 'Trying to set PK on column col1 but this column is nullable',
        ];
    }

    public function testCreateTableWithInvalidTableName(): void
    {
        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage(
            'Invalid table name testTable.: Only alphanumeric characters dash and underscores are allowed.'
        );
        $this->qb->getCreateTableCommand('testDb', 'testTable.', new ColumnCollection([]));
        self::fail('Should fail because of invalid table name');
    }

    public function testRenameTableWithInvalidTableName(): void
    {
        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage(
            'Invalid table name testTable.: Only alphanumeric characters dash and underscores are allowed.'
        );
        $this->qb->getRenameTableCommand('testDb', 'testTable', 'testTable.');
        self::fail('Should fail because of invalid table name');
    }
}
