<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Event\EventType;

use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Selection;

/**
 * A factory for building event type filter objects.
 */
class Factory extends Filter\AbstractFactory
{
    /**
     * @var EventTypeMapper
     */
    private $eventTypeMapper;


    /**
     * Constructor
     *
     * @param Page\Option\Mapper          $optionMapper
     * @param Page\Option\Defaults\Mapper $defaultMapper
     * @param Selection\Mapper            $selectionMapper
     * @param EventTypeMapper             $eventTypeMapper
     */
    public function __construct(
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        EventTypeMapper $eventTypeMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->eventTypeMapper = $eventTypeMapper;
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
            $this->eventTypeMapper->findAll()->indexBy('id')
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter($filter, $this->eventTypeMapper->findAllIds());
    }
}
