<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\SelectLookup;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\MapperFactory;
use Schoolbox\Admin\Settings\FieldType\Select;
use InvalidArgumentException;

class Renderer extends Select\Renderer
{

    // Serialised Options keys
    public const OPTION_KEY_MAPPER           = 'mapper';
    public const OPTION_KEY_GET_KEY_METHOD   = 'getKeyMethod';
    public const OPTION_KEY_GET_VALUE_METHOD = 'getValueMethod';
    public const OPTION_KEY_FIND_METHOD      = 'findMethod';

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
     * Converts a string representation of options into an array form
     *
     * @param string $optionsString
     * @return array
     * @throws InvalidArgumentException If mapper class not found
     */
    protected function buildOptions(string $optionsString): array
    {
        // options: expected to contain mapper class
        $lookupOptions  = \unserialize($optionsString ?? '', [false]);
        $mapper         = $this->mapperFactory->get($lookupOptions[self::OPTION_KEY_MAPPER]);
        $getKeyMethod   = $lookupOptions[self::OPTION_KEY_GET_KEY_METHOD] ?? 'getId';
        $getValueMethod = $lookupOptions[self::OPTION_KEY_GET_VALUE_METHOD] ?? 'getName';
        $findMethod     = $lookupOptions[self::OPTION_KEY_FIND_METHOD] ?? 'findAll';

        $options = [];
        foreach ($mapper->$findMethod() as $opt) {
            $options[$opt->$getKeyMethod()] = $opt->$getValueMethod();
        }
        return $options;
    }


    /**
     * Returns whether the proposed value is one of the available values
     *
     * @param string|null $value
     * @param array       $options
     * @return bool
     */
    protected function isValueValid(?string $value, array $options): bool
    {
        foreach ($options as $key => $option) {
            if ((string)$key === $value) {
                return true;
            }
        }
        return false;
    }


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_SELECT_LOOKUP;
    }
}
