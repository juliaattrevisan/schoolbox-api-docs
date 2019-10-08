<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Event\EventType;

use InvalidArgumentException;
use Schoolbox\Calendar\EventType;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;


/**
 * Definition for the event type filter.
 */
class Definition extends Filter\AbstractDefinition
{
    // The name to expose in the UI
    protected const NAME = 'Event Type';

    /**
     * @var GenericCollection|EventType[]
     */
    private $eventTypes;


    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param GenericCollection                  $eventTypes
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        GenericCollection $eventTypes
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->eventTypes = $eventTypes->shallowClone();
    }


    /**
     * @inheritdoc
     */
    public static function getPages(): array
    {
        return [
            Filter\Page\Model::PAGE_ASSESSMENT_CALENDAR,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        $eventType = $this->eventTypes[$key] ?? null;
        if ($eventType) {
            return $eventType->getType();
        }
        throw new InvalidArgumentException("Unknown EventType key: $key");
    }


    /**
     * This filter has no custom keys.
     *
     * @return array
     */
    protected function getCustomKeys(): array
    {
        return [];
    }


    /**
     * This filter's abstract keys are the set of all event types.
     *
     * @return string[]
     */
    public function getAbstractKeys(): array
    {
        return array_merge(
            $this->getCustomKeys(),
            array_map(
                static function (int $workTypeId) {
                    return (string)$workTypeId;
                },
                $this->eventTypes->keys()
            )
        );
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Event/EventType/templates';
    }
}
