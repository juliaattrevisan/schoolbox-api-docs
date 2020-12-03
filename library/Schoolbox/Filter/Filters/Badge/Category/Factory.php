<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);


namespace Schoolbox\Filter\Filters\Badge\Category;


use Schoolbox\Filter\AbstractFactory;
use Schoolbox\Filter\IDefinition;
use Schoolbox\Filter\IKeyConverter;
use Schoolbox\Filter\Model;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Selection;
use Schoolbox\Learning\Badge\Category\Mapper;


/**
 * Factory for building badge category filters.
 */
class Factory extends AbstractFactory
{
    /**
     * Category mapper, for finding badge categroeis.
     */
    private Mapper $categoryMapper;


    /**
     * Constructor
     *
     * @param Page\Option\Mapper $optionMapper
     * @param Page\Option\Defaults\Mapper $defaultMapper
     * @param Selection\Mapper $selectionMapper
     * @param Mapper $termMapper
     */
    public function __construct(
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        Mapper $termMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->categoryMapper = $termMapper;
    }


    /**
     * @inheritdoc
     */
    public function buildDefinition(Model $filter) : IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper(),
            $this->categoryMapper->findAll()
        );
    }


    /**
     * @inheritdoc
     */
    public function buildKeyConverter(Model $filter) : IKeyConverter
    {
        return new KeyConverter($filter, $this->categoryMapper->findAllIds());
    }
}
