<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests;

use Brain\Monkey;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ExtendingModule;
use Inpsyde\Modularity\Module\FactoryModule;
use Inpsyde\Modularity\Module\Module;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Properties\Properties;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Error\Deprecated;
use PHPUnit\Framework\TestCase as FrameworkTestCase;

abstract class TestCase extends FrameworkTestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var int|null
     */
    private $currentErrorReporting = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
        if (is_int($this->currentErrorReporting)) {
            error_reporting($this->currentErrorReporting);
        }
    }

    /**
     * @param string $basename
     * @param bool $isDebug
     *
     * @return Properties|MockInterface
     */
    protected function mockProperties(
        string $basename = 'basename',
        bool $isDebug = false
    ): Properties {

        $stub = \Mockery::mock(Properties::class);
        $stub->allows('basename')->andReturn($basename);
        $stub->allows('isDebug')->andReturn($isDebug);

        return $stub;
    }

    /**
     * @param string $id
     * @param class-string ...$interfaces
     * @return Module|MockInterface
     */
    protected function mockModule(string $id = 'module', string ...$interfaces): Module
    {
        $interfaces or $interfaces[] = Module::class;

        $stub = \Mockery::mock(...$interfaces);
        $stub->allows('id')->andReturn($id);

        if (in_array(ServiceModule::class, $interfaces, true) ) {
            $stub->allows('services')->byDefault()->andReturn([]);
        }

        if (in_array(FactoryModule::class, $interfaces, true) ) {
            $stub->allows('factories')->byDefault()->andReturn([]);
        }

        if (in_array(ExtendingModule::class, $interfaces, true) ) {
            $stub->allows('extensions')->byDefault()->andReturn([]);
        }

        if (in_array(ExecutableModule::class, $interfaces, true) ) {
            $stub->allows('run')->byDefault()->andReturn(false);
        }

        return $stub;
    }

    /**
     * @param string ...$ids
     * @return array<string, callable>
     */
    protected function stubServices(string ...$ids): array
    {
        $services = [];
        foreach ($ids as $id) {
            $services[$id] = static function () use ($id) {
                return new \ArrayObject(['id' => $id]);
            };
        }

        return $services;
    }

    /**
     * @return void
     */
    protected function ignoreDeprecations(): void
    {
        $this->currentErrorReporting = error_reporting();
        error_reporting($this->currentErrorReporting & ~\E_DEPRECATED & ~\E_USER_DEPRECATED);
    }

    /**
     * @return void
     */
    protected function convertDeprecationsToExceptions(): void
    {
        $this->currentErrorReporting = error_reporting();
        error_reporting($this->currentErrorReporting | \E_DEPRECATED | \E_USER_DEPRECATED);

        set_error_handler(
            static function (int $code, string $msg, ?string $file = null, ?int $line = null): void {
                throw new Deprecated($msg, $code, $file ?? '', $line ?? 0);
            },
            \E_DEPRECATED | \E_USER_DEPRECATED
        );
    }

    /**
     * @param \Throwable $throwable
     * @param string $pattern
     * @return void
     */
    protected function assertThrowableMessageMatches(\Throwable $throwable, string $pattern): void
    {
        static::assertSame(1, preg_match("/{$pattern}/i", $throwable->getMessage()));
    }
}
