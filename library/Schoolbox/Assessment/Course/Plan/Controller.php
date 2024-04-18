<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Plan;

use Alaress\Database\Exception;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Web\Controller\Base;
use Component_Config_Componentname;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Component\CurriculumOverviewController;
use Schoolbox\Curriculum\Node\Mapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Translator\UnitAssessments;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as RubricIndicatorMapper;
use Schoolbox\Curriculum\Node;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model;
use Schoolbox\Resource\Textbox\Component\Homepage\Controller as TextboxComponentController;
use Schoolbox\Resource\Textbox\Mapper as TextboxMapper;
use Schoolbox\L10n\RosettaStone;
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
     * @var RosettaStone
     */
    private $rosettaStone;

    /**
     * Controller constructor.
     *
     * @param AssessmentMapperProxy $assessmentMapper
     * @param RubricIndicatorMapper $rubricIndicatorMapper
     * @param Mapper $nodeMapper
     * @param \Component_InstanceMapper $componentInstanceMapper
     * @param RosettaStone $rosettaStone
     */
    public function __construct(
        private AssessmentMapperProxy $assessmentMapper,
        private RubricIndicatorMapper $rubricIndicatorMapper,
        private Node\Mapper $nodeMapper,
        private \Component_InstanceMapper $componentInstanceMapper,
        RosettaStone $rosettaStone
    ) {
        /* Only prepare the textbox statement once
         */
        $this->textboxStatement = $this->rubricIndicatorMapper->getDatabase()->prepareQuery(
            // Notes:
            //  * A similar query exists in library/Schoolbox/Search/Api/Database/ResultType/Textbox/IndexBuilder.php.
            //  * The displayed heading of a textbox component on a homepage will be:
            //     * The component name if non-empty,
            //     * otherwise the textbox heading if non-empty,
            //     * otherwise Component_Homepage_Controller::DEFAULT_COMPONENT_HEADING.
            //    (see library/Schoolbox/Resource/Textbox/Component/Homepage/Controller.php)
            //    So that is the heading we must use for the search index.
            "SELECT
            COALESCE(
                IF(cci_componentname.value = '', NULL, cci_componentname.value),
                IF(tb.heading = '', NULL, tb.heading),
                :homepageDefaultComponentHeading
            ) AS componentHeading,
            tb.textBoxID AS textboxID,
            tb.body AS textboxBody ,
            tb.updated_at AS updated_at
            FROM component_definition cd
            JOIN component_instance ci ON ci.component_definition_id = cd.id
            JOIN folder f ON f.id = ci.identifier
            JOIN component_config_definition ccd_textbox ON (
                ccd_textbox.component_definition_id = cd.id AND
                ccd_textbox.key = :homepageComponentKey
            )
            LEFT JOIN component_config_instance cci_textbox ON (
                cci_textbox.component_config_definition_id = ccd_textbox.id AND
                cci_textbox.component_instance_id = ci.id
            )
            JOIN textBox tb ON CAST(tb.textBoxID AS NCHAR)=cci_textbox.value
            JOIN component_config_definition ccd_componentname ON (
                ccd_componentname.component_definition_id = cd.id AND
                ccd_componentname.key = :componentConfigComponentNameKey
            )
            LEFT JOIN component_config_instance cci_componentname ON (
                cci_componentname.component_config_definition_id = ccd_componentname.id AND
                cci_componentname.component_instance_id = ci.id
            )
            WHERE cd.`class` = 'Schoolbox\\\Resource\\\Textbox\\\Component\\\Homepage\\\Controller'
            AND f.deleted_at IS NULL
            AND tb.deleted_at IS NULL
            AND f.id = :folderId
            ORDER BY tb.updated_at ASC"
        );

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
            AND sb.use_rubric = 1
            ORDER BY rc.id, riu.sequence, ri.name
        ");

        $this->rosettaStone = $rosettaStone;
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
         * Grab the list of curriculum components from the Unit Template
         */
        $module = \Component_Definition::MODULE_HOMEPAGE_TEMPLATE;
        $class = CurriculumOverviewController::class;
        $curriculumnTemplate = [];
        $curriculumnKey = [];
        $unitCurricComp = $this->componentInstanceMapper->findByContextAndClass($module, $course->getTemplate()->getUnitTemplate()->getId(), $class);

        foreach ($unitCurricComp as $curricComponent) {
            if ('' !== (string)$curricComponent->getConfigByKey('componentname')->getValue()) {
                // if there is a value set for the component use that
                $heading = trim($curricComponent->getConfigByKey('componentname')->getValue());
            } elseif ('' !== $curricComponent->getConfigByKey('componentname')->getDefaultValue()) {
                // is there a system default value for the component
                $heading = trim($curricComponent->getConfigByKey('componentname')->getDefaultValue());
            } else {
                // Fallback of the php default title for textbox components
                $heading = trim(CurriculumOverviewController::DEFAULT_COMPONENT_HEADING);
            }

            /**
             * we need to default and check if we have a valid curriculum filter, or just return null.
             */
            $curricFilter = $curricComponent->getConfigByKey('curriculumTreeFilter')->getValue();
            if ('0' === $curricFilter || '' === $curricFilter) {
                $curricFilter = null;
            } else {
                $curricFilter = $this->nodeMapper->findOneByColumnList(['id' => $curricFilter, 'enabled' => true,]);
            }
            $curriculumnTemplate[] = [
                'heading' => $heading,
                'filter' => $curricFilter,
            ];
            $curriculumnKey[] = strtolower($heading);
        }

        /**
         * Grab the list of textbox components from the Unit Template
         */
        $module = \Component_Definition::MODULE_HOMEPAGE_TEMPLATE;
        $class = TextboxComponentController::class;
        $textboxTemplate = [];
        $textboxKey = [];
        $unitTextboxComp = $this->componentInstanceMapper->findByContextAndClass($module, $course->getTemplate()?->getUnitTemplate()->getId(), $class);

        foreach ($unitTextboxComp as $textboxComponent) {
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

            // Provide a default value for the textbox with a negative ID to indicate it doesn't exist
            // The frontend will use this to determine whether the textbox needs to be created or not.
            // This saves needing to manipulate the initial data on the frontend on the first mount
            $textboxTemplate[] = [
                'heading' => $heading,
            ];
            $textboxKey[] = strtolower($heading);
        }

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
            $rubricCapabilities = array_values($rubricData['used']['capabilities']);

            /* Curriculum nodes
             * A unique set of curriculum nodes collated from various places:
             * folders, assessments, capabilities and indicators
             */
            // now unused, as we are only now caring about curriculum nodes on the folder, not all children items also.
            //$curriculumNodes = $this->findCurriculumNodes($unit, $assessments, $rubricData);

            //new curriculum array structure
            $curriculum = [
                'nodes' => $this->getCurriculumNodes($unit),
                'selectedNodes' => $unit->getFolder()->getCurriculumNodes()->reindex(),
                'components' => [],
            ];

            // grab list collection of curriculum overview components on the unit page
            $components = $this->componentInstanceMapper->findByContextAndClass(
                'homepage',
                $unit->getFolder()->getId(),
                CurriculumOverviewController::class
            );
            foreach ($components as $component) {
                // extract the configs.
                $instanceConfig = $component->getConfigArray();
                if ('' === trim($instanceConfig['componentname']['value'])) {
                    $instanceConfig['componentname']['value'] = $instanceConfig['componentname']['defaultValue'];
                }
                // default value it '0' so set to null for vue to work
                // when clearing the component filter (after something was filtered) it is empty (not 0) also catch that
                if ('0' === $instanceConfig['curriculumTreeFilter']['value'] || '' === $instanceConfig['curriculumTreeFilter']['value']) {
                    $instanceConfig['curriculumTreeFilter']['value'] = null;
                }
                // if we are filtering we should return the curric node
                if ($instanceConfig['curriculumTreeFilter']['value']) {
                    $instanceConfig['curriculumTreeFilter']['value'] =
                        $this->nodeMapper->findOneByColumnList(['id' => $instanceConfig['curriculumTreeFilter']['value'], 'enabled' => true,]);
                }
                $curriculum['components'][] = [
                    'componentId' => $component->getId(),
                    'heading' => $instanceConfig['componentname']['value'],
                    'filter' => $instanceConfig['curriculumTreeFilter']['value'],
                    'inTemplate'    => in_array(strtolower(trim($instanceConfig['componentname']['value'])), $curriculumnKey),
                ];
            }

            $activitiesListRoutes = [
                'create' => ["route" => "learningAssessment.getCreateAssessmentForm", "params" => [
                    'contextType' => 'unitspecs',
                    'contextId' => $unit->getId(),
                    'context' => $unit,
                ]],
                'reorder' => ["route" => "learningAssessment.postReorderAssessments", "params" => [
                    'contextType' => 'unitspecs',
                    'contextId' => $unit->getId(),
                    'context' => $unit,
                ]],
            ];

            foreach ($activitiesListRoutes as $key => $link) {
                if ($this->getAuthorizer()->isPathAuthorizedForUser($link['route'], $link['params'], $this->getUser())) {
                    $activitiesListRoutes[$key] = $this->routeGenerator->generate($link['route'], $link['params']);
                } else {
                    $activitiesListRoutes[$key] = null;
                }
            }

            /* Aggregated data for each unit appears as a row in the Unit Planner
             * on the front end - this is basically the contract between back- and
             * front-end implementations and the basis of an api-first philosophy
             */
            $unitOut = [
                'id' => $unit->getId(),
                'title' => $unit->getTitle(),
                'homepage' => "/homepage/{$unit->getFolder()->getId()}",
                'folderId' => $unit->getFolder()->getId(),
                /* Textboxes in the Unit that circumstantially match ones in the Template
                 */
                'textboxes' => $textboxData['textboxes'],
                /* All the other random textboxes that don't match
                 */
                'extraTextboxes' => $textboxData['extraTextboxes'],
                /* All the assessment specs in this unit
                 */
                'activities' => [
                    '_routes' => $activitiesListRoutes,
                    'items' => $assessments->map(new UnitAssessments(
                        $this->routeGenerator,
                        $this->getAuthorizer(),
                        $this->getUser(),
                        $this->getUser()
                    )),
                ],
                /* All the rubric capabilities and indicators that are in use among all
                 * the assessment specs in this unit
                 */
                'rubricCapabilities' => $rubricCapabilities,
                /* Curriculum data object, containing selected, and filtered curriculum nodes
                 */
                'curriculum' => $curriculum,
                'sequence' => $unit->getFolder()->getSequence(),
            ];

            // add this units data to the whole course units collection.
            $units[] = $unitOut;
        }

        // Locales required to translate the activity types
        $activityTypeLocale = [
            'project' => [
                'proper' => $this->rosettaStone->translate('project', false, true),
            ],
            'dueWork' => [
                'proper' => $this->rosettaStone->translate('dueWork', false, true),
            ],
        ];

        $this->response = $this->responseFromTemplate('@coursePlan/index.twig', [
            'course' => $course,
            'units' => $units,
            'activityTypeLocale' => $activityTypeLocale,
            'template'  => [
                'textboxes' => $textboxTemplate,
                'curriculum' => $curriculumnTemplate,
            ]
        ], "Unit Planner - {$course->getTitle()}");
    }


    /**
     * Find all the textboxes currently in use by $unitFolderId
     *
     * (A count of extraneous textboxes outside of the ones specified by $unitTemplateId
     * is thrown into extraTextboxes)
     *
     * @param null|int $unitTemplateId
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

            // Provide a default value for the textbox with a negative ID to indicate it doesn't exist
            // The frontend will use this to determine whether the textbox needs to be created or not.
            // This saves needing to manipulate the initial data on the frontend on the first mount
            $textboxes[strtolower($heading)] = [
                'id' => -1,
                'body' => ''
            ];

            $textboxesKeys[strtolower($heading)] = $heading;
        }

        // fetch all for the folder
        /* Find all the textboxes in this Unit Spec that happens to match
         * the ones in the template; circumstantially matching textboxes
         * appear as columns in the Unit Planner. Ones that aren't in the
         * template are called "extraTextboxes"
         */
        $extraTextboxes = 0;

        $this->textboxStatement->execute(
            [
                'folderId' => $unitFolderId,
                'homepageComponentKey' => TextboxMapper::HOMEPAGE_COMPONENT_KEY,
                'componentConfigComponentNameKey' => Component_Config_Componentname::KEY,
                'homepageDefaultComponentHeading' => TextboxComponentController::DEFAULT_COMPONENT_HEADING
            ]
        );

        while ($row = $this->textboxStatement->fetch()) {
            // match only on the Component Heading
            $normalisedTitle = strtolower(trim((string)$row['componentHeading']));
            // Check if the textbox should exist in the plan?
            if (array_key_exists($normalisedTitle, $textboxes)) {
                // NOTE: This is all based on component titles (which don't need to be unique),
                // so you could have 2 components on a page that share the same title, and this will just display the last entry.
                $textboxes[$normalisedTitle] = [
                    'id' => $row['textboxID'],
                    'body' => Scrub::html($row['textboxBody']),
                    'inTemplate' => true,
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
                    'id' => $capabilityId,
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
     * Build the tree of curriculum nodes for the specified unit
     * if there are no nodes, will just return null
     * direct clone from \Schoolbox\Curriculum\Component\CurriculumOverviewController::execute
     *
     * @param Model $unit
     * @return Collection
     */
    private function getCurriculumNodes(\Schoolbox\Learning\Course\DataModel\Specification\Unit\Model $unit): Collection
    {
        $curriculumNodes = $this->nodeMapper->createCollection();
        if ($unit->getFolder()->getCurriculumNodes()->count()) {
            $nodeIds = [];
            foreach ($unit->getFolder()->getCurriculumNodes() as $node) {
                if (!$node->getEnabled()) {
                    continue;
                }

                $nodeIds[] = $node->getId();
            }

            // Fetch every node which is an ancestor or descendant of one of
            // this folder's nodes, and is still enabled:
            // only enabled nodes should be selectable.
            $nodeIds = $this->nodeMapper->findFamilyTreeIds($nodeIds, 'curriculum_node_parent_id');

            // Then, sort the entire list of nodes into a tree structure.
            $curriculumNodes = $this->nodeMapper->getCollectionAsTree(
                $this->nodeMapper->findByColumnList(['id' => $nodeIds, 'enabled' => true,]),
            );
        }

        if ($curriculumNodes && $curriculumNodes->count()) {
            Node\Mapper::sortNodes($curriculumNodes, 0);
        }
        return $curriculumNodes;
    }
}
