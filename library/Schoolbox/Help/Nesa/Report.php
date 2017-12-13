<?php
/**
 * Nesa Report
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Help\Nesa;

use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\RubricContext;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\User;

class Report
{

    /**
     * Teacher Course
     * Course specification id
     */
    private $courseSpecId;

    /**
     * @var array
     */
    private $groups;

    /**
     * @var array
     */
    private $criteria;

    /**
     * @var Rubric\RubricMapper
     */
    private $rubricMapper;

    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * @var CourseImplementationMapper
     */
    private $courseImplementationMapper;

    /**
     * @var UnitImplementationMapper
     */
    private $unitImplementationMapper;


    /**
     * Nesa constructor.
     *
     * @param int                        $courseSpecId
     * @param array                      $descriptors   An array of descriptor text
     * @param Rubric\RubricMapper        $rubricMapper
     * @param AssessmentMapperProxy      $assessmentMapperProxy
     * @param CourseImplementationMapper $courseImplementationMapper
     * @param UnitImplementationMapper   $unitImplementationMapper
     */
    public function __construct(
        int $courseSpecId,
        array $descriptors,
        Rubric\RubricMapper $rubricMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        CourseImplementationMapper $courseImplementationMapper,
        UnitImplementationMapper $unitImplementationMapper
    ) {
        $this->courseSpecId = $courseSpecId;
        $this->descriptors = $descriptors;
        $this->rubricMapper = $rubricMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->courseImplementationMapper = $courseImplementationMapper;
        $this->unitImplementationMapper = $unitImplementationMapper;
    }

    /**
     * Find all the Unit Implementations for Teacher Course
     *
     * @param int $courseSpecId id of course spec for Teacher Course
     * @return Collection|\Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model[]
     */
    private function findUnitImplementations(int $courseSpecId)
    {
        $allUnitImplementations = new Collection();

        $allCourseImplementations = $this->courseImplementationMapper->findAllByCourseSpecificationId($courseSpecId);

        foreach($allCourseImplementations as $currentCourseImplementation) {
            $implementations = $this->unitImplementationMapper->findAllByCourseId($currentCourseImplementation->getId());

            $allUnitImplementations = $allUnitImplementations->merge($implementations);
        }

        return $allUnitImplementations;
    }

    /**
     * Find all the assessments in the Teacher Course from all Unit Implementations everywhere
     */
    private function findAssessments()
    {
        $allUnitImplementations = $this->findUnitImplementations($this->courseSpecId);

        $allAssessments = new Collection();

        foreach ($allUnitImplementations as $currentUnitImplementation) {
            $assessments = $this->assessmentMapperProxy->findAllByUnitImplementationId($currentUnitImplementation->getId());

            $allAssessments = $allAssessments->merge($assessments);
        }

        /* Load participants into assessments
         */
        return $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($allAssessments, null, false, true);
    }

    /**
     * Investigate a particular participant and find out what they got for a particular assessment
     * Store the ultra-basics in an array and return that for future rendering
     *
     * @param User        $user
     * @param IAssessable $assessment
     * @param array       $rubric
     * @param array       $descriptors Report upon these descriptors
     * @return array
     */
    private function investigate(User $user, IAssessable $assessment, array $rubric, array $descriptors)
    {
        $tempGroup = [];

        foreach ($rubric['groups'] as $groupId => $groupData) {
            $tempCriterion = [];

            foreach ($groupData['criteria'] as $criterionId => $criterionData) {
                $tempDescriptor = [];

                foreach ($criterionData['descriptors'] as $levelId => $levelData) {

                    /* Only collate the ones we selected
                     */
                    if (\in_array($levelData['text'], $descriptors, true)) {
                        if ($levelData['selected']) {
                            $tempDescriptor[$levelData['id']] = [
                                'text' => $levelData['text']
                            ];
                        } else {
                            $tempDescriptor[$levelData['id']] = [
                                'text' => ''
                            ];
                        }
                    }
                }

                $tempCriterion[$criterionId] = [
                    'name' => $criterionData['name'],
                    'descriptors' => $tempDescriptor
                ];
            }

            $tempGroup[] = [
                'name' => $groupData['name'],
                'criteria' => $tempCriterion
            ];
        }

        $tempAssessment = [
            'name' => $assessment->getTitle(),
            'user' => $user->getFullname(),
            'groups' => $tempGroup
        ];

        return $tempAssessment;
    }

    /**
     * Investigate all the assessments and their participants and harvest all the rubric selections
     */
    public function generate()
    {
        /* The output
         */
        $data = [];

        /* Get all the assessments in the Teacher Course and hydrate with all their participants
         * Hopefully that is performantly bearable
         */
        $allAssessments = $this->findAssessments();

        /* Investigate each assessment for each participant to see what everyone got
         * We need to load the rubric data separately
         */
        foreach ($allAssessments as $currentAssessment) {
            $currentAssessment->setRubric($this->rubricMapper->findByDueWork($currentAssessment));

            foreach ($currentAssessment->getAllParticipants() as $currentParticipant) {
                $context = (new RubricContext($this->rubricMapper))->generateMarkable($currentAssessment, $currentParticipant->getUser());

                /* Investigate the context for the user and assessment and find the chosen descriptors that
                 * exist in the set of rubric groups and criteria
                 */
                $data[$currentAssessment->getId()][] = $this->investigate(
                    $currentParticipant->getUser(),
                    $currentAssessment,
                    $context['rubric'],
                    $this->descriptors
                );
            }
        }

        new \Alaress_Probe($data);
        return $data;
    }
}