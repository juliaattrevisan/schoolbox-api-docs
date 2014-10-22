<?php
/**
 * Factory for building a list of grade levels appropriate for a submission.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;

use DueWork_ErrorMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Collection;

class ListFactory
{
    /**
     * @var Mapper
     */
    protected $gradeLevelMapper;

    /**
     * @var DueWork_ErrorMapper
     */
    protected $errorGradeMapper;

    /**
     * Constructor
     *
     * Currently builds dependencies itself instead of being injected.
     */
    public function __construct()
    {
        $this->gradeLevelMapper = new Mapper();
        $this->errorGradeMapper = new DueWork_ErrorMapper();
    }

    /**
     * Builds a list of available grades for the given submission box.
     *
     * This list includes:
     * * a "none" option (no mark)
     * * all grade levels for the submission's grade group
     * * all error grades
     *
     * @param SubmissionBox $box
     *
     * @return Collection|Model[]
     */
    public function buildGradeList(SubmissionBox $box)
    {
        $collection = new Collection();
        if ($box->getMarkType()->getId() == SubmissionBox\MarkType::TYPE_LETTER) {
            // "None" option
            $noneOption = new Model();
            $noneOption
                ->setId(-1)
                ->setLowerBound(-1)
                ->setCaption('(none)');
            $collection->add($noneOption);

            // Options for this grade group
            $collection->merge(
                $this->gradeLevelMapper->findByColumn(
                    'submission_grade_group_id',
                    1,
                    array(
                        'order' => array(
                            'lower_bound' => 'asc',
                        ),
                    )
                )
            );

            // Options built from errors
            foreach (\DueWork_ErrorMapper::getInstance()->findAll() as $errorGrade) {
                $errorOption = new Model();
                $errorOption
                    ->setId($errorGrade->getDisplayAs())
                    ->setLowerBound(-1)
                    ->setCaption($errorGrade->getDisplayAs());
                $collection->add($errorOption);
            }
        }
        return $collection;
    }
}
