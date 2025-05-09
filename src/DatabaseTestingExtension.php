<?php declare(strict_types=1);

namespace Cspray\DatabaseTesting\PhpUnit;

use Cspray\DatabaseTesting\Internal\FixtureAttributeAwareDatabaseTest;
use Cspray\DatabaseTesting\RequiresTestDatabaseSettings;
use Cspray\DatabaseTesting\TestDatabase;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Finished as TestFinished;
use PHPUnit\Event\Test\FinishedSubscriber as TestFinishedSubscriber;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use PHPUnit\Event\TestSuite\FinishedSubscriber as TestSuiteFinishedSubscriber;
use PHPUnit\Event\TestSuite\Finished as TestSuiteFinished;
use PHPUnit\Event\TestSuite\Started;
use PHPUnit\Event\TestSuite\StartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use ReflectionAttribute;
use stdClass;

final class DatabaseTestingExtension implements Extension {

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters) : void {
        $data = new stdClass();
        $data->connectionAdapter = null;
        $data->cleanupStrategy = null;
        $facade->registerSubscribers(
            $this->establishDatabaseConnectionSubscriber($data),
            $this->cleanupDatabaseBeforeTestSubscriber($data),
            $this->tearDownDatabaseAfterTestSubscriber($data),
            $this->closeDatabaseConnectionSubscriber($data),
        );
    }

    private function establishDatabaseConnectionSubscriber(stdClass $data) : StartedSubscriber {
        return new class($data) implements StartedSubscriber {

            public function __construct(
                private readonly stdClass $data
            ) {}

            public function notify(Started $event) : void {
                $firstTest = $event->testSuite()->tests()->asArray()[0];
                assert($firstTest instanceof TestMethod);

                $reflection = new \ReflectionClass($firstTest->className());
                $requiresTestDatabaseAttribute = $reflection->getAttributes(
                    RequiresTestDatabaseSettings::class,
                    ReflectionAttribute::IS_INSTANCEOF
                );
                if ($requiresTestDatabaseAttribute !== []) {
                    $requiresTestDatabase = $requiresTestDatabaseAttribute[0]->newInstance();
                    assert($requiresTestDatabase instanceof RequiresTestDatabaseSettings);

                    $this->data->cleanupStrategy = $requiresTestDatabase->cleanupStrategy();
                    $this->data->connectionAdapter = $requiresTestDatabase->connectionAdapterFactory()->createConnectionAdapter();
                    $this->data->connectionAdapter->establishConnection();

                    $testDatabaseReflection = new \ReflectionClass(TestDatabase::class);
                    $testDatabase = $testDatabaseReflection->newInstanceWithoutConstructor();
                    $constructor = $testDatabaseReflection->getConstructor();
                    $constructor->setAccessible(true);
                    $constructor->invoke($testDatabase, $this->data->connectionAdapter);

                    foreach ($reflection->getProperties() as $reflectionProperty) {
                        if (!$reflectionProperty->isStatic()) {
                            continue;
                        }

                        $injectTestDatabase = $reflectionProperty->getAttributes(InjectTestDatabase::class);
                        if ($injectTestDatabase !== []) {
                            $reflectionProperty->setValue(null, $testDatabase);
                        }
                    }
                }
            }
        };
    }

    private function cleanupDatabaseBeforeTestSubscriber(stdClass $data) : PreparationStartedSubscriber {
        return new class($data) implements PreparationStartedSubscriber {

            public function __construct(
                private readonly stdClass $data
            ) {}

            public function notify(PreparationStarted $event) : void {
                if ($this->data->cleanupStrategy !== null && $event->test()->isTestMethod()) {
                    $testMethod = $event->test();
                    assert($testMethod instanceof TestMethod);
                    $test = FixtureAttributeAwareDatabaseTest::fromTestMethodWithPossibleFixtures(
                        $testMethod->className(), $testMethod->methodName()
                    );
                    $this->data->cleanupStrategy->cleanupBeforeTest($test, $this->data->connectionAdapter);
                    $this->data->connectionAdapter->insert($test->fixtures());
                }
            }
        };
    }

    private function tearDownDatabaseAfterTestSubscriber(stdClass $data) : TestFinishedSubscriber {
        return new class($data) implements TestFinishedSubscriber {

            public function __construct(
                private readonly stdClass $data
            ) {}

            public function notify(TestFinished $event) : void {
                if ($this->data->cleanupStrategy !== null && $event->test()->isTestMethod()) {
                    $testMethod = $event->test();
                    assert($testMethod instanceof TestMethod);
                    $test = FixtureAttributeAwareDatabaseTest::fromTestMethodWithPossibleFixtures(
                        $testMethod->className() , $testMethod->methodName()
                    );
                    $this->data->cleanupStrategy->teardownAfterTest($test, $this->data->connectionAdapter);
                }
            }
        };
    }

    private function closeDatabaseConnectionSubscriber(stdClass $data) : TestSuiteFinishedSubscriber {
        // AfterLastTestMethodCalled event is only triggered when a TestCase explicitly defines tearDownAfterClass
        return new class($data) implements TestSuiteFinishedSubscriber {

            public function __construct(
                private readonly stdClass $data,
            ) {}

            public function notify(TestSuiteFinished $event) : void {
                if (!$event->testSuite()->isForTestClass() || $this->data->connectionAdapter === null) {
                    return;
                }

                $this->data->connectionAdapter->closeConnection();
            }
        };
    }
}
