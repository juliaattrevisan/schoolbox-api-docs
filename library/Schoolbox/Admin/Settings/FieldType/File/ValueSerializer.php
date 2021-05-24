<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\File;

use Schoolbox\Admin\Settings\IFieldValueSerializer;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;

class ValueSerializer implements IFieldValueSerializer
{

    /** @var CollectorFactory */
    private $collectorFactory;


    /**
     * @param CollectorFactory $collectorFactory
     */
    public function __construct(CollectorFactory $collectorFactory)
    {
        $this->collectorFactory = $collectorFactory;
    }


    /**
     * @inheritdoc
     */
    public function serializeValue($value): string
    {
        try {
            $result   = $this->collectorFactory->build([$value])->getFirst();
            $newValue = $result->getHash();
        } catch (\Schoolbox\Resource\Exception $e) {
            // File is empty or not found. Delete the record completely
            $newValue = '';
        } catch (\InvalidArgumentException $e) {
            $newValue = '';
        }

        return $newValue;
    }


    /**
     * @return CollectorFactory
     */
    protected function getCollectorFactory(): CollectorFactory
    {
        return $this->collectorFactory;
    }
}
