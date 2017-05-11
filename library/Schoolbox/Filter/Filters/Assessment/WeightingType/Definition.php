<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\WeightingType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\GenericCollection;


/**
 * Available options:
 * □ Weighted
 * □ Unweighted
 *
 */
class Definition extends AbstractFilterDefinition
{
    const SLUG = 'wt';

    const KEY_WEIGHTED    = 'weighted';
    const KEY_UNWEIGHTED  = 'unweighted';


    /** @var Collection|string[] */
    private $weightingTypes;


    /**
     * @param Filter\Model    $filter
     * @param Option\Mapper   $filterOptionMapper
     * @param Defaults\Mapper $filterDefaultMapper
     * @param Collection      $weightingTypes
     */
    public function __construct(
        Filter\Model $filter,
        Option\Mapper $filterOptionMapper,
        Defaults\Mapper $filterDefaultMapper,
        Collection $weightingTypes
    ) {
        parent::__construct($filter, $filterOptionMapper, $filterDefaultMapper);
        $this->weightingTypes = $weightingTypes->shallowClone();
    }

    /**
     * @inheritdoc
     */
    protected function getCustomKeys()
    {
        return $this->weightingTypes->getAll();
    }


    /**
     * @inheritdoc
     */
    public static function getPages()
    {
        return [
            Option\Model::PAGE_PENDING_ASSESSMENTS,
            Option\Model::PAGE_GRADES,
            Option\Model::PAGE_MARK_BOOK,
            Option\Model::PAGE_DUE_WORK_CALENDAR,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel($key)
    {
        if (self::KEY_UNWEIGHTED === $key) {
            return 'Unweighted';
        }
        if (self::KEY_WEIGHTED === $key) {
            return 'Weighted';
        }
        throw new \InvalidArgumentException("Unknown Weighting key: $key");
    }


    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(GenericCollection $pageOptions, $page)
    {
        // noop - WeightingTypes are purely hard-coded.
        return $pageOptions;
    }

    /**
     * @inheritdoc
     */
    protected function unsetInvalidOptions(GenericCollection $pageOptions)
    {
        // noop - WeightingTypes are purely hard-coded.
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
        // by default, all WeightingTypes are available
        return true;
    }
}
