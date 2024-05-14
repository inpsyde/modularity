<?php

declare(strict_types=1);

namespace Inpsyde\Modularity\Container;

use Psr\Container\ContainerInterface as Container;

/**
 * @psalm-import-type ExtendingService from \Inpsyde\Modularity\Module\ExtendingModule
 */
class ServiceExtensions
{
    private const SERVICE_TYPE_NOT_CHANGED = 1;
    private const SERVICE_TYPE_CHANGED = 2;
    private const SERVICE_TYPE_NOT_OBJECT = 0;

    /**
     * @var array<string, list<ExtendingService>>
     */
    protected $extensions = [];

    /**
     * @param string $type
     * @return string
     */
    final public static function typeId(string $type): string
    {
        return "@instanceof<{$type}>";
    }

    /**
     * @param string $extensionId
     * @param ExtendingService $extender
     * @return static
     */
    public function add(string $extensionId, callable $extender): ServiceExtensions
    {
        isset($this->extensions[$extensionId]) or $this->extensions[$extensionId] = [];
        $this->extensions[$extensionId][] = $extender;

        return $this;
    }

    /**
     * @param string $extensionId
     * @return bool
     */
    public function has(string $extensionId): bool
    {
        return isset($this->extensions[$extensionId]);
    }

    /**
     * @param mixed $service
     * @param string $id
     * @param Container $container
     * @return mixed
     */
    final public function resolve($service, string $id, Container $container)
    {
        $service = $this->resolveById($id, $service, $container);

        return is_object($service)
            ? $this->resolveByType(get_class($service), $service, $container)
            : $service;
    }

    /**
     * @param string $id
     * @param mixed $service
     * @param Container $container
     * @return mixed
     */
    protected function resolveById(string $id, $service, Container $container)
    {
        foreach ($this->extensions[$id] ?? [] as $extender) {
            $service = $extender($service, $container);
        }

        return $service;
    }

    /**
     * @param string $className
     * @param object $service
     * @param Container $container
     * @param array $extendedClasses
     * @return mixed
     */
    protected function resolveByType(
        string $className,
        object $service,
        Container $container,
        array $extendedClasses = []
    ) {

        $extendedClasses[] = $className;

        /** @var array<class-string, list<ExtendingService>> $allCallbacks */
        $allCallbacks = [];

        // 1st group of extensions: targeting exact class
        $byClass = $this->extensions[self::typeId($className)] ?? null;
        $byClass and $allCallbacks[$className] = $byClass;

        // 2nd group of extensions: targeting parent classes
        /** @var class-string $parentName */
        foreach (class_parents($service, false) ?: [] as $parentName) {
            $byParent = $this->extensions[self::typeId($parentName)] ?? null;
            $byParent and $allCallbacks[$parentName] = $byParent;
        }

        // 3rd group of extensions: targeting implemented interfaces
        /** @var class-string $interfaceName */
        foreach (class_implements($service, false) ?: [] as $interfaceName) {
            $byInterface = $this->extensions[self::typeId($interfaceName)] ?? null;
            $byInterface and $allCallbacks[$interfaceName] = $byInterface;
        }

        $resultType = self::SERVICE_TYPE_NOT_CHANGED;
        /** @var class-string $type */
        foreach ($allCallbacks as $type => $extenders) {
            // When the previous group of callbacks resulted in a type change, we need to check
            // type before processing next group.
            if (($resultType === self::SERVICE_TYPE_CHANGED) && !is_a($service, $type)) {
                continue;
            }
            [$service, $resultType] = $this->extendByType($type, $service, $container, $extenders);
            if ($resultType === self::SERVICE_TYPE_NOT_OBJECT) {
                // Service is not an object anymore, let's return it.
                return $service;
            }
        }

        // If type changed since beginning, let's start over.
        // We check if class was already extended to avoid infinite recursion. E.g. instead of:
        // `-> extend(A): B -> extend(B): A -> *loop* ->`
        // we have:
        // `-> extend(A): B -> extend(B): A -> return A`.
        $newClassName = get_class($service);
        if (!in_array($newClassName, $extendedClasses, true)) {
            return $this->resolveByType($newClassName, $service, $container, $extendedClasses);
        }

        return $service;
    }

    /**
     * @param class-string $type
     * @param object $service
     * @param Container $container
     * @param list<ExtendingService> $extenders
     * @return array{mixed, int}
     */
    private function extendByType(
        string $type,
        object $service,
        Container $container,
        array $extenders
    ): array {

        foreach ($extenders as $extender) {
            $service = $extender($service, $container);
            if (!is_object($service)) {
                return [$service, self::SERVICE_TYPE_NOT_OBJECT];
            }
            if (!is_a($service, $type)) {
                return [$service, self::SERVICE_TYPE_CHANGED];
            }
        }

        return [$service, self::SERVICE_TYPE_NOT_CHANGED];
    }
}
