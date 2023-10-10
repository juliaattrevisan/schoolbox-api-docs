<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Plan;

use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\ModelToArray as IndicatorModelToArray;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Translator\UnitAssessments;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as RubricIndicatorMapper;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Model as RubricIndicatorModel;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Curriculum\Node;
use Scrub;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param AssessmentMapperProxy $assessmentMapper
     * @param RubricIndicatorMapper $rubricIndicatorMapper
     * @param Node\Mapper           $nodeMapper
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
        $unitAssessmentsTranslator = new UnitAssessments($this->routeGenerator, $this->getUser());
        $indicatorMap = new IndicatorModelToArray();
        $units = [];
        /**
         * for each unit, generate the data...
         */
        foreach ($course->getUnits() as $unit) {
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
             * 'Standard' Unit textbox collection, with an `extra` to keep track of the count of additional textboxes that don't match the other titles
             */
            $textboxes = [
                'Learning Outcomes' => null,
                'Success Criteria' => null,
                'Outcomes' => null,
            ];
            $extraTextboxes = 0;
            // fetch all for the folder
            $statement = $this->rubricIndicatorMapper->getDatabase()->prepareQuery("
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
                // Check if the textbox should exist in the plan?
                if (array_key_exists($row['textboxHeading'], $textboxes)) {
                    // NOTE: This is all based on component titles (which don't need to be unique),
                    // so you could have 2 components on a page that share the same title, and this will just display the last entry.
                    $textboxes[$row['textboxHeading']] = [
                        'id' => $row['textboxID'],
                        'body' => Scrub::html($row['textboxBody'])
                    ];
                } else {
                    // increase the extra textboxes by 1
                    // usually a teacher has added an additional textbox component, may not be an issue, but good to inform the teachers there is something non standard in the unit.
                    $extraTextboxes++;
                }
            }
            $unitOut['textboxes'] = $textboxes;
            $unitOut['extraTextboxes'] = $extraTextboxes;

            // activities
            $options = ['order' => ['sequence' => 'ASC']];
            $assessments = $this->assessmentMapper->findAllByUnitSpecificationId($unit->getId(), $options);
            $unitOut['activities'] = $assessments->map($unitAssessmentsTranslator);

            // curriculum
            // collate curriculum data
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

            // add this units data to the whole course units collection.
            $units[] = $unitOut;
        }


        $this->response = $this->responseFromTemplate('@coursePlan/index.twig', ['course' => $course, 'units' => $units], 'Unit Planner');
    }

    /**
     * Find all ancestor ids to remove duplicates
     * Dupe of \Schoolbox\Curriculum\Component\CurriculumOverviewController::getAllAncestorIds
     *
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
        // ensure the object we are attempting to extract curriculum codes actually has the required function.
        if (!method_exists($object, 'getCurriculumNodes')) {
            throw new SchoolboxException(sprintf("Object: %s does not have the `getCurriculumNodes` function.", get_class($object)));
        }
        // if there are no curriculum nodes, then just exit
        if (null === $object->getCurriculumNodes()) {
            return;
        }
        foreach ($object->getCurriculumNodes() as $node) {
            if (!$node->getEnabled() || '' === $node->getCode()) {
                continue;
            }
            $this->nodeMapper->loadDescendants($node);
            $curriculumNodes->add($node);
        }
    }
}
