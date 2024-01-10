<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Plan;

use Alaress\Web\Controller\Base;
use Component_Config_Componentname;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
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
use Schoolbox\Resource\Textbox\Component\Homepage\Controller as TextboxComponentController;
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
    public function __construct(private AssessmentMapperProxy $assessmentMapper, private RubricIndicatorMapper $rubricIndicatorMapper, private Node\Mapper $nodeMapper, private \Component_InstanceMapper $componentInstanceMapper)
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
        $unitAssessmentsTranslator = new UnitAssessments(
            $this->routeGenerator,
            $this->getAuthorizer(),
            $this->getUser(),
            $this->getUser()
        );
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
             * Grab the list of textboxes from the Unit Template
             */
            $module = \Component_Definition::MODULE_HOMEPAGE_TEMPLATE;
            $class = TextboxComponentController::class;
            $textboxes = [];
            $textboxesKeys = [];
            $unitTextboxes = $this->componentInstanceMapper->findByContextAndClass($module, $course->getTemplate()->getUnitTemplate()->getId(), $class);
            foreach ($unitTextboxes as $textboxComponent) {
                if ('' !== (string)$textboxComponent->getConfigByKey('componentname')->getValue()) {
                    // if there is a value set for the component use that
                    $heading = trim($textboxComponent->getConfigByKey('componentname')->getValue());
                } elseif ('' !== $textboxComponent->getConfigByKey('componentname')->getDefaultValue()) {
                    // is there a system default value for the component
                    $heading = trim($textboxComponent->getConfigByKey('componentname')->getDefaultValue());
                } else {
                    // Fallback of the php default title for textbox components
                    $heading = trim(TextboxComponentController::DEFAULT_COMPONENT_HEADING);
                }
                $textboxes[strtolower($heading)] = null;
                $textboxesKeys[strtolower($heading)] = $heading;
            }

            $extraTextboxes = 0;
            // fetch all for the folder
            $statement = $this->rubricIndicatorMapper->getDatabase()->prepareQuery(
                // A similar query exists in library/Schoolbox/Search/Api/Database/ResultType/Textbox/IndexBuilder.php.
                "SELECT
                IFNULL(cci_componentname.value, tb.heading) AS componentHeading,
                tb.textBoxID AS textboxID,
                tb.body AS textboxBody ,
                tb.updated_at AS updated_at
                FROM component_definition cd
                JOIN component_instance ci ON ci.component_definition_id = cd.id
                JOIN folder f ON f.id = ci.identifier
                JOIN component_config_definition ccd_textbox ON (
                    ccd_textbox.component_definition_id = cd.id AND
                    ccd_textbox.key = '" . TextboxMapper::HOMEPAGE_COMPONENT_KEY . "'
                )
                LEFT JOIN component_config_instance cci_textbox ON (
                    cci_textbox.component_config_definition_id = ccd_textbox.id AND
                    cci_textbox.component_instance_id = ci.id
                )
                JOIN textBox tb ON CAST(tb.textBoxID AS NCHAR)=cci_textbox.value
                JOIN component_config_definition ccd_componentname ON (
                    ccd_componentname.component_definition_id = cd.id AND
                    ccd_componentname.key = '" . Component_Config_Componentname::KEY . "'
                )
                LEFT JOIN component_config_instance cci_componentname ON (
                    cci_componentname.component_config_definition_id = ccd_componentname.id AND
                    cci_componentname.component_instance_id = ci.id
                )
                WHERE cd.`class` = 'Schoolbox\\\Resource\\\Textbox\\\Component\\\Homepage\\\Controller'
                AND f.deleted_at IS NULL
                AND tb.deleted_at IS NULL
                AND tb.categoryID = :folderId
                ORDER BY tb.updated_at ASC"
            );
            $statement->execute(['folderId' => $unit->getFolder()->getId()]);
            while ($row = $statement->fetch()) {
                // match only on the Component Heading
                $normalisedTitle = strtolower(trim((string)$row['componentHeading']));
                // Check if the textbox should exist in the plan?
                if (array_key_exists($normalisedTitle, $textboxes)) {
                    // NOTE: This is all based on component titles (which don't need to be unique),
                    // so you could have 2 components on a page that share the same title, and this will just display the last entry.
                    $textboxes[$normalisedTitle] = [
                        'id' => $row['textboxID'],
                        'body' => Scrub::html($row['textboxBody'])
                    ];
                } else {
                    // increase the extra textboxes by 1
                    // usually a teacher has added an additional textbox component, may not be an issue, but good to inform the teachers there is something non-standard in the unit.
                    $extraTextboxes++;
                }
            }
            $unitOut['textboxes'] = array_combine($textboxesKeys, $textboxes);
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
                // extract for the parent rubricCapabilities also.
                $this->extractCurricNodes($curriculumNodes, $rubric->getCapability());
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

            // display only unique items
            $unitOut['curriculumCodes'] = $curriculumNodes->getUnique('id');

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
