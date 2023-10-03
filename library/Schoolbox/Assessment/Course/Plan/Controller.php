<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Plan;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\ModelToArray as IndicatorModelToArray;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Mapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Translator\UnitAssessments;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as RubricIndicatorMapper;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Model as RubricIndicatorModel;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\Curriculum\Node;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Scrub;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param AssessmentMapperProxy $assessmentMapper
     * @param RubricIndicatorMapper $rubricIndicatorMapper
     * @param Mapper                $nodeMapper
     */
    public function __construct(private AssessmentMapperProxy $assessmentMapper, private RubricIndicatorMapper $rubricIndicatorMapper, private Node\Mapper $nodeMapper)
    {
    }


    /**
     * Get the Course/Unit Planner
     *
     * @param CourseSpecification $course
     * @throws Error|MapperException
     */
    public function getPlan(CourseSpecification $course): void
    {
        // TODO: Inject the things...
        $textboxMapper = TextboxMapper::getInstance();
        $unitAssessmentsTranslator = new UnitAssessments($this->routeGenerator, $this->getUser());
        $indicatorMap = new IndicatorModelToArray();
        $units = [];
        /**
         * for each unit, generate the data...
         */
        foreach ($course->getUnits() as $unit) {
            /**
             * { // UNIT
             * "id": 6,
             * "title": "Romeo and Juliet",
             * "url": "/homepage/88",
             * "learningOutcomes": "",
             * "successCriteria": "",
             * "outcomes": "",
             * "activities": [
             * {
             * "id": 446,
             * "title": "due work 1",
             * "assessmentType": "dueWork",
             * "course": "Year 9 English",
             * "unit": "Romeo and Juliet",
             * "projectName": "Project 1",
             * "_links": {
             * "status": "\/learning\/assessments\/446\/13"
             * },
             * "assessments": null
             * }
             * ],
             * "rubricCapabilities": "",
             * "curriculumCodes": ""
             * }
             */
            // unit title / info
            $unitOut = [
                'id' => $unit->getId(),
                'title' => $unit->getTitle(),
                'homepage' => "/homepage/" . $unit->getFolder()->getId(),
            ];

            $unitOut['rubricCapabilities'] = [];
            // grab rubric info
            $rubrics = $this->rubricIndicatorMapper->findForFolder($unit->getFolder()->getId());
            $rubric = $rubrics->groupByCallback(function (RubricIndicatorModel $rubric) {
                return $rubric->getCapability()->getName();
            });

            // parse each rubric group and jsonify each of them
            foreach ($rubric as $group => $indicators) {
                $unitOut['rubricCapabilities'][] = [
                    'id' => $indicators->first()->getCapability()->getId(),
                    'name' => Scrub::markdown($indicators->first()->getCapability()->getName()),
                    'indicators' => $indicators->map($indicatorMap)
                ];
            }


            /**
             * text boxes
             */
            $textboxes = [
                'Learning Outcomes' => null,
                'Success Criteria' => null,
                'Outcomes' => null,
                'extras' => 0
            ];
            // fetch all for the folder
            $statement = $textboxMapper->getDatabase()->prepareQuery("
                SELECT
                    `ccih`.value as `componentHeading`,
                    `textBox`.`textboxID` as `textboxID`,
                    `textBox`.`heading` as `textboxHeading`,
                    `textBox`.`body` as `textboxBody`
                FROM component_definition cd
                         INNER JOIN component_instance ci ON cd.id = ci.component_definition_id
                         INNER JOIN component_config_instance cci ON cci.component_instance_id = ci.id
                         LEFT JOIN component_config_instance ccih ON ccih.component_instance_id = ci.id
                         INNER JOIN component_config_definition ccd ON cci.component_config_definition_id = ccd.id
                         LEFT JOIN component_config_definition ccdh ON  ccih.component_config_definition_id = ccdh.id
                         INNER JOIN textBox ON cci.value = textBox.textBoxID
                         INNER JOIN folder f ON ci.identifier = f.id
                WHERE cd.`class` = 'Schoolbox\\\Resource\\\Textbox\\\Component\\\Homepage\\\Controller'
                  AND ccd.`key` = 'textbox'
                  AND ccdh.`key` = 'componentname'
                  AND textBox.deleted_at IS NULL
                  AND f.deleted_at IS NULL
                  AND textBox.categoryID = :folderId
                GROUP BY textBox.textBoxID, f.id
                ORDER BY textBox.textBoxID
            ");
            $statement->execute(['folderId' => $unit->getFolder()->getId()]);
            while ($row = $statement->fetch()) {
                // Check if the textbox exists in plan?
                if (array_key_exists($row['textboxHeading'], $textboxes)) {
                    $textboxes[$row['textboxHeading']] = [
                        'id' => $row['textboxID'],
                        'body' => Scrub::html($row['textboxBody'])
                    ];
                } else {
                    //increase the extra textboxes by 1
                    $textboxes['extras']++;
                }
            }
            $unitOut['textboxes'] = $textboxes;

            // activities
            $options = ['order' => ['sequence' => 'ASC']];
            $assessments = $this->assessmentMapper->findAllByUnitSpecificationId($unit->getId(), $options);
            $unitOut['activities'] = $assessments->map($unitAssessmentsTranslator);

            // curriculum
            // build curriculum data
            $curriculumNodes = $this->nodeMapper->createCollection();
            $this->extractCurricNodes($curriculumNodes, $unit->getFolder());

            foreach ($rubrics as $rubric) {
                $this->extractCurricNodes($curriculumNodes, $rubric);
            }

            foreach ($assessments as $assessment) {
                $this->extractCurricNodes($curriculumNodes, $assessment);
            }

            /**
             * Duped code from library/Schoolbox/Curriculum/Component/CurriculumOverviewController.php
             * as we are needing the same tree format as that component.
             */
            // make sure that the node or node's ancestors do not already in the list
            // to avoid same hierarchy appears twice in the component
            $selectedNodeIds = $curriculumNodes->getPropertyValues('id');
            foreach ($curriculumNodes as $offset => $node) {
                $this->nodeMapper->loadAncestors($node);
                $ancestorIds = $this->getAllAncestorIds($node);
                $diff        = array_diff($ancestorIds, $selectedNodeIds);
                if (count($diff) !== count($ancestorIds)) {
                    $curriculumNodes->offsetUnset($offset);
                }
            }

            Node\Mapper::sortNodes($curriculumNodes, 0);

            $unitOut['curriculumCodes'] = $curriculumNodes;

            $units[] = $unitOut;
        }


        $this->response = $this->responseAsJson($units);
    return;
//        $this->response = $this->responseFromTemplate('@coursePlan/index.twig', ['course' => $course, 'units' => $units], 'Unit Planner');
    }

    /**
     * Find all ancestor ids to remove duplicates
     * @param Node\Model $node
     * @return array
     */
    private function getAllAncestorIds(Node\Model $node): array
    {
        $parentIds = [];
        if ($node->getParent() instanceof Node\Model) {
            $parentIds   = $this->getAllAncestorIds($node->getParent());
            $parentIds[] = $node->getParent()->getId();
        }
        return $parentIds;
    }

    /**
     * Extract nodes from an object that has curriculum nodes
     *
     * @param Collection $curriculumNodes The collection to inject the identified nodes into
     * @param object     $object The object to extract the nodes out of. This should use the HasCurriculumNodes trait
     * @return void
     */
    private function extractCurricNodes(Collection $curriculumNodes, object $object): void
    {
        foreach ($object->getCurriculumNodes() as $node) {
            if (!$node->getEnabled() || '' === $node->getCode()) {
                continue;
            }
            $this->nodeMapper->loadDescendants($node);
            $curriculumNodes->add($node);
        }
    }
}
