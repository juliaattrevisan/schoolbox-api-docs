<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Event\Campus;

use InvalidArgumentException;
use Schoolbox\Campus as CampusModel;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;

/**
 * Definition for the campus filter.
 */
class Definition extends Filter\AbstractDefinition
{
    // The name to expose in the UI
    protected const NAME = 'Campus';

    /**
     * @var Collection|CampusModel[]
     */
    private $campuses;


    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param Collection|CampusModel[]           $campuses
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        Collection $campuses
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->campuses = $campuses->shallowClone();
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
        $campus = $this->campuses[$key] ?? null;
        if ($campus) {
            return $campus->getName();
        }
        throw new InvalidArgumentException("Unknown Campus key: $key");
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
     * Alphabetically sort campus options.
     *
     * @param GenericCollection|Filter\Page\Option\Model[] $options
     * @return GenericCollection
     */
    protected function sortFilterOptions(GenericCollection $options): GenericCollection
    {
        return $options;
    }


    /**
     * This filter's abstract keys are the set of all campuses.
     *
     * @return string[]
     */
    public function getAbstractKeys(): array
    {
        return array_merge(
            $this->getCustomKeys(),
            array_map(
                static function (CampusModel $campus) {
                    return (string)$campus->getId();
                },
                $this->campuses->getAll()
            )
        );
    }


    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(Filter\Page\Model $pageFilter): GenericCollection
    {
        $options = $pageFilter->getOptions();
        foreach ($this->campuses as $campus) {
            $abstractKey = (string)$campus->getId();
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
        return '@filterDefinitions/Event/Campus/templates';
    }
}
