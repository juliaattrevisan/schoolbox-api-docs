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
    public function serializeValue($value, $config): string
    {
        $options = $this->buildOptions($config->getOptions());
        $newValue = '';

        try {
            $result   = $this->collectorFactory->build([$value])->getFirst();

            $mimeFilter = $options['mimeFilter'];

            /* Use the itemised equivalent of the wildcard, because stupid svg
             * Otherwise use whatever was sent to us
             */
            if (\Schoolbox\Resource\Attachment\Zone::IMAGE_ALL_WILDCARD === $options['mimeFilter']) {
                $mimeFilter = \Schoolbox\Resource\Attachment\Zone::IMAGE_ALL;
            }

            if ($result->getMimeType()->matches($mimeFilter)) {
                $newValue = $result->getHash();
            }
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


    /**
     * Converts a string representation of options into an array form
     * The options for the file FieldType are a JSON object with the following keys:
     * - mimeFilter: string - The mime filter for the file input (e.g. "image/* for images)
     *
     * @param string $optionsString
     * @return array{mimeFilter:string}
     */
    public function buildOptions(string $optionsString): array
    {
        return json_decode($optionsString, true);
    }
}
