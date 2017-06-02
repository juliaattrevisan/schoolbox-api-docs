<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\TemporalState;

use Schoolbox\Collection;
use Schoolbox\Filter;


/**
 * Filters assessments by their publish, open, due and close dates.
 *
 * Available options:
 * □ Unpublished (           now < publish)
 * □ Upcoming    (publish <= now < open)
 * □ Open        (open    <= now < late)
 * □ Overdue     (late    <= now < close)
 * □ Closed      (close   <= now )
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    const NAME = 'Activity State';

    const KEY_UNPUBLISHED = 'unpublished';
    const KEY_UPCOMING = 'upcoming';
    const KEY_OPEN = 'open';
    const KEY_OVERDUE = 'overdue';
    const KEY_CLOSED = 'closed';


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
    protected function getCustomKeys()
    {
        return $this->temporalStates->getAll();
    }


    /**
     * @inheritdoc
     */
    public static function getPages()
    {
        return [
            Filter\Page\Model::PAGE_PENDING_ASSESSMENTS,
            Filter\Page\Model::PAGE_MARK_BOOK,
//            Filter\Page\Model::PAGE_GRADES,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel($key)
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
        throw new \InvalidArgumentException("Unknown TemporalState key: $key");
    }


    /**
     * @inheritdoc
     */
    protected function unsetInvalidOptions(Filter\Page\Model $pageFilter)
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
    protected function getInitialSelectedState($key, $page, $roleType)
    {
        // No options checked
        return false;
    }


    /**
     * @inheritdoc
     */
    public function getInitialAvailability($customAbstractKey, Filter\Page\Model $pageFilter)
    {
        // by default, all TemporalStates are available
        return true;
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir()
    {
        return '@filterDefinitions/Assessment/TemporalState/templates';
    }
}
