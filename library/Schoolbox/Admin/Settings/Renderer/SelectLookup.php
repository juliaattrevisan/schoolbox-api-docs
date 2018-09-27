<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Settings\Renderer;

use Schoolbox\Config\Config;
use Schoolbox\MapperFactory;

class SelectLookup extends Select
{

    /** @var MapperFactory */
    private $mapperFactory;


    /**
     * @param Config        $config
     * @param MapperFactory $mapperFactory
     */
    public function __construct(Config $config, MapperFactory $mapperFactory)
    {
        parent::__construct($config);
        $this->mapperFactory = $mapperFactory;
    }


    /**
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getData(): array
    {
        // options: expected to contain mapper class
        $lookupOptions  = \unserialize($this->getConfig()->getOptions() ?? '', [false]);
        $mapper         = $this->mapperFactory->get($lookupOptions['mapper']);
        $getKeyMethod   = $lookupOptions['getKeyMethod'] ?? 'getId';
        $getValueMethod = $lookupOptions['getValueMethod'] ?? 'getName';
        $findMethod     = $lookupOptions['findMethod'] ?? 'findAll';

        $options = [];
        foreach ($mapper->$findMethod() as $opt) {
            $options[$opt->$getKeyMethod()] = $opt->$getValueMethod();
        }

        return \array_merge(parent::getData(), [
            'options' => $options,
        ]);
    }
}
