<?php
/**
 * Factory for building a list of grade levels appropriate for a submission.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;

use StateMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Collection;

class ListFactory
{
    /**
     * @var Mapper
     */
    protected $gradeLevelMapper;

    /**
     * @var StateMapper
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
        $this->errorGradeMapper = new StateMapper();
    }

    /**
     * Builds a list of available grades for the given submission box.
     *
     * This list includes:
     * * a "none" option (no mark)
     * * all grade levels for the submission's grade group
     * * all error grades
     *
     * @param $thing
     *
     * @return Collection|Model[]
     */
    public function buildGradeList($thing)
    {
        $collection = new Collection();
        if ($thing->getMarkType()->getId() == SubmissionBox\MarkType::TYPE_LETTER) {
            // "None" option
            $noneOption = new Model();
            $noneOption
                ->setId(-1)
                ->setLowerBound(-1)
                ->setRawGrade(-1)
                ->setCaption('(none)');
            $collection->add($noneOption);

            /* FindAll gets all the options but we only want the ones associated
             * with the submissionBox, via its gradeGroup id. That wasn't being
             * updated, it was always the first one that was chosen regardless
             */
            $collection = $collection->merge($this->gradeLevelMapper->findByColumnList(
                array(
                    'submission_grade_group_id' => $thing->getGradeGroup()->getId()
                ),
                array(
                    'order' => array(
                        'submission_grade_group_id' => 'asc',
                        'lower_bound'               => 'asc',
                    ),
                )
            ));

            // Options built from errors
            foreach (\StateMapper::getInstance()->findAll() as $errorGrade) {
                $errorOption = new Model();
                $errorOption
                    ->setId($errorGrade->getDisplayAs())
                    ->setLowerBound(-1)
                    ->setRawGrade(-1)
                    ->setCaption($errorGrade->getDisplayAs());
                $collection->add($errorOption);
            }
        }
        return $collection;
    }
}
