<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\WorkType;

use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\WorkType;
use function array_map;
use function array_merge;


/**
 * Available options:
 * □ None
 * □ One per workType that exists in system
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    protected const NAME = 'Work Type';

    // If assessment does not have a WorkType at all
    public const KEY_NONE = 'none';

    /**
     * Indexed by workType.id
     *
     * @var GenericCollection|WorkType\Model[]
     */
    private $workTypes;


    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param GenericCollection|WorkType\Model[] $workTypes
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        GenericCollection $workTypes
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->workTypes = $workTypes->shallowClone()->indexByCallback(static function (WorkType\Model $workType): int {
            return $workType->getId();
        });
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return [
            self::KEY_NONE,
        ];
    }


    /**
     * @inheritdoc
     */
    public static function getPages(): array
    {
        return [
            Filter\Page\Model::PAGE_ASSESSMENT_CALENDAR,
            Filter\Page\Model::PAGE_PENDING_ASSESSMENTS,
            Filter\Page\Model::PAGE_GRADES,
            Filter\Page\Model::PAGE_CLASS_MARK_BOOK,
            Filter\Page\Model::PAGE_COURSE_MARK_BOOK,
            Filter\Page\Model::PAGE_RUBRIC_MARK_BOOK,
            Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS,
        ];
    }


    /**
     * @param GenericCollection|Filter\Page\Option\Model[] $options
     * @return GenericCollection
     */
    protected function sortFilterOptions(GenericCollection $options): GenericCollection
    {
        return $options;
    }


    /**
     * Return the set of possible keys for this filter's options
     *
     * @return string[]
     */
    public function getAbstractKeys(): array
    {
        // Ensure numeric ids are cast to string keys
        return array_merge($this->getCustomKeys(), array_map(static function (int $workTypeId) {
            return (string)$workTypeId;
        }, $this->workTypes->keys()));
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        if (self::KEY_NONE === $key) {
            return '(none)';
        }
        return $this->workTypes[$key]->getName();
    }


    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(Filter\Page\Model $pageFilter): GenericCollection
    {
        $options = $pageFilter->getOptions();

        // Ensure it contains all WorkTypes
        foreach ($this->workTypes as $workType) {

            $abstractKey = (string)$workType->getId();

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
        return '@filterDefinitions/Assessment/WorkType/templates';
    }
}
