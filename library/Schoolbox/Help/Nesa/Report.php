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
     * Indicators to examine
     * @var array
     */
    private $indicators;

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
     * @param array                      $indicators   An array of indicator text
     * @param Rubric\RubricMapper        $rubricMapper
     * @param AssessmentMapperProxy      $assessmentMapperProxy
     * @param CourseImplementationMapper $courseImplementationMapper
     * @param UnitImplementationMapper   $unitImplementationMapper
     */
    public function __construct(
        int $courseSpecId,
        array $indicators,
        Rubric\RubricMapper $rubricMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        CourseImplementationMapper $courseImplementationMapper,
        UnitImplementationMapper $unitImplementationMapper
    ) {
        $this->courseSpecId = $courseSpecId;
        $this->indicators = $indicators;
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
     * @param array       $indicators Report upon these indicators
     * @return array
     */
    private function investigate(User $user, IAssessable $assessment, array $rubric, array $indicators)
    {
        $tempGroup = [];
        $condensed = [];

        foreach ($rubric['groups'] as $groupId => $groupData) {
            $tempCriterion = [];

            foreach ($groupData['criteria'] as $criterionId => $criterionData) {

                $tempDescriptor = [];

                if (\in_array($criterionData['name'], $indicators, true)) {
                    foreach ($criterionData['descriptors'] as $levelId => $levelData) {

                        /* Only collate the ones we selected
                         * Condense this as well, for rendering
                         * The hierarchy is useful for debugging and visualisation
                         */
                        if ($levelData['selected']) {
                            $val = [
                                'id' => $levelData['id'],
                                'text' => 'Y'
                            ];

                            $tempDescriptor[$levelData['text']] = $val;

                            /* Indicator in the heading; descriptor text in the cell
                             */
                            $condensed[$criterionData['name']] = $levelData['text'];
                        } else {
                            $val = [
                                'id' => $levelData['id'],
                                'text' => 'N'
                            ];

                            $tempDescriptor[$levelData['text']] = $val;
                        }
                    }

                    $tempCriterion[$criterionId] = [
                        'name' => $criterionData['name'],
                        'descriptors' => $tempDescriptor
                    ];
                }
            }

            $tempGroup[] = [
                'name' => $groupData['name'],
                'criteria' => $tempCriterion
            ];
        }

        $tempAssessment = [
            'name' => $assessment->getTitle(),
            'user' => $user,
            'assessmentId' => $assessment->getId(),
            'condensed' => $condensed,
            'groups' => $tempGroup,
        ];

        return $tempAssessment;
    }

    /**
     * Investigate all the assessments and their participants and harvest all the rubric selections
     */
    public function generate()
    {

        /* A condensed version of the output stack that discards the capability/indicator hierarchy
         * and provides you the descriptor outputs, dumping them into a hashtable
         */
        $condensedData = [];

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
                $current = $this->investigate(
                    $currentParticipant->getUser(),
                    $currentAssessment,
                    $context['rubric'],
                    $this->indicators
                );

                /* Store first stack of condensed descriptor data for this user into a hashtable
                 * Otherwise merge into existing
                 */
                if (!isset($condensedData[$current['user']->getId()])) {
                    $condensedData[$current['user']->getId()] = [
                        'user' => $current['user'],
                        'condensed' => $current['condensed']
                    ];
                }
                else {
                    $existingIndicators = $condensedData[$current['user']->getId()]['condensed'];
                    $additionalIndicators = $current['condensed'];

                    $condensedData[$current['user']->getId()]['condensed'] = array_merge($existingIndicators, $additionalIndicators);
                }
            }
        }

        return $condensedData;
    }
}