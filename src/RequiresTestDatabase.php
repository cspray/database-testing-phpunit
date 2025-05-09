<?php declare(strict_types=1);

namespace Cspray\DatabaseTesting\PhpUnit;

use Attribute;
use Cspray\DatabaseTesting\ConnectionAdapter\ConnectionAdapterFactory;
use Cspray\DatabaseTesting\DatabaseCleanup\CleanupStrategy;
use Cspray\DatabaseTesting\RequiresTestDatabaseSettings;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class RequiresTestDatabase implements RequiresTestDatabaseSettings {

    public function __construct(
       private ConnectionAdapterFactory $connectionAdapterFactory,
       private CleanupStrategy $cleanupStrategy,
    ) {}

    public function connectionAdapterFactory() : ConnectionAdapterFactory {
        return $this->connectionAdapterFactory;
    }

    public function cleanupStrategy() : CleanupStrategy {
        return $this->cleanupStrategy;
    }
}