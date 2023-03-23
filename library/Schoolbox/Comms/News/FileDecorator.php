<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News;

use Alaress\Json\Decorator\IDecorator;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Storage\Translator\ModelToArray;

/**
 * @package Schoolbox\Comms\News
 */
class FileDecorator implements IDecorator
{
    public function __construct(private ModelToArray $storageTranslator)
    {
    }

    /**
     * @param array             $json
     * @param GenericCollection $model
     * @return array
     */
    public function decorate(array $json, $model): array
    {
        if (!$model instanceof GenericCollection) {
            return $json;
        }

        $files = [];
        foreach ($model as $file) {
            if (!$file instanceof \Schoolbox\Comms\News\File\Model) {
                return $json;
            }
            $files[] = call_user_func($this->storageTranslator, $file->getStorage());
        }
        // return translated files rather than input array
        return $files;
    }
}
