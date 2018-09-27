<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

use Schoolbox\Config\Config;
use Schoolbox\Resource\Attachment\Zone;

class FileMulti extends File
{

    /**
     * @inheritdoc
     */
    protected function isMulti(): bool
    {
        return true;
    }


    /**
     * @inheritdoc
     */
    protected function getFileVar(Config $config): string
    {
        return parent::getFileVar($config) . "[]";
    }


    protected function attachFiles(Config $config, Zone $zone): void
    {
        $paths = \explode(",", $config->getValue());

        if (\count($paths)) {
            $zone->attachFiles($paths);
        }
    }
}
