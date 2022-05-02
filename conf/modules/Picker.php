<?php

/**
 * Configuration for the picker module.
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;

class Picker implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $datetime = new \Schoolbox\Picker\DateTime\Module($di);
        $datetime->registerModule('/picker/datetime');
//        $datetime->init('/picker/datetime');
    }
}
