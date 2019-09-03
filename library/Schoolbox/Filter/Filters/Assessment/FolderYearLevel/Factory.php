<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\FolderYearLevel;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Selection;
use Schoolbox\YearLevel;

/**
 * A factory for building 'FolderYearLevel' filter objects
 */
class Factory extends Filter\AbstractFactory
{
    /** @var YearLevel\Mapper */
    private $yearLevelMapper;

    /**
     * @param Page\Option\Mapper          $optionMapper
     * @param Page\Option\Defaults\Mapper $defaultMapper
     * @param Selection\Mapper            $selectionMapper
     * @param YearLevel\Mapper            $yearLevelMapper
     */
    public function __construct(
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        YearLevel\Mapper $yearLevelMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->yearLevelMapper = $yearLevelMapper;
    }

    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Definition|Filter\IDefinition
     */
    public function buildDefinition(Filter\Model $filter): Filter\IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper(),
            $this->yearLevelMapper->findAll()
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter(
            $filter,
            new Collection($this->yearLevelMapper->findAllIds())
        );
    }
}
