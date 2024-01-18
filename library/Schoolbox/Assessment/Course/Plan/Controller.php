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
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Mapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Translator\UnitAssessments;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as RubricIndicatorMapper;
use Schoolbox\Curriculum\Node;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model;
use Schoolbox\Resource\Textbox\Component\Homepage\Controller as TextboxComponentController;
use Scrub;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * A repeatable query used for every Unit in the Unit Planner
     *
     * @var \Alaress\Database\Statement\Query\Base|IQuery
     */
    private \Alaress\Database\Statement\Query\Base|IQuery $textboxStatement;
    private \Alaress\Database\Statement\Query\Base|IQuery $rubricStatement;


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

        /* Only prepare the rubric statement once
         */
        $this->rubricStatement = $this->rubricIndicatorMapper->getDatabase()->prepareQuery("
            SELECT 
                rc.id AS capability_id,
                rc.name AS capability_name,
                ri.id AS indicator_id,
                ri.name AS indicator_name
            FROM course_unit_specification cus
            INNER JOIN assessment_course_unit_specification acus ON cus.id = acus.course_unit_specification_id
            INNER JOIN submission_box sb ON acus.assessment_id = sb.assessment_id
            INNER JOIN rubric_indicator_usage riu ON sb.id = riu.submission_box_id
            INNER JOIN rubric_indicator ri ON riu.rubric_indicator_id = ri.id
            INNER JOIN rubric_capability rc ON ri.rubric_capability_id = rc.id
            WHERE cus.folder_id = :unitFolderId
            AND rc.deleted_at IS NULL
            AND ri.deleted_at IS NULL
            ORDER BY rc.id, riu.sequence, ri.name
        ");
    }


    /**
     * Get the Course/Unit Planner
     *
     * @param CourseSpecification $course
     * @throws Error
     * @throws Exception
     * @throws InvalidColumnException
     * @throws \DomainException
     * @throws \LogicException
     * @throws \PDOException
     */
    public function getPlan(CourseSpecification $course): void
    {
        /* A mega struct of all the Units' data
         */
        $units = [];

        /**
         * for each unit, generate the data...
         *
         * Maybe one day we can separate textboxData, assessments, rubricData, etc.
         * into separate payloads to be accessed asynchronously via frontend and
         * improve object cohesion somewhat, but this will suffice for now
         */
        foreach ($course->getUnits() as $unit) {
            /* Gather up the textboxes and extraTextboxes meta
             */
            $textboxData = $this->findTextboxes($course->getTemplate()?->getUnitTemplate()->getId(), $unit->getFolder()->getId());

            // Learning Activities
            $assessments = $this->assessmentMapper->findAllByUnitSpecificationId($unit->getId(), [
                'order' => [
                    'sequence' => 'ASC'
                ]
            ]);

            /* Get a set of rubrics that were used among all the assessment specs
             * This also comes with all the cap ids and indicator ids
             */
            $rubricData = $this->findRubrics($unit->getFolder()->getId());

            /* TODO Later on we can provide a list of rubrics that exist in the whole course
             *  and diff them in some way so users can see what they haven't planned properly
             */
            $rubricCapabilities = $rubricData['used']['capabilities'];

            /* Curriculum nodes
             * A unique set of curriculum nodes collated from various places:
             * folders, assessments, capabilities and indicators
             */
            $curriculumNodes = $this->findCurriculumNodes($unit, $assessments, $rubricData);

            /* Aggregated data for each unit appears as a row in the Unit Planner
             * on the front end - this is basically the contract between back- and
             * front-end implementations and the basis of an api-first philosophy
             */
            $unitOut = [
                'id' => $unit->getId(),
                'title' => $unit->getTitle(),
                'homepage' => "/homepage/{$unit->getFolder()->getId()}",
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
                'curriculumCodes' => $curriculumNodes
            ];

            // add this units data to the whole course units collection.
            $units[] = $unitOut;
        }

        $this->response = $this->responseFromTemplate('@coursePlan/index.twig', [
            'course' => $course,
            'units' => $units
        ], "Unit Planner - {$course->getTitle()}");
    }


    /**
     * Find all the textboxes currently in use by $unitFolderId
     *
     * (A count of extraneous textboxes outside of the ones specified by $unitTemplateId
     * is thrown into extraTextboxes)
     *
     * @param int|null $unitTemplateId|null
     * @param int $unitFolderId
     * @return array
     * @throws Exception
     * @throws \DomainException
     */
    private function findTextboxes(?int $unitTemplateId, int $unitFolderId): array
    {
        /* In case the course wasn't hydrated with all the parts
         */
        if (null === $unitTemplateId) {
            return [
                'textboxes' => [],
                'extraTextboxes' => 0
            ];
        }

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
     * We are after all the unique rubric capabilities and indicators that
     * were in use by all the assessment specs in a course. We can find
     * all of them with one mega query.
     *
     * Later on we can augment this with all the unique rubric capabilities and
     * indicators that exist in a course; not just the ones in use.
     *
     * @param int $unitFolderId
     * @return array
     * @throws Exception
     */
    private function findRubrics(int $unitFolderId): array
    {
        /* TODO Add a list of all the rubric caps and inds that *should* have been fulfilled
         *  so users can compare it against the ones that *were* fulfilled (a diff between them)
         */
        $rubrics = [
            'available' => [
                'capabilities' => [],
                /* Cross-referencing for fetching curriculum nodes separately
                 */
                'capabilityIds' => [],
                'indicatorIds' => []
            ],
            'used' => [
                'capabilities' => [],
                /* Cross-referencing for fetching curriculum nodes separately
                 */
                'capabilityIds' => [],
                'indicatorIds' => []
            ],
        ];

        $this->rubricStatement->execute([
            'unitFolderId' => $unitFolderId
        ]);

        /* Accumulate a unique set of capabilities and indicators
         *
         * Note that capabilities/indicators can be markdown stuff
         */
        while ($row = $this->rubricStatement->fetch(\PDO::FETCH_ASSOC)) {
            $capabilityId = $row['capability_id'];
            $indicatorId = $row['indicator_id'];

            /* Cross-referencing for fetching curriculum nodes separately
             */
            $rubrics['used']['capabilityIds'][$capabilityId] = $capabilityId;
            $rubrics['used']['indicatorIds'][$indicatorId] = $indicatorId;

            /* Initialise it, if it hasn't already existed
             */
            if (!isset($rubrics['used']['capabilities'][$capabilityId])) {
                $rubrics['used']['capabilities'][$capabilityId] = [
                    'name' => Scrub::markdown($row['capability_name']),
                    'indicators' => []
                ];
            }

            $rubrics['used']['capabilities'][$capabilityId]['indicators'][$indicatorId] = [
                'name' => Scrub::markdown($row['indicator_name'])
            ];
        }

        return $rubrics;
    }


    /**
     * Find all the unique curriculum nodes used in:
     *     - folders
     *     - assessments
     *     - capabilities
     *     - indicators
     *
     * Capabilities' and Indicators' curriculum nodes won't be hydrated;
     * do those separately, but you can mine out the ones from Folders
     * and Assessments - those should already exist.
     *
     * @param Model $unit
     * @param Collection $assessments
     * @param array $rubricData
     * @return GenericCollection
     * @throws \PDOException
     */
    private function findCurriculumNodes(
        \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model $unit,
        Collection $assessments,
        array $rubricData
    ): \Schoolbox\GenericCollection {
        $curriculumNodes = $this->nodeMapper->createCollection();

        /* Nodes from the folder
         */
        $curriculumNodes = $curriculumNodes->merge($unit->getFolder()->getCurriculumNodes());

        /* Nodes from the assessment
         */
        foreach ($assessments as $assessment) {
            $curriculumNodes = $curriculumNodes->merge($assessment->getCurriculumNodes());
        }

        /* Nodes from the capabilities and indicators needs their own prehydration
         * Capabilities might not exist; indicators might not exist.
         */
        $query = [];

        if (\count($rubricData['used']['capabilityIds'])) {
            $capabilityIds = implode(',', $rubricData['used']['capabilityIds']);

            $query[] = "
                SELECT curriculum_node_id
                FROM curriculum_node_for_rubric_capability
                WHERE rubric_capability_id IN ({$capabilityIds})
            ";
        }

        if (\count($rubricData['used']['indicatorIds'])) {
            $indicatorIds = implode(',', $rubricData['used']['indicatorIds']);

            $query[] = "
                SELECT curriculum_node_id
                FROM curriculum_node_for_rubric_indicator
                WHERE rubric_indicator_id IN ({$indicatorIds})
            ";
        }

        if (\count($query)) {
            $statement = $this->nodeMapper->getDatabase()->prepare(implode(' UNION ', $query));
            $statement->execute();

            $nodeIds = [];

            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $nodeIds[$row['curriculum_node_id']] = $row['curriculum_node_id'];
            }

            if (\count($nodeIds)) {
                $curriculumNodes = $curriculumNodes->merge($this->nodeMapper->findByColumnList([
                    'id' => array_values($nodeIds),
                    'enabled' => 1
                ]));
            }
        }

        /* Unique this set
         */
        $curriculumNodes = $curriculumNodes->indexBy('id');

        /* Remove any curriculum nodes that aren't enabled
         * Remove any curriculum nodes that have a meaningless code
         */
        foreach ($curriculumNodes as $nodeId => $node) {
            if (!$node->getEnabled() || '' === $node->getCode()) {
                /* Did we really need these right now?
                 * Temporarily disabled for speed; re-enable when you actually need it
                 */
//                $this->nodeMapper->loadAncestors($node);
//                $this->nodeMapper->loadDescendants($node);

                $ancestorIds = $this->getAllAncestorIds($node);
                $diff = array_diff($ancestorIds, $curriculumNodes->keys());

                if (count($diff) !== count($ancestorIds)) {
                    $curriculumNodes->offsetUnset($nodeId);
                }
            }
        }

        Node\Mapper::sortNodes($curriculumNodes, 0);

        return $curriculumNodes;
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
}
