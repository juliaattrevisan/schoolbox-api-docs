<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Config\Modules;

use Psr\Container\ContainerInterface;

class Socs implements ModuleConfigInterface
{
    public function configure(ContainerInterface $di): void
    {
        new \Schoolbox\Admin\Socs\Module($di)
            ->registerModule('/adminv2/socs');
    }
}
