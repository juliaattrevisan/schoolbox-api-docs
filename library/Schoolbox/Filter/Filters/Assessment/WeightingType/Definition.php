<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\WeightingType;

use Schoolbox\Collection;
use Schoolbox\Filter;


/**
 * Available options:
 * □ Weighted
 * □ Unweighted
 *
 */
class Definition extends Filter\AbstractDefinition
{

    // The name to expose in the UI
    const NAME = 'Weighting';

    const KEY_WEIGHTED = 'weighted';
    const KEY_UNWEIGHTED = 'unweighted';


    /** @var Collection|string[] */
    private $weightingTypes;


    /**
     * @param Filter\Model                       $filter
     * @param Filter\Page\Option\Mapper          $filterOptionMapper
     * @param Filter\Page\Option\Defaults\Mapper $filterDefaultMapper
     * @param Collection                         $weightingTypes
     */
    public function __construct(
        Filter\Model $filter,
        Filter\Page\Option\Mapper $filterOptionMapper,
        Filter\Page\Option\Defaults\Mapper $filterDefaultMapper,
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
            Filter\Page\Model::PAGE_PENDING_ASSESSMENTS,
            Filter\Page\Model::PAGE_GRADES,
            Filter\Page\Model::PAGE_MARK_BOOK,
//            Page\Model::PAGE_DUE_WORK_CALENDAR,
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
        // by default, all WeightingTypes are available
        return true;
    }


    /**
     * @inheritdoc
     */
    protected static function getTemplatesDir()
    {
        return '@filterDefinitions/Assessment/WeightingType/templates';
    }
}
