<?php declare(strict_types=1);

namespace Cspray\DatabaseTesting\PhpUnit\Tests\Integration;

use Cspray\DatabaseTesting\DatabaseCleanup\TransactionWithRollback;
use Cspray\DatabaseTesting\Fixture\LoadFixture;
use Cspray\DatabaseTesting\Fixture\SingleRecordFixture;
use Cspray\DatabaseTesting\Pdo\Sqlite\SqliteConnectionAdapterFactory;
use Cspray\DatabaseTesting\PhpUnit\DatabaseTestingExtension;
use Cspray\DatabaseTesting\PhpUnit\InjectTestDatabase;
use Cspray\DatabaseTesting\PhpUnit\RequiresTestDatabase;
use Cspray\DatabaseTesting\TestDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseTestingExtension::class)]
#[RequiresTestDatabase(
    new SqliteConnectionAdapterFactory(
        initialSchemaPath: __DIR__ . '/../../resources/schemas/sqlite.sql'
    ),
    new TransactionWithRollback()
)]
final class UsesDataProviderIntegrationTest extends TestCase {

    #[InjectTestDatabase]
    private static TestDatabase $testDatabase;

    public static function recordData() : array {
        return [
            'first' => [0, 'first record'],
            'second' => [1, 'second record']
        ];
    }

    #[DataProvider('recordData')]
    #[LoadFixture(
        new SingleRecordFixture('my_table', ['name' => 'first record']),
        new SingleRecordFixture('my_table', ['name' => 'second record'])
    )]
    public function testLoadFixturesWithTestThatUsesDataProvider(int $row, string $name) : void {
        $table = self::$testDatabase->table('my_table');

        self::assertCount(2, $table);
        self::assertSame($name, $table->row($row)->get('name'));
    }

}