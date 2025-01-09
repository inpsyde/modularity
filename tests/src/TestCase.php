<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Tests;

use Brain\Monkey;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ExtendingModule;
use Inpsyde\Modularity\Module\FactoryModule;
use Inpsyde\Modularity\Module\Module;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\Modularity\Package;
use Inpsyde\Modularity\Properties\Properties;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Error\Deprecated;
use PHPUnit\Framework\TestCase as FrameworkTestCase;
use Psr\Container\ContainerInterface;

abstract class TestCase extends FrameworkTestCase
{
    use MockeryPHPUnitIntegration;

    private ?int $currentErrorReporting = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Monkey\Functions\stubEscapeFunctions();
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
    protected function stubProperties(
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
    protected function stubModule(string $id = 'module', string ...$interfaces): Module
    {
        $interfaces or $interfaces[] = Module::class;

        $stub = \Mockery::mock(...$interfaces);
        $stub->allows('id')->andReturn($id);

        if (in_array(ServiceModule::class, $interfaces, true)) {
            $stub->allows('services')->byDefault()->andReturn([]);
        }

        if (in_array(FactoryModule::class, $interfaces, true)) {
            $stub->allows('factories')->byDefault()->andReturn([]);
        }

        if (in_array(ExtendingModule::class, $interfaces, true)) {
            $stub->allows('extensions')->byDefault()->andReturn([]);
        }

        if (in_array(ExecutableModule::class, $interfaces, true)) {
            $stub->allows('run')->byDefault()->andReturn(false);
        }

        return $stub;
    }

    /**
     * @param string $suffix
     * @param bool $debug
     * @return Package
     */
    protected function stubSimplePackage(string $suffix, bool $debug = false): Package
    {
        $module = $this->stubModule("module_{$suffix}", ServiceModule::class);
        $module->expects('services')->andReturn($this->stubServices("service_{$suffix}"));
        $properties = $this->stubProperties("package_{$suffix}", $debug);

        return Package::new($properties)->addModule($module);
    }

    /**
     * @param string ...$ids
     * @return array<string, callable>
     */
    protected function stubServices(string ...$ids): array
    {
        $services = [];
        foreach ($ids as $id) {
            $services[$id] = static function () use ($id): \ArrayObject {
                return new \ArrayObject(['id' => $id]);
            };
        }

        return $services;
    }

    /**
     * @param string ...$ids
     * @return ContainerInterface
     */
    protected function stubContainer(string ...$ids): ContainerInterface
    {
        return new class ($this->stubServices(...$ids)) implements ContainerInterface
        {
            /** @var array<string, callable> */
            private array $services;

            /** @param array<string, callable> $services */
            public function __construct(array $services)
            {
                $this->services = $services;
            }

            /** @return mixed */
            public function get(string $id)
            {
                if (!isset($this->services[$id])) {
                    throw new \Exception("Service {$id} not found.");
                }

                return $this->services[$id]($this);
            }

            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }

    /**
     * @return void
     */
    protected function ignoreDeprecations(): void
    {
        \Brain\Monkey\Actions\expectDone('wp_trigger_error_run')->atLeast()->once();

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
