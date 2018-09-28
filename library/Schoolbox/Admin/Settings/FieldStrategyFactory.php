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
     * Indexed by field_id
     *
     * @var IConfigRenderer[]
     */
    private $cache = [];


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
     */
    public function make(Config $config): IConfigRenderer
    {
        if (!\array_key_exists($config->getFieldId(), $this->cache)) {
            $this->cache[$config->getFieldId()] = $this->makeNew($config);
        }

        return $this->cache[$config->getFieldId()];
    }


    /**
     * @param Config $config
     * @return IConfigRenderer
     * @throws UnexpectedValueException
     */
    private function makeNew(Config $config): IConfigRenderer
    {
        switch ($config->getFieldId()) {
            case ConfigMapper::FIELD_INPUT:
                return new Renderer\Input();
            case ConfigMapper::FIELD_TEXT_AREA:
                return new Renderer\TextArea();
            case ConfigMapper::FIELD_BOOLEAN:
                return new Renderer\Boolean();
            case ConfigMapper::FIELD_SELECT:
                return new Renderer\Select();
            case ConfigMapper::FIELD_CHECKBOX:
                return new Renderer\Checkbox();
            case ConfigMapper::FIELD_BYTES:
                return new Renderer\Bytes();
            case ConfigMapper::FIELD_SELECT_KEY:
                return new Renderer\SelectKey();
            case ConfigMapper::FIELD_HTML:
                return new Renderer\Html();
            case ConfigMapper::FIELD_RESOURCE:
                return new Renderer\Resource();
            case ConfigMapper::FIELD_SELECT_LOOKUP:
                return new Renderer\SelectLookup($this->mapperFactory);
            case ConfigMapper::FIELD_COLOUR:
                return new Renderer\Colour();
            case ConfigMapper::FIELD_FILE:
                return new Renderer\File();
            case ConfigMapper::FIELD_FILE_MULTI:
                return new Renderer\FileMulti();
            case ConfigMapper::FIELD_PASSWORD:
                return new Renderer\Password();
            case ConfigMapper::FIELD_TIME_PICKER:
                return new Renderer\TimePicker();
        }
        throw new UnexpectedValueException("Unable to find a field renderer for config field '{$config->getFieldId()}'");
    }
}
