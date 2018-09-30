<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\FileMulti;

use Schoolbox\Admin\Settings\FieldType\File;

class ValueSerializer extends File\ValueSerializer
{

    /**
     * @inheritdoc
     */
    public function serializeValue($value): string
    {
        try {
            $newValue = '';
            if (\is_array($value)) {
                $hashes = [];
                foreach ($this->getCollectorFactory()->build($value)->getAll() as $image) {
                    $hashes[] = $image->getHash();
                }
                $newValue = \implode(',', $hashes);
            }
        } catch (\Schoolbox\Resource\Exception $e) {
            // File is empty or not found. Delete the record completely
            $newValue = '';
        } catch (\InvalidArgumentException $e) {
            $newValue = '';
        }

        return $newValue;
    }
}
