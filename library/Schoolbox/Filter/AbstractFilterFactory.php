<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Filter;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Filter\Selection;


abstract class AbstractFilterFactory implements IFilterFactory
{

    /** @var Filter\Mapper */
    private $filterMapper;

    /** @var Option\Mapper */
    private $filterOptionMapper;

    /** @var Defaults\Mapper */
    private $filterDefaultMapper;

    /** @var Selection\Mapper */
    private $filterSelectionMapper;


    public function __construct(
        Filter\Mapper $filterMapper,
        Option\Mapper $optionMapper,
        Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper
    ) {
        $this->filterMapper          = $filterMapper;
        $this->filterOptionMapper    = $optionMapper;
        $this->filterDefaultMapper   = $defaultMapper;
        $this->filterSelectionMapper = $selectionMapper;
    }

    /**
     * @return Filter\Mapper
     */
    protected function getFilterMapper()
    {
        return $this->filterMapper;
    }

    /**
     * @return Option\Mapper
     */
    protected function getFilterOptionMapper()
    {
        return $this->filterOptionMapper;
    }


    /**
     * @return Defaults\Mapper
     */
    protected function getFilterDefaultMapper()
    {
        return $this->filterDefaultMapper;
    }


    /**
     * @return \Schoolbox\Filter\Selection\Mapper
     */
    protected function getFilterSelectionMapper()
    {
        return $this->filterSelectionMapper;
    }
}
