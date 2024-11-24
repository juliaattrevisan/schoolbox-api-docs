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
use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Web\Controller\Base;
use Component_Config_Componentname;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Component\CurriculumOverviewController;
use Schoolbox\Curriculum\Node\Mapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Translator\UnitAssessments;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as RubricIndicatorMapper;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Model as RubricIndicatorModel;
use Schoolbox\Curriculum\Node;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Folder\Translator\ModelToArray\LinkOnly;
use Schoolbox\Resource\Textbox;
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
     * @param AssessmentMapperProxy     $assessmentMapper
     * @param RubricIndicatorMapper     $rubricIndicatorMapper
     * @param Mapper                    $nodeMapper
     * @param \Component_InstanceMapper $componentInstanceMapper
     * @param Textbox\Mapper            $textboxMapper
     * @param CourseConfigMapper        $courseConfigMapper
     * @param UnitSpecificationMapper   $unitSpecificationMapper
     * @param Access                    $access
     */
    public function __construct(
        private AssessmentMapperProxy $assessmentMapper,
        private RubricIndicatorMapper $rubricIndicatorMapper,
        private Node\Mapper $nodeMapper,
        private \Component_InstanceMapper $componentInstanceMapper,
        private Textbox\Mapper $textboxMapper,
        private CourseConfigMapper $courseConfigMapper,
        private UnitSpecificationMapper $unitSpecificationMapper,
        private Access $access,
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
            cci_textbox.component_instance_id AS componentID,
            tb.textBoxID AS textboxID,
            tb.body AS textboxBody ,
            tb.updated_at AS updated_at
            FROM component_definition cd
            JOIN component_instance ci ON (ci.component_definition_id = cd.id AND ci.module = 'homepage')
            JOIN folder f ON f.id = ci.identifier
            JOIN component_config_definition ccd_textbox ON (
                ccd_textbox.component_definition_id = cd.id AND
                ccd_textbox.key = :homepageComponentKey
            )
            LEFT JOIN component_config_instance cci_textbox ON (
                cci_textbox.component_config_definition_id = ccd_textbox.id AND
                cci_textbox.component_instance_id = ci.id
            )
            LEFT JOIN textBox tb ON (
                CAST(tb.textBoxID AS NCHAR)=cci_textbox.value AND
                tb.deleted_at IS NULL
            )
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
        $curriculumTemplate = [];
        $curriculumKey = [];
        $curriculumDefId = null;
        $unitCurricComp = $this->componentInstanceMapper->findByContextAndClass($module, $course->getTemplate()?->getUnitTemplate()->getId(), $class);

        foreach ($unitCurricComp as $curricComponent) {
            // grab the component ID, for use later
            $curriculumDefId = $curricComponent->getDefinitionId();

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
            /**
             * the default value is '0' if we add a node and remove it it becomes '' and legacy components don't have the config so will be null
             */
            if ('0' === $curricFilter || '' === $curricFilter || null === $curricFilter) {
                $curricFilter = null;
            } else {
                $curricFilter = $this->nodeMapper->findOneByColumnList(['id' => $curricFilter, 'enabled' => true,]);
            }
            $curriculumTemplate[] = [
                'heading' => $heading,
                'filter' => $curricFilter,
            ];
            $curriculumKey[] = strtolower($heading);
        }

        /**
         * Grab the list of textbox components from the Unit Template
         */
        $module = \Component_Definition::MODULE_HOMEPAGE_TEMPLATE;
        $class = Textbox\Component\Homepage\Controller::class;
        $textboxTemplate = [];
        $textboxKey = [];
        $textboxDefId = null;
        $unitTextboxComp = $this->componentInstanceMapper->findByContextAndClass($module, $course->getTemplate()?->getUnitTemplate()->getId(), $class);

        foreach ($unitTextboxComp as $textboxComponent) {
            // grab the component ID, for use later
            $textboxDefId = $textboxComponent->getDefinitionId();

            if ('' !== (string)$textboxComponent->getConfigByKey('componentname')->getValue()) {
                // if there is a value set for the component use that
                $heading = trim($textboxComponent->getConfigByKey('componentname')->getValue());
            } elseif ('' !== $textboxComponent->getConfigByKey('componentname')->getDefaultValue()) {
                // is there a system default value for the component
                $heading = trim($textboxComponent->getConfigByKey('componentname')->getDefaultValue());
            } else {
                // Fallback of the php default title for textbox components
                $heading = trim(Textbox\Component\Homepage\Controller::DEFAULT_COMPONENT_HEADING);
            }

            // Provide a default value for the textbox with a negative ID to indicate it doesn't exist
            // The frontend will use this to determine whether the textbox needs to be created or not.
            // This saves needing to manipulate the initial data on the frontend on the first mount
            $textboxTemplate[] = [
                'heading' => $heading,
                'emptyStateMessage' => $textboxComponent->getConfigByKey('emptyStateMessage')->getValue(),
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
            // add this units data to the whole course units collection.
            if ($this->access->getFolderAccess($this->getUser(), $unit->getFolder()->getId()) >= UserAccess::ACCESS_READ) {
                $units[] = $this->getUnitData(
                    $unit,
                    $course->getTemplate()?->getUnitTemplate()->getId(),
                    $curriculumKey,
                    $textboxDefId,
                    $curriculumDefId,
                );
            }
        }

        $this->response = $this->responseFromTemplate('@coursePlan/index.twig', [
            'course' => $course,
            'units' => $units,
            'template'  => [
                'textboxDefId' => $textboxDefId,
                'curriculumDefId' => $curriculumDefId,
                'textboxes' => $textboxTemplate,
                'curriculum' => $curriculumTemplate,
            ]
        ], "Unit Planner - {$course->getTitle()}");
    }

    /**
     * Get csv of all textboxes from all unit pages, to confirm they have the correct textbox title, component heading, and actually contain data
     *
     * @api   GET /course/plan/audit
     *
     * @return void
     */
    public function getAudit()
    {
        $units = [];

        //build the csv
        $rowHeadings = [
            'Course Title',
            'Course Homepage ID',
            'Course ID',
            'Course Framework',
            'Unit Title',
            'Unit Homepage ID',
            'Edit Link',
            'Textbox Component Name',
            'Textbox Component ID',
            'Textbox Title',
            'Textbox ID',
            'Textbox Content (empty string, empty state)'
        ];

        // base filename with school code and date
        $filename = sprintf("unit-audit-%s-%s.csv", $this->getConfigLoader()->get('site_school_code'), \Alaress_Date_Format::now()->toIso8601String());
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();

        // fetch all units/courses
        $courseUnitQuery = $this->rubricIndicatorMapper->getDatabase()->prepareQuery("select
            courseFolder.name as 'course.name',
            courseFolder.id as 'course.id',
            course_specification.id as 'course_specification.id',
            unitFolder.name as 'unit.name',
            unitFolder.id as 'unit.id',
            courseTemplates.name as 'framework'
        FROM course_specification
        join courseTemplates on course_specification.template_id = courseTemplates.id
        join folder courseFolder on course_specification.folder_id = courseFolder.id
        join course_unit_specification on course_specification.id = course_unit_specification.course_specification_id
        join folder unitFolder on course_unit_specification.folder_id = unitFolder.id");

        $courseUnitQuery->execute();
        while ($row = $courseUnitQuery->fetch(\PDO::FETCH_ASSOC)) {
            $units[$row['unit.id']] = $row;
        }

        $unitPlaceHolder = Builder::getArrayPlaceholders(array_keys($units), 'units');

        // fetch all textboxes with components from these units
        // clone with extension from \Schoolbox\Search\Api\Database\ResultType\Textbox\IndexBuilder::buildUpdates
        $compTextQuery = $this->rubricIndicatorMapper->getDatabase()->prepareQuery("SELECT
            tb.textBoxID AS id,
            f.id AS folder_id,
            cci_componentname.value as 'componentName',
            ci.id as 'componentId',
            tb.heading as 'textboxHeading',
            tb.updated_at AS date,
            tb.updated_by AS updated_by,
            tb.body AS textboxBody
        FROM component_definition cd
                 JOIN component_instance ci ON ci.component_definition_id = cd.id and ci.module = 'homepage'
                 JOIN folder f ON f.id = ci.identifier
                 JOIN component_config_definition ccd_textbox ON (
            ccd_textbox.component_definition_id = cd.id AND
            ccd_textbox.key = :homepageComponentKey
            )
                 LEFT JOIN component_config_instance cci_textbox ON (
            cci_textbox.component_config_definition_id = ccd_textbox.id AND
            cci_textbox.component_instance_id = ci.id
            )
                 LEFT JOIN textBox tb ON CAST(tb.textBoxID AS NCHAR)=cci_textbox.value
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
        AND f.id in {$unitPlaceHolder};
        ");


        header('Content-type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");

        $fp = fopen("php://output", 'w');
        fputcsv($fp, $rowHeadings);

        /**
         * $rowHeadings = [
         * 'Course Title',
         * 'Course Homepage ID',
         * 'Course Specification ID',
         * 'Unit Title',
         * 'Unit Homepage ID',
         * 'Textbox Component Name',
         * 'Textbox Title',
         * 'Textbox ID',
         * 'Textbox Content (empty string, empty state)'
         * ];
         */
        $compTextQuery->execute(array_merge(
            [
                'homepageComponentKey' => Textbox\Mapper::HOMEPAGE_COMPONENT_KEY,
                'componentConfigComponentNameKey' => Component_Config_Componentname::KEY,
            ],
            Builder::getArrayParameters(array_keys($units), 'units')
        ));
        while ($row = $compTextQuery->fetch(\PDO::FETCH_ASSOC)) {
            $componentName = $row['componentName'] ?? 'NULL';
            // if component is empty string, use the system default
            if (trim($componentName) === '') {
                $componentName = Textbox\Component\Homepage\Controller::DEFAULT_COMPONENT_HEADING;
            }
            //build the row and send
            fputcsv($fp, [
                $units[$row['folder_id']]['course.name'],
                $units[$row['folder_id']]['course.id'],
                $units[$row['folder_id']]['course_specification.id'],
                $units[$row['folder_id']]['framework'],
                $units[$row['folder_id']]['unit.name'],
                $units[$row['folder_id']]['unit.id'],
                $urlBase->withPath(sprintf("/homepage/%s", $units[$row['folder_id']]['unit.id']))->withFragment(sprintf("component%s", $row['componentId'])),
                $componentName,
                $row['componentId'] ?? 'NULL',
                $row['textboxHeading'] ?? 'NULL',
                $row['id'] ?? 'NULL',
                $row['textboxBody'] ?? 'NULL',
            ]);
        }

        // fetch all textboxes with no component from these units
        // clone with extension from \Schoolbox\Search\Api\Database\ResultType\Textbox\IndexBuilder::buildUpdates
        $textQuery = $this->rubricIndicatorMapper->getDatabase()->prepareQuery("SELECT
            tb2.textBoxID AS id,
            tb2.heading as 'textboxHeading',
            tb2.updated_at AS date,
            tb2.updated_by AS updated_by,
            tb2.body AS textboxBody,
            tb2.categoryID as folderId
        FROM textBox tb2
                 LEFT JOIN component_config_instance cci2 ON (
            cci2.component_config_definition_id = (
                SELECT id
                FROM component_config_definition ccd2
                WHERE ccd2.key = :homepageComponentKey
            ) AND
            cci2.value = CAST(tb2.textBoxID AS NCHAR)
            )
        WHERE tb2.deleted_at IS NULL
          AND cci2.id IS NULL
          AND tb2.categoryID in {$unitPlaceHolder}
        ");

        /**
         * $rowHeadings = [
         * 'Course Title',
         * 'Course Homepage ID',
         * 'Unit Title',
         * 'Unit Homepage ID',
         * 'Textbox Component Name',
         * 'Textbox Title',
         * 'Textbox ID',
         * 'Textbox Content (empty string, empty state)'
         * ];
         */
        $textQuery->execute(array_merge(
            [
                'homepageComponentKey' => Textbox\Mapper::HOMEPAGE_COMPONENT_KEY
            ],
            Builder::getArrayParameters(array_keys($units), 'units')
        ));
        while ($row = $textQuery->fetch(\PDO::FETCH_ASSOC)) {
            //build the row and send
            fputcsv($fp, [
                $units[$row['folderId']]['course.name'],
                $units[$row['folderId']]['course.id'],
                $units[$row['folderId']]['course_specification.id'],
                $units[$row['folderId']]['framework'],
                $units[$row['folderId']]['unit.name'],
                $units[$row['folderId']]['unit.id'],
                $urlBase->withPath(sprintf("/textbox/folder/%s", $units[$row['folderId']]['unit.id'])),
                'No Component',
                'No Component',
                $row['textboxHeading'] ?? 'NULL',
                $row['id'] ?? 'NULL',
                $row['textboxBody'] ?? 'NULL',
            ]);
        }
        exit(1);
    }

    /**
     * Super Only endpoint
     * @api POST /course/plan/{courseID}/newUnit
     * @param CourseSpecification $course
     * @return void
     */
    public function newUnit(CourseSpecification $course)
    {
        // assume course exists.
        // ensure the specified title is valid whilst trimming the title
        $unitName = trim($this->requestPost->get('name') ?? '');
        if (!$unitName) {
            $problem = (new ApiProblem('Could not create unit. You need to give the unit a name'))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)
                ->withStatus(400);
            return; // exit early don't try to create a new unit with no name
        }

        // create the new unit
        try {
            // Need:
            // base config
            // course config
            $rolePermissions = [];

            foreach ($this->courseConfigMapper->findByColumn('page', 'unit') as $courseConfig) {
                $rolePermissions[$courseConfig->getRoleId()] = $courseConfig->getPermission();
            }
            /** @var \Schoolbox\Assessment\Course\Controller $courseController */
            $courseController = $this->getController('course');

            $folder = $courseController->persistFolder(
                $unitName,
                $course->getFolder()->getId(),
                [],
                (bool)$this->getConfigLoader()->get('course_builder_default_unit_page_inherit'),
                $course->getTemplate()->getUnitTemplate()->getId(),
                $rolePermissions,
                false,
                []
            );

            // Persist the unit too
            $unitSpec = $this->unitSpecificationMapper->createModel()
                ->setFolder($folder)
                ->setCourse($course);

            $this->unitSpecificationMapper->save($unitSpec);

            // return json of that unit specifically with components identically as the unit planner unit payload (part payload)
            [$curriculumTemplate, $curriculumKey] = $this->getTemplateCurric($course->getTemplate()->getUnitTemplate()->getId());
            // Textbox and curriculum def ID can be null for now:
            // AFAICT this is unused
            $this->response = $this->responseAsJson(
                $this->getUnitData(
                    $unitSpec,
                    $course->getTemplate()->getUnitTemplate()->getId(),
                    $curriculumKey,
                    null,
                    null,
                )
            )
                ->withStatus(201);
        } catch (\Exception $e) {
            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus($e->getCode())
                ->asArray();
            $this->response = $this->responseAsJson($problem)
                ->withStatus($e->getCode());
            return;
        }
    }

    /**
     * Find all the textboxes currently in use by $unitFolderId
     *
     * (A count of extraneous textboxes outside of the ones specified by $unitTemplateId
     * is thrown into extraTextboxes)
     *
     * @param null|int    $unitTemplateId
     * @param FolderModel $unitFolder
     * @return array
     * @throws Exception
     * @throws \DomainException
     */
    private function findTextboxes(?int $unitTemplateId, FolderModel $unitFolder): array
    {
        /* In case the course wasn't hydrated with all the parts
         */
        if (null === $unitTemplateId) {
            return [
                'textboxes' => [],
                'extraTextboxes' => 0,
            ];
        }

        /**
         * Grab the list of textboxes from the Unit Template
         */
        $module = \Component_Definition::MODULE_HOMEPAGE_TEMPLATE;
        $class = Textbox\Component\Homepage\Controller::class;
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
                $heading = trim(Textbox\Component\Homepage\Controller::DEFAULT_COMPONENT_HEADING);
            }

            /*
             * Provide a default value for the textbox with a negative ID to indicate it doesn't exist
             * The frontend will use this to determine whether the textbox needs to be created or not.
             * This saves needing to manipulate the initial data on the frontend on the first mount
             *
             * NOTE: This is the base structure for textbox data expected by the unit planner as per:
             *   TextboxContent in library/Schoolbox/Assessment/Course/Plan/templates/vue/unitPlannerTypes.ts
             */
            $textboxes[strtolower($heading)] = [
                'componentId' => -1,
                'textboxId' => -1,
                'body' => '', // Empty string and not null as frontend expects a string (easier to handle in Vue)
                'inTemplate' => false,
                '_links' => [
                    'update' => null,
                ]
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
                'folderId' => $unitFolder->getId(),
                'homepageComponentKey' => Textbox\Mapper::HOMEPAGE_COMPONENT_KEY,
                'componentConfigComponentNameKey' => Component_Config_Componentname::KEY,
                'homepageDefaultComponentHeading' => Textbox\Component\Homepage\Controller::DEFAULT_COMPONENT_HEADING,
            ]
        );

        while ($row = $this->textboxStatement->fetch()) {
            // match only on the Component Heading
            $normalisedTitle = strtolower(trim((string)$row['componentHeading']));
            // Check if the textbox should exist in the plan?
            if (array_key_exists($normalisedTitle, $textboxes)) {
                // NOTE: This is all based on component titles (which don't need to be unique),
                // so you could have 2 components on a page that share the same title, and this will just display the last entry.

                $textboxes[$normalisedTitle]['componentId'] = $row['componentID'];
                $textboxes[$normalisedTitle]['textboxId'] = $row['textboxID'] ?? -1; // The component may exist, but not have a textbox yet.
                $textboxes[$normalisedTitle]['inTemplate'] = true; // It exists in the template if we've gotten here
            } else {
                // increase the extra textboxes by 1
                // usually a teacher has added an additional textbox component, may not be an issue, but good to inform the teachers there is something non standard in the unit.
                $extraTextboxes++;
            }
        }

        // Find actual textbox models, and hydrate and translate them before
        // setting them in `$textboxes`.
        $textboxTranslator = new Textbox\Translator\ModelToArray(
            new LinkOnly(0),
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            new ScrubAdapter()
        );
        $translatedTextboxes = $this->textboxMapper
            ->findByColumn(
                'textBoxID',
                array_filter(array_column($textboxes, 'textboxId'), fn ($id) => $id > 0),
            )
            ->indexBy('id')
            ->map($textboxTranslator);

        foreach ($textboxes as $normalisedTitle => $textbox) {
            if ($textbox['textboxId'] < 0) {
                continue; // This textbox doesn't exist yet, so there won't be a translation for it.
            }

            $translatedTextbox = $translatedTextboxes[$textbox['textboxId']];

            // Assign (not overwrite) the relevant translated data to ensure the structure still conforms to what the FE expects
            $textboxes[$normalisedTitle]['body'] = $translatedTextbox['body'];
            $textboxes[$normalisedTitle]['_links']['update'] = $translatedTextbox['_links']['update'];
        }

        return [
            'textboxes' => array_combine($textboxesKeys, $textboxes),
            'extraTextboxes' => $extraTextboxes,
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
    private function findRubrics(int $unitFolderId, $courseFolderId): array
    {
        $rubrics = [
            'available' => $this->getCourseUnitRubric($courseFolderId, $unitFolderId),
            'used' => [
                'capabilities' => [],
                /* Cross-referencing for fetching curriculum nodes separately
                 */
                'capabilityIds' => [],
                'indicatorIds' => [],
            ],
        ];

        $this->rubricStatement->execute([
            'unitFolderId' => $unitFolderId,
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
                    'course' => false,
                    'indicators' => [],
                ];
            }

            $rubrics['used']['capabilities'][$capabilityId]['indicators'][$indicatorId] = [
                'id'    => $indicatorId,
                'name'  => Scrub::markdown($row['indicator_name']),
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
    private function getCurriculumNodes(Model $unit): Collection
    {
        $curriculumNodes = $this->nodeMapper->createCollection();
        if ($unit->getFolder()->getCurriculumNodes()->count()) {
            $nodeIds = [];
            foreach ($unit->getFolder()->getCurriculumNodes() as $node) {
                $nodeIds[] = $node->getId();
            }

            // Fetch every node which is an ancestor or descendant of one of this folder's nodes.
            // Disabled nodes are included here because the only point of disabling a node is to prevent
            // future links to that node from being created.
            $nodeIds = $this->nodeMapper->findFamilyTreeIds($nodeIds, 'curriculum_node_parent_id');

            // Then, sort the entire list of nodes into a tree structure.
            $curriculumNodes = $this->nodeMapper->getCollectionAsTree(
                $this->nodeMapper->findByColumnList(['id' => $nodeIds]),
            );
        }

        if ($curriculumNodes->count()) {
            Node\Mapper::sortNodes($curriculumNodes, 0);
        }
        return $curriculumNodes;
    }

    /**
     * helper common function to fetch the content of the unit and its components in a standard format
     * @param Model    $unit
     * @param int      $templateId      id of the homepage template used to standardize the unit
     * @param array    $curriculumKey   what curriculum components titles exist in the component, if any
     * @param int|null $textboxDefId    The definition ID of the textbox component, if any
     * @param int|null $curriculumDefId The definition ID of the curriculum component, if any
     * @return array
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\Mapper\Exception\NotFound
     */
    private function getUnitData(
        Model $unit,
        int $templateId,
        array $curriculumKey,
        ?int $textboxDefId,
        ?int $curriculumDefId
    ): array {
        /* Gather up the textboxes and extraTextboxes meta
         */
        $textboxData = $this->findTextboxes($templateId, $unit->getFolder());

        // Learning Activities
        $assessments = $this->assessmentMapper->findAllByUnitSpecificationId($unit->getId(), [
            'order' => [
                'sequence' => 'ASC',
            ],
        ]);

        /* Get a set of rubrics that were used among all the assessment specs
         * This also comes with all the cap ids and indicator ids
         */
        $rubricData = $this->findRubrics($unit->getFolder()->getId(), $unit->getCourse()->getFolder()->getId());

        $usedRubricCapabilities = array_values($rubricData['used']['capabilityIds']);
        $usedRubricIndicators = array_values($rubricData['used']['indicatorIds']);
        $availableRubricCapabilities = array_values($rubricData['available']['capabilities']);

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
                'heading' => trim($instanceConfig['componentname']['value']),
                'filter' => $instanceConfig['curriculumTreeFilter']['value'],
                'inTemplate'    => in_array(strtolower(trim($instanceConfig['componentname']['value'])), $curriculumKey),
            ];
        }

        // Activity routes
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

        // Unit-level routes
        $folderParams             = ['folderId' => $unit->getFolder()->getId(), 'folder' => $unit->getFolder()];
        $hasUnitFolderWriteAccess = $this->access->getFolderAccess($this->getUser(), $unit->getFolder()->getId()) >= UserAccess::ACCESS_WRITE;
        $rubricRoutes = [
            'manageUnit' => $this->getRouteIfAuthorized('rubricManager.getForm', $folderParams) ?: null,
            'manageCourse' => $this->getRouteIfAuthorized('rubricManager.getForm', ['folderId' => $unit->getCourse()->getFolder()->getId(), 'folder' => $unit->getCourse()->getFolder()]) ?: null,
        ];
        $textboxRoutes = [
            'createComponent' => ($textboxDefId && $hasUnitFolderWriteAccess) ? '/homepage/configForm.php' : null,
            'createTextbox'   => $this->getRouteIfAuthorized('textbox.createTextboxInFolder', $folderParams) ?: null,
        ];
        $curriculum['_routes'] = [
            'createComponent'  => ($curriculumDefId && $hasUnitFolderWriteAccess) ? '/homepage/configForm.php' : null,
            'modifyCurriculum' => $this->getRouteIfAuthorized('curriculumApiUsageFolder.postFolderUsage', $folderParams) ?: null,
        ];


        /* Aggregated data for each unit appears as a row in the Unit Planner
         * on the front end - this is basically the contract between back- and
         * front-end implementations and the basis of an api-first philosophy
         */
        return [
            'id' => $unit->getId(),
            'title' => $unit->getTitle(),
            'homepage' => "/homepage/{$unit->getFolder()->getId()}",
            'folderId' => $unit->getFolder()->getId(),
            /* Textboxes in the Unit that circumstantially match ones in the Template
             */
            'textboxes' => [
                '_routes' => $textboxRoutes,
                'items'   => $textboxData['textboxes'],
            ],
            /* All the other random textboxes that don't match
             */
            'extraTextboxes' => $textboxData['extraTextboxes'],
            /* All the assessment specs in this unit
             */
            'activities' => [
                '_routes' => $activitiesListRoutes,
                'items'   => $assessments->map(new UnitAssessments(
                    $this->routeGenerator,
                    $this->getAuthorizer(),
                    $this->getUser(),
                    $this->getUser()
                )),
            ],
            /* All the rubric capabilities and indicators that are in use among all
             * the assessment specs in this unit
             */
            'rubricCapabilities' => [
                '_routes' => $rubricRoutes,
                'usedIndicators'   => $usedRubricIndicators,
                'usedCapabilities'   => $usedRubricCapabilities,
                'availableRubricCapabilities' => $availableRubricCapabilities,
            ],
            /* Curriculum data object, containing selected, and filtered curriculum nodes
             */
            'curriculum' => $curriculum,
            'sequence' => $unit->getFolder()->getSequence(),
        ];
    }

    private function getTemplateCurric(int $courseTemplateId)
    {
        /**
         * Grab the list of curriculum components from the Unit Template
         */
        $module = \Component_Definition::MODULE_HOMEPAGE_TEMPLATE;
        $class = CurriculumOverviewController::class;
        $curriculumTemplate = [];
        $curriculumKey = [];
        $unitCurricComp = $this->componentInstanceMapper->findByContextAndClass($module, $courseTemplateId, $class);

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
            /**
             * the default value is '0' if we add a node and remove it it becomes '' and legacy components don't have the config so will be null
             */
            if ('0' === $curricFilter || '' === $curricFilter || null === $curricFilter) {
                $curricFilter = null;
            } else {
                $curricFilter = $this->nodeMapper->findOneByColumnList(['id' => $curricFilter, 'enabled' => true,]);
            }
            $curriculumTemplate[] = [
                'heading' => $heading,
                'filter' => $curricFilter,
            ];
            $curriculumKey[] = strtolower($heading);
        }
        return [$curriculumTemplate, $curriculumKey];
    }


    /**
     * @param int $courseFolderId
     * @param int $unitFolderId
     * @return array|array[]
     * @throws \Schoolbox\Mapper\Exception
     */
    private function getCourseUnitRubric($courseFolderId, $unitFolderId)
    {
        // grab rubric info
        $rubricsModels = $this->rubricIndicatorMapper->findForFolder([$courseFolderId, $unitFolderId]);
        //-------------
        $rubrics = [
            'capabilities' => [],
            /* Cross-referencing for fetching curriculum nodes separately
             */
            'capabilityIds' => [],
            'indicatorIds' => [],
        ];
        /* Accumulate a unique set of capabilities and indicators
         *
         * Note that capabilities/indicators can be markdown stuff
         */
        foreach ($rubricsModels as $indicator) {
            /** @var RubricIndicatorModel $indicator */
            $capabilityId = $indicator->getCapability()->getId();
            $indicatorId = $indicator->getId();

            /* Cross-referencing for fetching curriculum nodes separately
             */
            $rubrics['capabilityIds'][$capabilityId] = $capabilityId;
            $rubrics['indicatorIds'][$indicatorId] = $indicatorId;

            /* Initialise it, if it hasn't already existed
             */
            if (!isset($rubrics['capabilities'][$capabilityId])) {
                $rubrics['capabilities'][$capabilityId] = [
                    'id' => $capabilityId,
                    'name' => Scrub::markdown($indicator->getCapability()->getName()),
                    'course' => ($indicator->getCapability()->getParentId() === $courseFolderId),
                    'indicators' => [],
                ];
            }

            $rubrics['capabilities'][$capabilityId]['indicators'][$indicatorId] = [
                'id'    => $indicator->getId(),
                'name'  => Scrub::markdown($indicator->getName()),
            ];
        }

        // normalize and sort the capabilities and indicators
        $rubrics['capabilities'] = array_values($rubrics['capabilities']);

        // sort the rubrics, to the same structure / format as the other spaces in the UI
        usort($rubrics['capabilities'], static function ($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });

        // sort and normalize indicators
        foreach ($rubrics['capabilities'] as $key => $value) {
            $tmp = array_values($value['indicators']);
            usort($tmp, static function ($a, $b) {
                return strnatcasecmp($a['name'], $b['name']);
            });
            $rubrics['capabilities'][$key]['indicators'] = $tmp;
        }

        return $rubrics;
    }
}
