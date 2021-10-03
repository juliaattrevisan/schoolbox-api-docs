<?php

declare(strict_types=1);

namespace Schoolbox\Application\WebRequest;

use Alaress\Exception\UnreachableException;
use DomainException;
use UnexpectedValueException;

/**
 * Wrapper to handle registration of shutdown functions and allow ordering of execution
 */
class ShutdownHandler
{
    /**
     * Highest possible priority for shutdown functions.
     */
    public const PRIORITY_HIGHEST = 0;

    /**
     * Lowest possible priority for shutdown functions.
     */
    public const PRIORITY_LOWEST = 100;

    /**
     * Functions to call on shutdown
     * Indexed by priority, functionId
     *
     * @var ShutdownFunction[][]
     */
    private array $functions = [];


    public function __construct()
    {
        register_shutdown_function($this);
    }


    /**
     * This class is expected to be registered as a shutdown function
     *
     * @see register_shutdown_function()
     */
    public function __invoke(): void
    {
        $priorities = array_keys($this->functions);
        sort($priorities);

        foreach ($priorities as $priority) {
            foreach ($this->functions[$priority] as $shutdownFunction) {
                $shutdownFunction();
            }
        }
    }


    /**
     * Register a shutdown function.
     *
     * You can register the same callback and multiple priorities, but not multiple times for the same priority.
     *
     * @param callable $callback Function to invoke
     * @param int      $priority When to invoke the function relative to other callbacks. Lower value => higher priority
     * @param mixed    ...$args  Additional args to pass when invoking the callback
     * @throws DomainException if `$priority` is outside [self::PRIORITY_HIGHEST, self::PRIORITY_LOWEST]
     */
    public function registerFunction(callable $callback, int $priority = self::PRIORITY_LOWEST, ...$args): bool
    {
        $functionId = $this->buildUniqueFunctionId($callback);
        if ($priority > self::PRIORITY_LOWEST || $priority < self::PRIORITY_HIGHEST) {
            throw new DomainException(
                sprintf(
                    'Priority of registered function must be within [%d, %d]: received %d',
                    self::PRIORITY_HIGHEST,
                    self::PRIORITY_LOWEST,
                    $priority
                )
            );
        }

        if (array_key_exists($functionId, $this->functions[$priority] ?? [])) {
            return false;
        }

        $this->functions[$priority][$functionId] = new ShutdownFunction($callback, $args);
        return true;
    }


    /**
     * Unregister callback at the specified priority
     *
     * @param callable $callback
     * @param int      $priority
     */
    public function unregisterFunction(callable $callback, int $priority = PHP_INT_MAX): void
    {
        unset($this->functions[$priority][$this->buildUniqueFunctionId($callback)]);
    }


    /**
     * Build a unique identifier for a callable
     *
     * @param string|callable $function
     * @return string
     */
    private function buildUniqueFunctionId($function): string
    {
        if (is_string($function)) {
            return $function;
        }

        if (is_object($function)) {
            // Closures are currently implemented as objects
            $function = [$function, ''];
        } else {
            $function = (array)$function;
        }

        if (is_object($function[0])) {
            // Object Class Calling
            return spl_object_hash($function[0]) . $function[1];
        }
        if (is_string($function[0])) {
            // Static Calling
            return "$function[0]::$function[1]";
        }
        try {
            throw new UnexpectedValueException("Unable to derive unique id for shutdown function");
        } catch (UnexpectedValueException $e) {
            throw new UnreachableException($e);
        }
    }
}
