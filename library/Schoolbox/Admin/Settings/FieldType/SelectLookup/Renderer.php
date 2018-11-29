<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\SelectLookup;

use Schoolbox\Config\Config;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\MapperFactory;
use Schoolbox\Admin\Settings\FieldType\Select;

class Renderer extends Select\Renderer
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
     * Converts a string representation of options into an array form
     *
     * @param string $optionsString
     * @return array
     * @throws \InvalidArgumentException If mapper class not found
     */
    protected function buildOptions(string $optionsString): array
    {
        // options: expected to contain mapper class
        $lookupOptions  = \unserialize($optionsString ?? '', [false]);
        $mapper         = $this->mapperFactory->get($lookupOptions['mapper']);
        $getKeyMethod   = $lookupOptions['getKeyMethod'] ?? 'getId';
        $getValueMethod = $lookupOptions['getValueMethod'] ?? 'getName';
        $findMethod     = $lookupOptions['findMethod'] ?? 'findAll';

        $options = [];
        foreach ($mapper->$findMethod() as $opt) {
            $options[$opt->$getKeyMethod()] = $opt->$getValueMethod();
        }
        return $options;
    }


    /**
     * @inheritdoc
     */
    protected function getFieldId(): int
    {
        return ConfigMapper::FIELD_SELECT_LOOKUP;
    }
}
