<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\FileMulti;

use Schoolbox\Admin\Settings\FieldType\File;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Attachment\Zone;

class Renderer extends File\Renderer
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


    /**
     * @inheritdoc
     */
    protected function attachFiles(Config $config, Zone $zone): void
    {
        $paths = \explode(',', $config->getValue());

        if (\count($paths)) {
            $zone->attachFiles($paths);
        }
    }


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_FILE_MULTI;
    }
}
