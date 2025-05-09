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
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseTestingExtension::class)]
#[RequiresTestDatabase(
    new SqliteConnectionAdapterFactory(
        initialSchemaPath: __DIR__ . '/../../resources/schemas/sqlite.sql'
    ),
    new TransactionWithRollback()
)]
final class PhpUnitIntegrationTest extends TestCase {

    #[InjectTestDatabase]
    private static TestDatabase $testDatabase;

    #[LoadFixture(
        new SingleRecordFixture('my_table', ['name' => 'Single record'])
    )]
    public function testWithSingleLoadedFixtureHasCorrectTableAvailable() : void {
        $table = self::$testDatabase->table('my_table');

        self::assertSame('my_table', $table->name());
        self::assertCount(1, $table);
        self::assertSame('Single record', $table->row(0)->get('name'));
    }

    #[LoadFixture(
        new SingleRecordFixture('my_table', ['name' => 'one']),
        new SingleRecordFixture('my_table', ['name' => 'two'])
    )]
    public function testWithMultipleLoadedFixtureHasCorrectTableAvailable() : void {
        $table = self::$testDatabase->table('my_table');

        self::assertSame('my_table', $table->name());
        self::assertCount(2, $table);
        self::assertSame('one', $table->row(0)->get('name'));
        self::assertSame('two', $table->row(1)->get('name'));
    }

    public function testLoadingDataFromWithinMethodThroughTestDatabase() : void {
        $table = self::$testDatabase->table('my_table');

        self::assertCount(0, $table);

        self::$testDatabase->loadFixtures([
            new SingleRecordFixture('my_table', ['name' => 'from within test database'])
        ]);

        $table->reload();

        self::assertCount(1, $table);
        self::assertSame('from within test database', $table->row(0)->get('name'));
    }

}