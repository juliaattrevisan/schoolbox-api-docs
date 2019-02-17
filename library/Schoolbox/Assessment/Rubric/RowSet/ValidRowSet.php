<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet;

use Alaress\Specification\Base;
use Alaress\Specification\Conjunction;
use Alaress\Specification\ISpecification;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;

/**
 * Overall rowset specification pattern.
 *
 * Encapsulates the three extant rowset specification patterns:
 * * `ValidUniqueRowSetForFolder`
 * * `ValidUniqueRowSetForCourseAndUnit`
 * * `ValidRowSetDeletion`
 */
class ValidRowSet extends Base
{
    /**
     * @var ISpecification
     */
    private $internalSpecification;


    /**
     * Constructor
     *
     * @param RosettaStone            $translator
     * @param UnitSpecificationMapper $unitSpecMapper
     * @param RubricMapper            $rubricMapper
     * @param RowSet                  $existingRowSet
     * @param bool                    $allowModifyUsed
     * @param IAssessable|null        $allowModifyExclusion
     */
    public function __construct(
        RosettaStone $translator,
        UnitSpecificationMapper $unitSpecMapper,
        RubricMapper $rubricMapper,
        RowSet $existingRowSet,
        bool $allowModifyUsed,
        ?IAssessable $allowModifyExclusion
    ) {
        $this->internalSpecification = new Conjunction([
            new ValidUniqueRowSetForFolder($translator),
            new ValidUniqueRowSetForCourseAndUnit($translator, $unitSpecMapper, $rubricMapper),
            new ValidRowSetDeletion($translator, $existingRowSet, $allowModifyUsed, $allowModifyExclusion),
        ]);
    }


    /**
     * @inheritdoc
     */
    public function isSatisfiedBy($candidate): bool
    {
        return $this->internalSpecification->isSatisfiedBy($candidate);
    }
}
