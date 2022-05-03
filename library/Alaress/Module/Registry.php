<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Module;

use LogicException;
use Psr\Container\ContainerInterface;
use Schoolbox\Module\RestfulModule;

/**
 * Registry of all modules which may be built by Schoolbox.
 */
class Registry
{
    /**
     * A list of module registrations.
     *
     * @var array<string, array{class: class-string, basePath: string, contextBasename: ?string}>
     */
    protected static array $registrations = [];

    /**
     * Register a single module.
     *
     * @param Module             $module   The module to register
     * @param string|null        $basePath The base path for the module's routes
     * @param RestfulModule|null $context  If set: `$module`'s routes will be
     *                                     installed as sub-routes of `$context`
     * @throws LogicException if a module with basename `$basename` has already
     *                        been registered
     * @return void
     */
    public static function register(Module $module, ?string $basePath, ?RestfulModule $context = null)
    {
        $moduleClass = get_class($module);
        $basename    = $module->getBasename();
        if (isset(self::$registrations[$basename])) {
            $otherModuleClass = self::$registrations[$basename]['class'] ?? null;
            if ($moduleClass !== ($otherModuleClass)) {
                throw new LogicException(
                    sprintf(
                        'Module "%s" is already registered as instanceof "%s" - won\'t redefine as instanceof "%s"',
                        $basename,
                        $otherModuleClass,
                        $moduleClass,
                    )
                );
            } else {
                user_error(
                    sprintf('Module "%s" is already registered - not redefining', $basename),
                    E_USER_WARNING
                );
            }
        }
        self::$registrations[$basename] = [
            'class'           => $moduleClass,
            'basePath'        => $basePath,
            'contextBasename' => $context ? $context->getBasename() : null,
        ];
    }

    /**
     * Retrieve a single module registration.
     *
     * @param string $basename The name of the registered module.
     * @return array{class: class-string, basePath: string, contextBasename: ?string}
     * @throws LogicException if no module with basename `$basename` has been
     *                        registered using `register()`
     */
    public static function retrieveRegistration(string $basename): array
    {
        if (!isset(self::$registrations[$basename])) {
            throw new LogicException(sprintf('Module "%s" does not exist', $basename));
        }
        return self::$registrations[$basename];
    }

    /**
     * Build a module out of a registration.
     *
     * @param ContainerInterface $di       The DI container all modules require.
     * @param string             $basename The name of the registered module.
     * @return Module
     * @throws LogicException if no module with basename `$basename` has been
     *                        registered using `register()`
     */
    public static function buildModuleFromRegistration(ContainerInterface $di, string $basename): Module
    {
        $registration = self::retrieveRegistration($basename);
        $class        = $registration['class'];

        // Modules may have a context module:
        // if so, build its context recursively.
        if (isset($registration['contextBasename'])) {
            $module = new $class($di, self::buildModuleFromRegistration($di, $registration['contextBasename']));
        } else {
            $module = new $class($di, null);
        }
        return $module;
    }

    /**
     * @return array<string, array{class: class-string, basePath: string, contextBasename: ?string}>
     */
    public static function getRegistrations(): array
    {
        return self::$registrations;
    }

    /**
     * Install cached module registrations.
     *
     * @param array<string, array{class: class-string, basePath: string, contextBasename: ?string}> $registrations
     * @return void
     */
    public static function setRegistrations(array $registrations): void
    {
        self::$registrations = $registrations;
    }
}
