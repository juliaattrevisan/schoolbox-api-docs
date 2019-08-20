<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\WorkType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Learning\Assessment\WorkType;

/**
 * A factory for building 'WorkType' filter objects
 */
class Factory extends Filter\AbstractFactory
{

    /**
     * @var WorkType\Mapper
     */
    private $workTypeMapper;


    /**
     * @param Filter\Page\Option\Mapper          $optionMapper
     * @param Filter\Page\Option\Defaults\Mapper $defaultMapper
     * @param Filter\Selection\Mapper            $selectionMapper
     * @param WorkType\Mapper                    $workTypeMapper
     */
    public function __construct(
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        WorkType\Mapper $workTypeMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->workTypeMapper = $workTypeMapper;
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
            $this->workTypeMapper->findAll()
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter($filter, new Collection($this->workTypeMapper->findAllIds()));
    }
}
