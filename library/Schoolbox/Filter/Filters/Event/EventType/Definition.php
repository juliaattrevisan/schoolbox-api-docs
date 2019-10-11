<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Event\EventType;

use InvalidArgumentException;
use Schoolbox\Calendar\EventType;
use Schoolbox\Collection;
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
     * @var Collection|EventType[]
     */
    private $eventTypes;


    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param Collection|EventType[]             $eventTypes
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        Collection $eventTypes
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
     * Alphabetically sort event type options.
     *
     * @param GenericCollection|Filter\Page\Option\Model[] $options
     * @return GenericCollection
     */
    protected function sortFilterOptions(GenericCollection $options): GenericCollection
    {
        // Keys represent event type IDs
        return $options->sortByCallback(
            function (Filter\Page\Option\Model $left, Filter\Page\Option\Model $right) {
                $leftKey  = $left->getKey();
                $rightKey = $right->getKey();
                if ($leftKey === $right) {
                    return 0;
                }

                // Find the keys in the list of labels
                $leftEventType  = $this->eventTypes->getBy('id', $leftKey);
                $rightEventType = $this->eventTypes->getBy('id', $rightKey);
                if (!($leftEventType instanceof EventType && $rightEventType instanceof EventType)) {
                    return 0;
                }
                return strnatcasecmp($leftEventType->getType(), $rightEventType->getType());
            },
            true
        );
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
                static function (EventType $eventType) {
                    return (string)$eventType->getId();
                },
                $this->eventTypes->getAll()
            )
        );
    }


    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(Filter\Page\Model $pageFilter): GenericCollection
    {
        $options = $pageFilter->getOptions();

        // Ensure it contains all WorkTypes
        foreach ($this->eventTypes as $eventType) {
            $abstractKey = (string)$eventType->getId();
            if (!$options->offsetExists($abstractKey)) {
                $option = new Filter\Page\Option\Model();
                $option
                    ->setFilterPage($pageFilter)
                    ->setKey($abstractKey)
                    ->setAvailable($this->getInitialAvailability($abstractKey, $pageFilter))
                    ->setDefaults(new GenericCollection(Filter\Page\Option\Defaults\Model::class));
                $options->setOffset($option->getKey(), $option);
            }
        }
        return $options;
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Event/EventType/templates';
    }
}
