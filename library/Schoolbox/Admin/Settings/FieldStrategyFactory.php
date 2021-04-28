<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

use Schoolbox\Admin\Settings\FieldType\FieldValueSerializerBase;
use Schoolbox\Admin\Settings\FieldType;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use UnexpectedValueException;

class FieldStrategyFactory
{

    /** @var MapperFactory */
    private $mapperFactory;

    /** @var CollectorFactory */
    private $collectorFactory;

    /**
     * Indexed by field_id
     *
     * @var IConfigRenderer[]
     */
    private $rendererCache = [];

    /**
     * Indexed by field_id
     *
     * @var IFieldValueSerializer[]
     */
    private $valueSerializerCache = [];


    /**
     * @param MapperFactory    $mapperFactory
     * @param CollectorFactory $collectorFactory
     */
    public function __construct(MapperFactory $mapperFactory, CollectorFactory $collectorFactory)
    {
        $this->mapperFactory    = $mapperFactory;
        $this->collectorFactory = $collectorFactory;
    }


    /**
     * @param int $fieldId
     * @return IConfigRenderer
     */
    public function makeRenderer(int $fieldId): IConfigRenderer
    {
        if (!\array_key_exists($fieldId, $this->rendererCache)) {
            $this->rendererCache[$fieldId] = $this->makeNewRenderer($fieldId);
        }

        return $this->rendererCache[$fieldId];
    }


    /**
     * @param int $fieldId
     * @return IFieldValueSerializer
     */
    public function makeValueSerializer(int $fieldId): IFieldValueSerializer
    {
        if (!\array_key_exists($fieldId, $this->valueSerializerCache)) {
            $this->valueSerializerCache[$fieldId] = $this->makeNewValueSerializer($fieldId);
        }

        return $this->valueSerializerCache[$fieldId];
    }


    /**
     * @param int $fieldId
     * @return IConfigRenderer
     */
    private function makeNewRenderer(int $fieldId): IConfigRenderer
    {
        switch ($fieldId) {
            case ConfigMapper::FIELD_INPUT:
                return new FieldType\Input\Renderer();
            case ConfigMapper::FIELD_TEXT_AREA:
                return new FieldType\TextArea\Renderer();
            case ConfigMapper::FIELD_BOOLEAN:
                return new FieldType\Boolean\Renderer();
            case ConfigMapper::FIELD_SELECT:
                return new FieldType\Select\Renderer();
            case ConfigMapper::FIELD_CHECKBOX:
                return new FieldType\Checkbox\Renderer();
            case ConfigMapper::FIELD_BYTES:
                return new FieldType\Bytes\Renderer();
            case ConfigMapper::FIELD_SELECT_KEY:
                return new FieldType\SelectKey\Renderer();
            case ConfigMapper::FIELD_HTML:
                return new FieldType\Html\Renderer();
            case ConfigMapper::FIELD_RESOURCE:
                return new FieldType\Resource\Renderer();
            case ConfigMapper::FIELD_SELECT_LOOKUP:
                return new FieldType\SelectLookup\Renderer($this->mapperFactory);
            case ConfigMapper::FIELD_COLOUR:
                return new FieldType\Colour\Renderer();
            case ConfigMapper::FIELD_FILE:
                return new FieldType\File\Renderer();
            case ConfigMapper::FIELD_FILE_MULTI:
                return new FieldType\FileMulti\Renderer();
            case ConfigMapper::FIELD_PASSWORD:
                return new FieldType\Password\Renderer();
            case ConfigMapper::FIELD_TIME_PICKER:
                return new FieldType\TimePicker\Renderer();
            case ConfigMapper::FIELD_NUMBER:
                return new FieldType\Number\Renderer();
            case ConfigMapper::FIELD_SELECT_MULTI:
                return new FieldType\SelectMulti\Renderer();
        }
        throw new UnexpectedValueException("Unable to find a field renderer for config field '{$fieldId}'");
    }


    /**
     * @param int $fieldId
     * @return IFieldValueSerializer
     */
    private function makeNewValueSerializer(int $fieldId): IFieldValueSerializer
    {
        switch ($fieldId) {
            case ConfigMapper::FIELD_BYTES:
                return new FieldType\Bytes\ValueSerializer();
            case ConfigMapper::FIELD_FILE:
                return new FieldType\File\ValueSerializer($this->collectorFactory);
            case ConfigMapper::FIELD_FILE_MULTI:
                return new FieldType\FileMulti\ValueSerializer($this->collectorFactory);
            case ConfigMapper::FIELD_INPUT:
            case ConfigMapper::FIELD_TEXT_AREA:
            case ConfigMapper::FIELD_BOOLEAN:
            case ConfigMapper::FIELD_SELECT:
            case ConfigMapper::FIELD_CHECKBOX:
            case ConfigMapper::FIELD_SELECT_KEY:
            case ConfigMapper::FIELD_HTML:
            case ConfigMapper::FIELD_RESOURCE:
            case ConfigMapper::FIELD_SELECT_LOOKUP:
            case ConfigMapper::FIELD_COLOUR:
            case ConfigMapper::FIELD_PASSWORD:
            case ConfigMapper::FIELD_TIME_PICKER:
            case ConfigMapper::FIELD_NUMBER:
                return new FieldValueSerializerBase();
            case ConfigMapper::FIELD_SELECT_MULTI:
                return new FieldType\SelectMulti\ValueSerializer();
            // Note that no default implicitly requires explicit registration to avoid logic errors.
        }
        throw new UnexpectedValueException("Unable to find a field value serializer for config field '{$fieldId}'");
    }
}
