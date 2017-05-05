<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\UnitImportSpec;

use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option;
use Schoolbox\GenericCollection;

/**
 * The unit-spec that an an assessment-impl's unit-impl was imported from.
 *
 * 'Give me assessments who reside in a [project inside a] unit-impl, that was imported from unit-spec X'
 *
 * An assessment created in a UnitImpl, but not imported from a UnitSpec is still considered to be 'imported' from that unitSpec.
 *
 * Eg,* UnitSpec_A contains AssessmentSpec_X
 *    * UnitSpec_A is imported as UnitImpl_A1, containing AssessmentImpl_X1
 *    * AssessmentImpl_Y1 is created in UnitImpl_A1
 *
 * In this scenario, both AssessmentImpl_X1 and AssessmentImpl_Y1 are considered as 'imported from UnitSpec_A'
 *
 * We still need to retain '(none)' to enable a full set of options for this filter
 * Eg:
 *
 * * □ (none) (lives on folder, project on folder, unit-spec, or project in unit-spec)
 *
 * * CourseSpec A
 * * □ UnitSpec 111 (will match any assessments that reside in UnitImpls imported from UnitSpec 111)
 * * □ UnitSpec 222
 *
 * * CourseSpec B
 * * □ UnitSpec 333
 */
class Definition extends AbstractFilterDefinition
{
    const SLUG = 'uis';

    /* Show units imported to the following folder types:
     * eg,
     * * Unit A imported to Archery(group) + Math3/4(class)
     * * Unit B imported to Archery(group)
     * * Unit C imported to Math3/4(class)
     *
     * If admin only selects 'classes', then user will see Unit A + Unit C (will not see Unit B)
     *
     * Items created in UnitImpls on a class will be included in 'CLASS_UNITS
     *
     * 'NONE' => assessment is not in a unit-impl imported from a unit-spec
     */
    const KEY_NONE              = 'none';
    const KEY_IMPORTED_TO_CLASS = 'to_classes';
    const KEY_IMPORTED_TO_GROUP = 'to_groups';


    /**
     * @inheritdoc
     */
    protected function insertDynamicOptions(GenericCollection $pageOptions, $page)
    {
        // noop - abstract options are hard-coded, concrete options are run-time
        return $pageOptions;
    }


    /**
     * @inheritdoc
     */
    protected function unsetInvalidOptions(GenericCollection $pageOptions)
    {
        // noop - abstract options are hard-coded, concrete options are run-time
        return $pageOptions;
    }


    /**
     * @inheritdoc
     */
    protected function getInitialSelectedState($key, $page, $roleType)
    {
        // Nothing is selected, meaning filter is not applied
        return false;
    }


    /**
     * @inheritdoc
     */
    protected function getCustomKeys()
    {
        return [
            self::KEY_NONE,
            self::KEY_IMPORTED_TO_CLASS,
            self::KEY_IMPORTED_TO_GROUP,
        ];
    }


    /**
     * @inheritdoc
     */
    public function getInitialAvailability($customAbstractKey, $page)
    {
        // Initial config is both classes and groups are available for all pages
        return true;
    }


    /**
     * @inheritdoc
     */
    public static function getPages()
    {
        return [
            Option\Model::PAGE_MARK_BOOK,

            Option\Model::PAGE_PENDING_ASSESSMENTS
        ];
    }


    /**
     * @inheritdoc
     */
    public function getLabel($key)
    {
        switch ($key) {
            case self::KEY_NONE:
                return 'No Unit';
            case self::KEY_IMPORTED_TO_CLASS:
                return 'Units imported to classes';
            case self::KEY_IMPORTED_TO_GROUP:
                return 'Units imported to groups';
        }
        throw new \InvalidArgumentException("Unknown 'UnitSpec' filter key: $key");
    }
}
