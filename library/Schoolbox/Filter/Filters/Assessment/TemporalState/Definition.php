<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\TemporalState;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\GenericCollection;


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
class Definition extends AbstractDefinition
{
    const KEY_UNPUBLISHED = 'unpublished';
    const KEY_UPCOMING    = 'upcoming';
    const KEY_OPEN        = 'open';
    const KEY_OVERDUE     = 'overdue';
    const KEY_CLOSED      = 'closed';


    /** @var Collection|string[] */
    private $temporalStates;


    /**
     * @param Filter\Model    $filter
     * @param Option\Mapper   $filterOptionMapper
     * @param Defaults\Mapper $filterDefaultMapper
     * @param Collection      $temporalStates
     */
    public function __construct(
        Filter\Model $filter,
        Option\Mapper $filterOptionMapper,
        Defaults\Mapper $filterDefaultMapper,
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
            Option\Model::PAGE_PENDING_ASSESSMENTS,
            Option\Model::PAGE_MARK_BOOK,
//            Option\Model::PAGE_GRADES,
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
    protected function insertDynamicOptions(GenericCollection $pageOptions, $page)
    {
        // noop - TemporalStates are purely hard-coded.
        return $pageOptions;
    }

    /**
     * @inheritdoc
     */
    protected function unsetInvalidOptions(GenericCollection $pageOptions)
    {
        foreach ($pageOptions as $idx => $option) {
            // Do not permit 'upcoming' on the 'grades' page
            if (self::KEY_UNPUBLISHED === $option->getKey() && Option\Model::PAGE_GRADES === $option->getPage()) {
                $pageOptions->offsetUnset($idx);
            }
        }
        return $pageOptions;
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
    public function getInitialAvailability($customAbstractKey, $page)
    {
        // by default, all TemporalStates are available
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function getTemplatesDir()
    {
        return '@filterDefinitions/Assessment/TemporalState/templates';
    }
}
