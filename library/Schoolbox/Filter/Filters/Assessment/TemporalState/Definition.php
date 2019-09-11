<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\TemporalState;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;


/**
 * Filters assessments by their publish, open, due and close dates.
 *
 * Available options:
 * □ Unpublished (             now < a.publish)
 * □ Upcoming    (a.publish <= now < a.open)
 * □ Open        (a.open    <= now < a.late)
 * □ Overdue     (a.late    <= now < a.close)
 * □ Closed      (a.close   <= now )
 *
 * □ Plan Unpublished (             now < p.publish)
 * □ Plan Upcoming    (p.publish <= now < p.open)
 * □ Plan Open        (p.open    <= now < p.late)
 * □ Plan Overdue     (p.late    <= now < p.close)
 * □ Plan Closed      (p.close   <= now )
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    protected const NAME = 'Activity State';

    // Filter on the assessment dates
    public const KEY_UNPUBLISHED = 'unpublished';
    public const KEY_UPCOMING = 'upcoming';
    public const KEY_OPEN = 'open';
    public const KEY_OVERDUE = 'overdue';
    public const KEY_CLOSED = 'closed';

    // Filter on plan dates (ie CAP)
    public const KEY_CUSTOM_UNPUBLISHED = 'cap_unpublished';
    public const KEY_CUSTOM_UPCOMING = 'cap_upcoming';
    public const KEY_CUSTOM_OPEN = 'cap_open';
    public const KEY_CUSTOM_OVERDUE = 'cap_overdue';
    public const KEY_CUSTOM_CLOSED = 'cap_closed';


    /** @var Collection|string[] */
    private $temporalStates;


    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param Collection                         $temporalStates
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
        Collection $temporalStates
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->temporalStates = $temporalStates->shallowClone();
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys(): array
    {
        return $this->temporalStates->getAll();
    }


    /**
     * @inheritdoc
     */
    public static function getPages(): array
    {
        return [
            Filter\Page\Model::PAGE_PENDING_ASSESSMENTS,
            Filter\Page\Model::PAGE_CLASS_MARK_BOOK,
            Filter\Page\Model::PAGE_COURSE_MARK_BOOK,
            Filter\Page\Model::PAGE_RUBRIC_MARK_BOOK,
//            Filter\Page\Model::PAGE_GRADES,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel(string $key): string
    {
        if (self::KEY_UNPUBLISHED === $key) {
            return 'Unpublished';
        }
        if (self::KEY_UPCOMING === $key) {
            return 'Upcoming';
        }
        if (self::KEY_OPEN === $key) {
            return 'Open';
        }
        if (self::KEY_OVERDUE === $key) {
            return 'Overdue';
        }
        if (self::KEY_CLOSED === $key) {
            return 'Closed';
        }
        if (self::KEY_CUSTOM_UNPUBLISHED === $key) {
            return 'Custom Plan Publish Date';
        }
        throw new InvalidArgumentException("Unknown TemporalState key: $key");
    }


    /**
     * @inheritdoc
     */
    protected function unsetInvalidOptions(Filter\Page\Model $pageFilter): GenericCollection
    {
        $cleanedPageOptions = parent::unsetInvalidOptions($pageFilter);

        foreach ($cleanedPageOptions as $idx => $option) {
            // Do not permit 'upcoming' on the 'grades' page
            if (self::KEY_UNPUBLISHED === $option->getKey() && Filter\Page\Model::PAGE_GRADES === $pageFilter->getPage()) {
                $cleanedPageOptions->offsetUnset($idx);
            }
        }
        return $cleanedPageOptions;
    }

    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir(): string
    {
        return '@filterDefinitions/Assessment/TemporalState/templates';
    }
}
