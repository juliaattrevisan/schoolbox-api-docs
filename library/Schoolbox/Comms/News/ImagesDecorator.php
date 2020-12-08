<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News;

use Alaress\Json\Decorator\IDecorator;

/**
 * Class ImagesDecorator
 *
 * Collate a set of attached images
 *
 * @package Schoolbox\Comms\News
 */
class ImagesDecorator implements IDecorator
{

    /**
     * @param array  $json
     * @param object $model
     * @return array
     */
    public function decorate(array $json, $model): array
    {
        if (!$model instanceof Model) {
            // only attempt to decorate if the model is the right type
            return $json;
        }

        $json['images'] = [];

        /** @var \Schoolbox\Comms\News\File\Model $file */
        foreach ($model->getImages() as $file) {
            $json['images'][] = [
                'filename' => [
                    'original' => $file->getBasename(),
                    'encoded' => urlencode($file->getBasename())
                ],
                'hash' => $file->getHash(),
                'mime' => $file->getMimetype()->getMime(),
                'size' => \Alaress_Byte_Format::bytesToString($file->getSize())
            ];
        }
        
        return $json;
    }
}
