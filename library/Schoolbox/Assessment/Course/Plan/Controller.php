<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Plan;

use Alaress\Database\Exception;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\ModelToArray as IndicatorModelToArray;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Mapper;
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
     * A repeatable query used for every Unit in the Unit Planner
     *
     * @var IQuery
     */
    private IQuery $textboxStatement;


    /**
     * Controller constructor.
     *
     * @param AssessmentMapperProxy $assessmentMapper
     * @param RubricIndicatorMapper $rubricIndicatorMapper
     * @param Mapper $nodeMapper
     * @param \Component_InstanceMapper $componentInstanceMapper
     */
    public function __construct(private AssessmentMapperProxy $assessmentMapper, private RubricIndicatorMapper $rubricIndicatorMapper, private Node\Mapper $nodeMapper, private \Component_InstanceMapper $componentInstanceMapper)
    {
        /* Only prepare the textbox statement once
         */
        $this->textboxStatement = $this->rubricIndicatorMapper->getDatabase()->prepareQuery("
            SELECT
                `ccih`.value as `componentHeading`,
                `textBox`.`textboxID` as `textboxID`,
                `textBox`.`heading` as `textboxHeading`,
                `textBox`.`body` as `textboxBody`,
                `textBox`.`updated_at` as `updated_at`
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
            ORDER BY textBox.updated_at
        ");
    }


    /**
     * Get the Course/Unit Planner
     *
     * @param CourseSpecification $course
     * @throws Error
     * @throws MapperException
     * @throws SchoolboxException
     * @throws Exception
     * @throws InvalidColumnException
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    public function getPlan(CourseSpecification $course): void
    {
        /* A mega struct of all the Units' data
         */
        $units = [];

        /**
         * for each unit, generate the data...
         */
        foreach ($course->getUnits() as $unit) {
            /* Gather up the textboxes and extraTextboxes meta
             */
            $textboxData = $this->findTextboxes($course->getTemplate()->getUnitTemplate()->getId(), $unit->getFolder()->getId());

            // Learning Activities
            $options = ['order' => ['sequence' => 'ASC']];
            $assessments = $this->assessmentMapper->findAllByUnitSpecificationId($unit->getId(), $options);

            // grab rubric info
            $rubricCapabilities = [];

            $rubrics = $this->rubricIndicatorMapper->findForFolder($unit->getFolder()->getId());
            $rubric = $rubrics->groupByCallback(function (RubricIndicatorModel $rubric) {
                return $rubric->getCapability()->getName();
            });

            // parse each rubric group and jsonify each of them
            foreach ($rubric as $group => $indicators) {
                $rubricCapabilities[] = [
                    'id' => $indicators->first()->getCapability()->getId(),
                    'name' => Scrub::markdown($indicators->first()->getCapability()->getName()),
                    'indicators' => $indicators->map(new IndicatorModelToArray())
                ];
            }

            // Curriculum Overview
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

            /* Aggregated data for each unit appears as a row in the Unit Planner
             * on the front end - this is basically the contract between back- and
             * front-end implementations and the basis of an api-first philosophy
             */
            $unitOut = [
                'id' => $unit->getId(),
                'title' => $unit->getTitle(),
                'homepage' => "/homepage/" . $unit->getFolder()->getId(),
                /* Textboxes in the Unit that circumstantially match ones in the Template
                 */
                'textboxes' => $textboxData['textboxes'],
                /* All the other random textboxes that don't match
                 */
                'extraTextboxes' => $textboxData['extraTextboxes'],
                /* All the assessment specs in this unit
                 */
                'activities' => $assessments->map(new UnitAssessments(
                    $this->routeGenerator,
                    $this->getAuthorizer(),
                    $this->getUser(),
                    $this->getUser()
                )),
                /* All the rubric capabilities and indicators that are in use among all
                 * the assessment specs in this unit
                 */
                'rubricCapabilities' => $rubricCapabilities,
                /* All the unique curriculum nodes that are in use by all the assessment specs
                 * in this unit
                 */
                'curriculumCodes' => $curriculumNodes->getUnique('id')
            ];

            // add this units data to the whole course units collection.
            $units[] = $unitOut;
        }

        $this->response = $this->responseFromTemplate('@coursePlan/index.twig', ['course' => $course, 'units' => $units], 'Unit Planner');
    }


    /**
     * Find all the textboxes currently in use by $unitFolderId
     *
     * (A count of extraneous textboxes outside of the ones specified by $unitTemplateId
     * is thrown into extraTextboxes)
     *
     * @param int $unitTemplateId
     * @param int $unitFolderId
     * @return array
     * @throws Exception
     * @throws \DomainException
     */
    private function findTextboxes(int $unitTemplateId, int $unitFolderId): array
    {
        /**
         * Grab the list of textboxes from the Unit Template
         */
        $module = \Component_Definition::MODULE_HOMEPAGE_TEMPLATE;
        $class = TextboxComponentController::class;
        $textboxes = [];
        $textboxesKeys = [];
        $unitTextboxes = $this->componentInstanceMapper->findByContextAndClass($module, $unitTemplateId, $class);

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

        // fetch all for the folder
        /* Find all the textboxes in this Unit Spec that happens to match
         * the ones in the template; circumstantially matching textboxes
         * appear as columns in the Unit Planner. Ones that aren't in the
         * template are called "extraTextboxes"
         */
        $extraTextboxes = 0;

        $this->textboxStatement->execute(['folderId' => $unitFolderId]);

        while ($row = $this->textboxStatement->fetch()) {
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
                // usually a teacher has added an additional textbox component, may not be an issue, but good to inform the teachers there is something non standard in the unit.
                $extraTextboxes++;
            }
        }

        return [
            'textboxes' => array_combine($textboxesKeys, $textboxes),
            'extraTextboxes' => $extraTextboxes
        ];
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
