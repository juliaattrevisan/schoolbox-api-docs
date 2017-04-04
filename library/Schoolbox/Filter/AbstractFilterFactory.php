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
use Schoolbox\GenericCollection;
use Schoolbox\Mapper;
use Schoolbox\User;


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
     * Get user's existing selections for this page
     *
     * @param User $user
     * @param string  $page
     * @return GenericCollection|Selection\Model[]
     * @throws Mapper\Exception
     */
    protected function getSavedSelections(User $user, $page)
    {
        return $this->getFilterSelectionMapper()->findByColumnList([
            'user_id' => $user->getId(),
            'page'    => $page,
        ])->indexBy('key');
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
