<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings;

use Schoolbox\Admin\Settings\Renderer;
use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\MapperFactory;
use UnexpectedValueException;

class FieldRendererFactory
{

    /** @var MapperFactory */
    private $mapperFactory;


    /**
     * @param MapperFactory $mapperFactory
     */
    public function __construct(MapperFactory $mapperFactory)
    {
        $this->mapperFactory = $mapperFactory;
    }


    /**
     * @param Config $config
     * @return IConfigRenderer
     * @throws UnexpectedValueException
     */
    public function make(Config $config): IConfigRenderer
    {
        switch ($config->getFieldId()) {
            case ConfigMapper::FIELD_INPUT:
                return new Renderer\Input($config);
            case ConfigMapper::FIELD_TEXT_AREA:
                return new Renderer\TextArea($config);
            case ConfigMapper::FIELD_BOOLEAN:
            case ConfigMapper::FIELD_CHECKBOX:
                return new Renderer\Checkbox($config);
            case ConfigMapper::FIELD_SELECT:
                return new Renderer\Select($config);
            case ConfigMapper::FIELD_BYTES:
                return new Renderer\Bytes($config);
            case ConfigMapper::FIELD_SELECT_KEY:
                return new Renderer\SelectKey($config);
            case ConfigMapper::FIELD_HTML:
                return new Renderer\Html($config);
            case ConfigMapper::FIELD_RESOURCE:
                return new Renderer\Resource($config);
            case ConfigMapper::FIELD_SELECT_LOOKUP:
                return new Renderer\SelectLookup($config, $this->mapperFactory);
            case ConfigMapper::FIELD_COLOUR:
                return new Renderer\Colour($config);
            case ConfigMapper::FIELD_FILE:
                return new Renderer\File($config);
            case ConfigMapper::FIELD_FILE_MULTI:
                return new Renderer\FileMulti($config);
            case ConfigMapper::FIELD_PASSWORD:
                return new Renderer\Password($config);
            case ConfigMapper::FIELD_TIME_PICKER:
                return new Renderer\TimePicker($config);
        }
        throw new UnexpectedValueException("Unable to find a field renderer for config field '{$config->getFieldId()}'");
    }
}
