<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Import;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Alaress_Date_Format;
use Schoolbox\Database\Factory;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\User;
use UnexpectedValueException;
use Schoolbox\Assessment\Rubric\DataModel\Indicator\Mapper as IndicatorMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Component\Homepage\Outline\Controller as HomepageOutlineComponentController;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkAssessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizAssessment;
use Schoolbox\Learning\Assessment\LessonPlan\Model as LessonPlan;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Assessment\Task\Model as TaskAssessment;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;

/**
 * Direct DB access to generate a data structure of UnitSpecs, UnitImpls, AssessmentSpecs + AssessmentImpls.
 *
 * Depending on which method is called, results will differ, however the data structure MUST BE the same
 * for all methods.
 *
 * All methods must return an object of the same type
 */
class ImportFactory
{
    /** @var IConnector */
    private IConnector $database;

    /**
     * All known Assessables, indexed by assessment.id
     *
     * @var GenericCollection|IAssessable[] $assessmentCache
     */
    private $assessmentCache;

    private MapperProxy $assessmentMapperProxy;

    private Replicator $replicator;

    private CourseImplementationMapper $courseImplementationMapper;

    private UnitImplementationMapper $unitImplementationMapper;

    private \Component_InstanceMapper $componentInstanceMapper;

    private \Component_ConfigDefinitionMapper $componentConfigDefinitionMapper;

    private \Component_ConfigMapper $componentConfigInstanceMapper;

    /**
     * ImportFactory constructor.
     */
    public function __construct(
        MapperProxy $assessmentMapperProxy,
        Replicator $replicator,
        CourseImplementationMapper $courseImplementationMapper,
        UnitImplementationMapper $unitImplementationMapper,
        \Component_InstanceMapper $componentInstanceMapper,
        \Component_ConfigDefinitionMapper $componentConfigDefinitionMapper,
        \Component_ConfigMapper $componentConfigInstanceMapper
    ) {
        $this->assessmentMapperProxy           = $assessmentMapperProxy;
        $this->replicator                      = $replicator;
        $this->courseImplementationMapper      = $courseImplementationMapper;
        $this->unitImplementationMapper        = $unitImplementationMapper;
        $this->componentInstanceMapper         = $componentInstanceMapper;
        $this->componentConfigDefinitionMapper = $componentConfigDefinitionMapper;
        $this->componentConfigInstanceMapper   = $componentConfigInstanceMapper;
        $this->database                        = Factory::getInstanceInternal();
        $this->assessmentCache                 = new GenericCollection(IAssessable::class);
    }

    /**
     * Called during a new import.
     *
     * UnitImpls will all have no id, but will have dates already set.
     * AssessmentImpls will have no id, but will have dates populated according to previous stage
     * All AssessmentImpls will link to an AssessmentSpec
     *
     * @param UnitImplementation[]|GenericCollection $unitImpls      Each UnitImpl links to a UnitSpec to get Assessments for
     * @param string|null                            $defaultDueTime Adjust due date time to this, if set in H:i format
     * @return DataTransferObject
     * @throws InvalidDataStateException If broken data encountered
     */
    public function buildForNewImport(GenericCollection $unitImpls, ?string $defaultDueTime = null)
    {
        /**
         * Extract all UnitSpecs into a cache, indexed by unitSpec.id
         * @var UnitSpecification[] $unitSpecCache
         */
        $unitSpecCache   = [];
        $assessmentSpecs = new GenericCollection(GenericCollection::class);
        $assessmentImpls = new GenericCollection(GenericCollection::class);
        $assessmentPairs = new GenericCollection(GenericCollection::class);

        // Instantiate empty sets for each UnitSpecId
        foreach ($unitImpls as $unitImpl) {
            $courseSpecId                 = $unitImpl->getSpecification()->getId();
            $unitSpecCache[$courseSpecId] = $unitImpl->getSpecification();
            $assessmentSpecs->setOffset($courseSpecId, new GenericCollection(IAssessable::class));
            $assessmentImpls->setOffset($courseSpecId, new GenericCollection(IAssessable::class));
            $assessmentPairs->setOffset($courseSpecId, new GenericCollection(AssessmentPair::class));
        }


        /**
         * Maintains a list of UnitSpecs encountered while traversing DB query result set.
         * Required so we can set the UnitSpec name once only.
         * @var UnitSpecification[] $unitSpecCache
         */
        $unitSpecDBCache = [];

        $query = "
            SELECT
                us.id                        AS 'us.id',
                usf.name                     AS 'usf.name',
                sa.id                        AS 'sa.id',
                sa.title                     AS 'sa.title',
                sa.default_open_span         AS 'sa.default_open_span',
                sa.sequence                  AS 'sa.sequence',
                sa.work_type_id              AS 'sa.work_type_id',
                sa.deleted_at                AS 'sa.deleted_at',
                lwt.name                     AS 'lwt.name',
                sdw.assessment_id            AS 'sdw.id',
                sdw.accept_submission        AS 'sdw.accept_submission',
                sdw.multi_submission         AS 'sdw.multi_submission',
                sqz.assessment_id            AS 'sqz.id',
                sqz.learnosity_reference     AS 'sqz.learnosity_reference',
                sqz.learnosity_job_reference AS 'sqz.learnosity_job_reference',
                sqz.learnosity_job_status    AS 'sqz.learnosity_job_status',
                stk.assessment_id            AS 'stk.id',
                spr.assessment_id            AS 'spr.id',
                slt.assessment_id            AS 'slt.id',
                specLessonPlan.assessment_id AS 'specLessonPlan.id'
            FROM course_unit_specification us
            LEFT JOIN folder usf ON usf.id = us.folder_id
            LEFT JOIN assessment_course_unit_specification aus ON aus.course_unit_specification_id = us.id
            LEFT JOIN assessment sa ON sa.id = aus.assessment_id
            LEFT JOIN lu_work_type lwt ON lwt.id = sa.work_type_id
            LEFT JOIN submission_box sdw ON sdw.assessment_id = sa.id
            LEFT JOIN assess sqz ON sqz.assessment_id = sa.id
            LEFT JOIN task stk ON stk.assessment_id = sa.id
            LEFT JOIN project spr ON spr.assessment_id = sa.id
            LEFT JOIN lti_assessment slt ON slt.assessment_id = sa.id
            LEFT JOIN lesson_plan specLessonPlan ON specLessonPlan.assessment_id = sa.id
        ";

        $pb = new Builder($this->database->getQuoter(), '');
        $pb->in('us.id', array_keys($unitSpecCache));
        $pb->isNull('sa.deleted_at');
        $query     .= 'WHERE' . implode(' AND ', $pb->getWhere());
        $statement = $this->database->prepare($query);
        $statement->execute($pb->getParams());


        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $unitSpecId = $row['us.id'];

            // Is this the first time this UnitSpec has been seen?
            if (!isset($unitSpecDBCache[$unitSpecId]) || !($unitSpecDBCache[$unitSpecId] instanceof UnitSpecification)) {
                // Add a Folder and name it so we can later render out this UnitSpec's name.
                $unitSpecDBCache[$unitSpecId] = $unitSpecCache[$unitSpecId];
                $folder                       = new Folder();
                $folder->setName($row['usf.name']);
                $unitSpecCache[$unitSpecId]->setFolder($folder);
            }

            // It's quite valid for this to return NULL. This will occur if there are no assessments in the unit-spec.
            $assessmentSpec = $this->buildAssessmentSpecFromRow($row);

            if ($assessmentSpec instanceof IAssessable) {
                $assessmentSpecs->getOffset($unitSpecId)->setOffset($assessmentSpec->getId(), $assessmentSpec);
            }
        }

        // Populating folder names which are missed in the steps above and not in unitSpecDBCache.
        $emptyUnitSpecIds = array_diff(array_keys($unitSpecCache), array_keys($unitSpecDBCache));
        if (!empty($emptyUnitSpecIds)) {
            $this->loadSpecFolders($emptyUnitSpecIds, $unitSpecCache);
        }

        // No AssessmentImpl to AssessmentSpec mapping (this is a new import)
        $assessmentSpecs = $this->loadProjectSpecAssessables($assessmentSpecs, []);
        $dto             = new DataTransferObject(false, [], $unitImpls, $assessmentSpecs, $assessmentImpls, $assessmentPairs, $defaultDueTime);

        return $dto;
    }


    /**
     * Called during a modification of prior import
     *
     * UnitImpl IDs will be null if not yet imported from UnitSpec.
     * UnitImpls will link to extant UnitSpecs
     *
     * @param UnitImplementation[]|GenericCollection $unitImpls
     * @param string|null                            $defaultDueTime Adjust due date time to this, if set in H:i format
     * @return DataTransferObject
     * @throws InvalidDataStateException If broken course data detected
     */
    public function buildForImportModify(GenericCollection $unitImpls, ?string $defaultDueTime)
    {
        /**
         * Not imported to the specified $unitImpls
         *
         * @var GenericCollection|GenericCollection[]|IAssessable[][] $assessmentSpecs
         */
        $assessmentSpecs = new GenericCollection(GenericCollection::class);

        /**
         * Created inside the specified $unitImpls (not imported from Spec)
         */
        $assessmentImpls = new GenericCollection(GenericCollection::class);


        /**
         * Imported from a Spec to the specified $unitImpls
         */
        $assessmentPairs = new GenericCollection(GenericCollection::class);

        /**
         * Extract all UnitSpecs into a cache, indexed by unitSpec.id, so we can use their IDs in queries
         * @var UnitSpecification[] $unitSpecCache
         */
        $unitSpecCache = [];

        /**
         * Maintains a list of UnitSpecs encountered while traversing DB query result set.
         * Required so we can populate the UnitSpec name once only.
         * @var UnitSpecification[] $unitSpecCache
         */
        $unitSpecDBCache = [];

        /**
         * IDs of existing UnitImpls. Indexed by UnitSpecId
         *
         * @var int[] $unitImplIds
         */
        $unitImplIds = [];

        // Instantiate empty sets for each UnitSpecId
        foreach ($unitImpls as $unitImpl) {
            $unitSpecId                 = $unitImpl->getSpecification()->getId();
            $unitSpecCache[$unitSpecId] = $unitImpl->getSpecification();
            $assessmentSpecs->setOffset($unitSpecId, new GenericCollection(IAssessable::class));
            $assessmentImpls->setOffset($unitSpecId, new GenericCollection(IAssessable::class));
            $assessmentPairs->setOffset($unitSpecId, new GenericCollection(AssessmentPair::class));

            // Is this UnitImpl already imported?
            if ($unitImpl->getId()) {
                $unitImplIds[$unitSpecId] = $unitImpl->getId();
            }
        }

        // Get the IDs of all non-deleted AssessmentImpls in the specified UnitImpls
        $innerQuery1 = "
            SELECT
              asi.specification_assessment_id AS 'sa.id',
              asi.implementation_assessment_id AS 'ia.id'
            FROM course_unit_implementation `ui_inner`
            JOIN assessment_course_unit_implementation aui ON aui.course_unit_implementation_id = `ui_inner`.id
            JOIN assessment ia ON ia.id = aui.assessment_id
            JOIN assessment_specification_implementation asi ON asi.implementation_assessment_id = ia.id
        ";

        $pbInner1 = new Builder($this->database->getQuoter(), '');
        $pbInner1->in('ui_inner.id', $unitImplIds);
        $pbInner1->isNull('ia.deleted_at');
        $innerQuery1 .= 'WHERE ' . implode(' AND ', $pbInner1->getWhere());

        // AssessmentSpecs in UnitSpecs, optionally with corresponding AssessmentImpl if it exists
        $query1   = "
            SELECT
                us.id                 AS 'us.id',
                usf.name              AS 'usf.name',
                sa.id                 AS 'sa.id',
                sa.title              AS 'sa.title',
                sa.deleted_at         AS 'sa.deleted_at',
                sa.sequence           AS 'sa.sequence',
                sa.work_type_id       AS 'sa.work_type_id',
                lwt.name			  AS 'lwt.name',
                sa.default_open_span  AS 'sa.default_open_span',
                sdw.assessment_id     AS 'sdw.id',
                sdw.accept_submission AS 'sdw.accept_submission',
                sdw.multi_submission  AS 'sdw.multi_submission',
                sqz.assessment_id     AS 'sqz.id',
                sqz.learnosity_reference     AS 'sqz.learnosity_reference',
                sqz.learnosity_job_reference AS 'sqz.learnosity_job_reference',
                sqz.learnosity_job_status    AS 'sqz.learnosity_job_status',
                stk.assessment_id     AS 'stk.id',
                spr.assessment_id     AS 'spr.id',
                slt.assessment_id     AS 'slt.id',
                specLessonPlan.assessment_id AS 'specLessonPlan.id',
                ia.id                 AS 'ia.id',
                ia.title              AS 'ia.title',
                ia.publish_date       AS 'ia.publish_date',
                ia.open_date          AS 'ia.open_date',
                ia.due_date           AS 'ia.due_date',
                ia.close_date         AS 'ia.close_date',
                ia.sequence           AS 'ia.sequence',
                ia.work_type_id       AS 'ia.work_type_id',
                ia.deleted_at         AS 'ia.deleted_at',
                idw.assessment_id     AS 'idw.id',
                idw.accept_submission AS 'idw.accept_submission',
                idw.multi_submission  AS 'idw.multi_submission',
                iqz.assessment_id     AS 'iqz.id',
                itk.assessment_id     AS 'itk.id',
                ipr.assessment_id     AS 'ipr.id',
                ilt.assessment_id     AS 'ilt.id',
                implLessonPlan.assessment_id AS 'implLessonPlan.id'
            FROM course_unit_specification us
            JOIN folder usf                               ON usf.id = us.folder_id
            JOIN assessment_course_unit_specification aus ON aus.course_unit_specification_id = us.id
            JOIN assessment sa                            ON sa.id = aus.assessment_id
            LEFT JOIN lu_work_type lwt ON lwt.id = sa.work_type_id
            LEFT JOIN submission_box sdw                  ON sdw.assessment_id = sa.id
            LEFT JOIN assess sqz                          ON sqz.assessment_id = sa.id
            LEFT JOIN task stk                            ON stk.assessment_id = sa.id
            LEFT JOIN project spr                         ON spr.assessment_id = sa.id
            LEFT JOIN lti_assessment slt                  ON slt.assessment_id = sa.id
            LEFT JOIN lesson_plan specLessonPlan          ON specLessonPlan.assessment_id = sa.id
            LEFT JOIN ($innerQuery1) `t1`                 ON `t1`.`sa.id` = sa.id
            LEFT JOIN assessment ia                       ON `t1`.`ia.id` = ia.id
            LEFT JOIN submission_box idw                  ON idw.assessment_id = ia.id
            LEFT JOIN assess iqz                          ON iqz.assessment_id = ia.id
            LEFT JOIN task itk                            ON itk.assessment_id = ia.id
            LEFT JOIN project ipr                         ON ipr.assessment_id = ia.id
            LEFT JOIN lti_assessment ilt                  ON ilt.assessment_id = ia.id
            LEFT JOIN lesson_plan implLessonPlan          ON implLessonPlan.assessment_id = ia.id
        ";
        $pbOuter1 = new Builder($this->database->getQuoter(), '');
        $pbOuter1->in('us.id', array_keys($unitSpecCache));
        $query1 .= ' WHERE ' . implode(' AND ', $pbOuter1->getWhere());

        // Get the following Assessments:
        // 5. AssessmentImpls created directly in UnitImpl, no corresponding AssessmentSpec
        $query2   = "
            SELECT
                us.id             AS 'us.id',
                usf.name          AS 'usf.name',
                null              AS 'sa.id',
                null              AS 'sa.title',
                null              AS 'sa.deleted_at',
                null              AS 'sa.sequence',
                null              AS 'sa.work_type_id',
                null			  AS 'lwt.name',
                null              AS 'sa.default_open_span',
                null              AS 'sdw.id',
                null              AS 'sdw.accept_submission',
                null              AS 'sdw.multi_submission',
                null              AS 'sqz.id',
                null              AS 'sqz.learnosity_reference',
                null              AS 'sqz.learnosity_job_reference',
                null              AS 'sqz.learnosity_job_status',
                null              AS 'stk.id',
                null              AS 'spr.id',
                null              AS 'slt.id',
                null              AS 'specLessonPlan.id',
                ia.id             AS 'ia.id',
                ia.title          AS 'ia.title',
                ia.publish_date   AS 'ia.publish_date',
                ia.open_date      AS 'ia.open_date',
                ia.due_date       AS 'ia.due_date',
                ia.close_date     AS 'ia.close_date',
                ia.sequence       AS 'ia.sequence',
                ia.work_type_id   AS 'ia.work_type_id',
                ia.deleted_at     AS 'ia.deleted_at',
                idw.assessment_id AS 'idw.id',
                idw.accept_submission AS 'idw.accept_submission',
                idw.multi_submission  AS 'idw.multi_submission',
                iqz.assessment_id AS 'iqz.id',
                itk.assessment_id AS 'itk.id',
                ipr.assessment_id AS 'ipr.id',
                ilt.assessment_id AS 'ilt.id',
                implLessonPlan.assessment_id AS 'implLessonPlan.id'
            FROM course_unit_implementation ui
            JOIN course_unit_specification us ON us.id = ui.course_unit_specification_id
            JOIN folder usf ON usf.id = us.folder_id
            JOIN assessment_course_unit_implementation aui ON aui.course_unit_implementation_id = ui.id
            JOIN assessment ia ON ia.id = aui.assessment_id
            LEFT JOIN lu_work_type lwt ON lwt.id = ia.work_type_id
            LEFT JOIN submission_box idw ON idw.assessment_id = ia.id
            LEFT JOIN assess iqz ON iqz.assessment_id = ia.id
            LEFT JOIN task itk ON itk.assessment_id = ia.id
            LEFT JOIN project ipr ON ipr.assessment_id = ia.id
            LEFT JOIN lti_assessment ilt ON ilt.assessment_id = ia.id
            LEFT JOIN lesson_plan implLessonPlan ON implLessonPlan.assessment_id = ia.id
            LEFT JOIN assessment_specification_implementation asi ON asi.implementation_assessment_id = ia.id
        ";
        $pbOuter2 = new Builder($this->database->getQuoter(), '');
        $pbOuter2->isNull('ia.deleted_at');
        $pbOuter2->isNull('asi.implementation_assessment_id');
        $pbOuter2->in('ui.id', $unitImplIds);
        $query2 .= ' WHERE ' . implode(' AND ', $pbOuter2->getWhere());

        $query = "$query1 UNION $query2";

        $statement = $this->database->prepare($query);
        $statement->execute(array_merge($pbInner1->getParams(), $pbOuter1->getParams(), $pbOuter2->getParams()));
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $unitSpecId = $row['us.id'];
            // Is this the first time this UnitSpec has been seen?
            if (!isset($unitSpecDBCache[$unitSpecId]) || !($unitSpecDBCache[$unitSpecId] instanceof UnitSpecification)) {
                // Add a Folder and name it so we can later render out this UnitSpec's name.
                $unitSpecDBCache[$unitSpecId] = $unitSpecCache[$unitSpecId];
                $folder                       = new Folder();
                $folder->setName($row['usf.name']);
                $unitSpecCache[$unitSpecId]->setFolder($folder);
            }

            // Create AssessmentSpec (if one exists). This may be valid null. This will occur, eg, if assessment was created in class.
            $assessmentSpec = $this->buildAssessmentSpecFromRow($row);

            // Create corresponding AssessmentImpl (if one exists). This may be valid null, eg if assessment has not been imported.
            $assessmentImpl = $this->buildAssessmentImplFromRow($row);

            if ($assessmentSpec instanceof IAssessable && $assessmentImpl instanceof IAssessable) {
                $assessmentImpl->setSpecificationAssessment($assessmentSpec);
                $assessmentPairs->getOffset($unitSpecId)->add(new AssessmentPair($assessmentSpec, $assessmentImpl));
            } else {
                if ($assessmentSpec instanceof IAssessable) {
                    // Don't add deleted AssessmentSpecs
                    if (!$assessmentSpec->isDeleted()) {
                        $assessmentSpecs->getOffset($unitSpecId)->setOffset($assessmentSpec->getId(), $assessmentSpec);
                    }
                } else {
                    if ($assessmentImpl instanceof IAssessable) {
                        $assessmentImpls->getOffset($unitSpecId)->setOffset($assessmentImpl->getId(), $assessmentImpl);
                    } else {
                        // If unable to build a pair, spec-only, or impl-only, something has gone wrong!
                        throw new InvalidDataStateException("Unable to build any assessments. spec.id: {$row['sa.id']}, impl.id: {$row['ia.id']}");
                    }
                }
            }
        }

        // Populating folder names which are missed in the steps above.
        $emptyUnitSpecIds = array_diff(array_keys($unitSpecCache), array_keys($unitSpecDBCache));
        if (!empty($emptyUnitSpecIds)) {
            $this->loadSpecFolders($emptyUnitSpecIds, $unitSpecCache);
        }

        $assessmentImplToSpecMap = $this->getAssessmentImplToSpecMap($unitImplIds);
        $assessmentSpecs         = $this->loadProjectSpecAssessables($assessmentSpecs, $assessmentImplToSpecMap);
        $assessmentImpls         = $this->loadProjectImplAssessables($assessmentImpls, $assessmentImplToSpecMap);
        $assessmentPairs         = $this->loadProjectPairAssessables($assessmentPairs, $assessmentImplToSpecMap);
        $dto                     = new DataTransferObject(true, $assessmentImplToSpecMap, $unitImpls, $assessmentSpecs, $assessmentImpls, $assessmentPairs, $defaultDueTime);
        return $dto;
    }


    /**
     * Load all Assessments inside Project Specs
     *
     * Normally we are un-interested in deleted specs. The exception is when we are adding as part of a pair.
     *
     * @param GenericCollection|GenericCollection[] $assessmentSpecs - Sets of AssessmentSpecs, indexed by UnitSpec.id
     * @param int[]                                 $assessmentImplToSpecMap
     * @return GenericCollection
     * @throws InvalidDataStateException If broken course data encountered
     */
    protected function loadProjectSpecAssessables(GenericCollection $assessmentSpecs, array $assessmentImplToSpecMap)
    {
        // Put the specIds in the keys for O(1) lookup. Strip out values that equate to false (eg NULL)
        $specToImplMap = array_flip(array_filter($assessmentImplToSpecMap));

        /** @var GenericCollection|Project[] $projectSpecs */
        $projectSpecs = new GenericCollection(Project::class);
        foreach ($assessmentSpecs as $unitSpecId => $unitAssessmentSpecs) {
            foreach ($unitAssessmentSpecs as $specId => $assessmentSpec) {
                if ($assessmentSpec instanceof Project) {
                    $projectSpecs->setOffset($specId, $assessmentSpec);
                }
            }
        }
        if (!$projectSpecs->count()) {
            return $assessmentSpecs;
        }
        $query = "
           SELECT
                p.assessment_id       AS 'p.assessment_id',
                sa.id                 AS 'sa.id',
                sa.title              AS 'sa.title',
                sa.deleted_at         AS 'sa.deleted_at',
                sa.sequence           AS 'sa.sequence',
                sa.work_type_id       AS 'sa.work_type_id',
                lwt.name              AS 'lwt.name',
                sa.default_open_span  AS 'sa.default_open_span',
                sdw.assessment_id     AS 'sdw.id',
                sdw.accept_submission AS 'sdw.accept_submission',
                sdw.multi_submission  AS 'sdw.multi_submission',
                sa.id                 AS 'spr.id',
                sqz.assessment_id     AS 'sqz.id',
                sqz.learnosity_reference     AS 'sqz.learnosity_reference',
                sqz.learnosity_job_reference AS 'sqz.learnosity_job_reference',
                sqz.learnosity_job_status    AS 'sqz.learnosity_job_status',
                stk.assessment_id     AS 'stk.id',
                slt.assessment_id     AS 'slt.id',
                specLessonPlan.assessment_id AS 'specLessonPlan.id'
            FROM project p
            JOIN project_assessment pa   ON pa.project_id = p.id
            JOIN assessment sa           ON sa.id = pa.assessment_id
            LEFT JOIN lu_work_type lwt ON lwt.id = sa.work_type_id
            LEFT JOIN assessment_course_unit_implementation pui ON pui.assessment_id = p.assessment_id
            LEFT JOIN submission_box sdw ON sdw.assessment_id = sa.id
            LEFT JOIN assess sqz         ON sqz.assessment_id = sa.id
            LEFT JOIN task stk           ON stk.assessment_id = sa.id
            LEFT JOIN lti_assessment slt ON slt.assessment_id = sa.id
            LEFT JOIN lesson_plan specLessonPlan ON specLessonPlan.assessment_id = sa.id
        ";

        $pb    = new Builder($this->database->getQuoter(), '');
        $pb->in('p.assessment_id', $projectSpecs->keys());
        $query .= ' WHERE' . implode(' AND ', $pb->getWhere());

        $statement = $this->database->prepare($query);
        $statement->execute($pb->getParams());

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $projectAssessmentId = $row['p.assessment_id'];
            $assessmentId        = $row['sa.id'];
            $deletedAt           = $row['sa.deleted_at'];

            // Only add deleted specs if they are linked to from a current Impl
            if (is_null($deletedAt) || array_key_exists($assessmentId, $specToImplMap)) {
                // This call should never return NULL - see above SQL query
                $assessmentSpec = $this->buildAssessmentSpecFromRow($row);
                if (!$assessmentSpec instanceof IAssessable) {
                    throw new InvalidDataStateException("Unable to build spec assessment. id: {$row['sa.id']}");
                }
                /** @var Project $projectSpec */
                $projectSpec = $projectSpecs->getOffset($projectAssessmentId);
                $projectSpec->getAssessables()->setOffset($assessmentSpec->getId(), $assessmentSpec);
                $assessmentSpec->setContext($projectSpec);
            }
        }

        return $assessmentSpecs;
    }


    /**
     * Load all Assessments inside Project Impls, and links the AssessmentImpls to AssessmentSpecs
     *
     * @param GenericCollection $assessmentImpls - Sets of AssessmentImpls, indexed by UnitSpec.id
     * @param int[]             $assessmentImplToSpecMap
     * @return GenericCollection
     * @throws InvalidDataStateException If broken course data encountered
     */
    protected function loadProjectImplAssessables(GenericCollection $assessmentImpls, array $assessmentImplToSpecMap)
    {
        /**
         * Indexed by Assessment.id
         *
         * @var GenericCollection|Project[] $projectImpls
         */
        $projectImpls = new GenericCollection(Project::class);
        foreach ($assessmentImpls as $unitSpecId => $unitAssessmentImpls) {
            foreach ($unitAssessmentImpls as $implId => $assessmentImpl) {
                if ($assessmentImpl instanceof Project) {
                    $projectImpls->setOffset($implId, $assessmentImpl);
                }
            }
        }
        if (!$projectImpls->count()) {
            return $assessmentImpls;
        }
        $query = "
           SELECT
                p.assessment_id       AS 'p.assessment_id',
                asi.specification_assessment_id AS 'asi.specification_assessment_id',
                ia.id                 AS 'ia.id',
                ia.title              AS 'ia.title',
                ia.sequence           AS 'ia.sequence',
                ia.work_type_id       AS 'ia.work_type_id',
                lwt.name			  AS 'lwt.name',
                ia.publish_date       AS 'ia.publish_date',
                ia.open_date          AS 'ia.open_date',
                ia.due_date           AS 'ia.due_date',
                ia.close_date         AS 'ia.close_date',
                ia.deleted_at         AS 'ia.deleted_at',
                idw.assessment_id     AS 'idw.id',
                idw.accept_submission AS 'idw.accept_submission',
                idw.multi_submission  AS 'idw.multi_submission',
                iqz.assessment_id     AS 'iqz.id',
                itk.assessment_id     AS 'itk.id',
                ilt.assessment_id     AS 'ilt.id',
                implLessonPlan.assessment_id AS 'implLessonPlan.id'
            FROM project p
            JOIN project_assessment pa   ON pa.project_id = p.id
            JOIN assessment ia           ON ia.id = pa.assessment_id
            LEFT JOIN lu_work_type lwt ON lwt.id = ia.work_type_id
            LEFT JOIN assessment_specification_implementation asi ON asi.implementation_assessment_id = ia.id
            LEFT JOIN submission_box idw ON idw.assessment_id = ia.id
            LEFT JOIN assess iqz         ON iqz.assessment_id = ia.id
            LEFT JOIN task itk           ON itk.assessment_id = ia.id
            LEFT JOIN lti_assessment ilt ON ilt.assessment_id = ia.id
            LEFT JOIN lesson_plan implLessonPlan ON implLessonPlan.assessment_id = ia.id
        ";

        $pb    = new Builder($this->database->getQuoter(), '');
        $pb->in('p.assessment_id', $projectImpls->keys());
        $pb->isNull('ia.deleted_at');
        $query .= ' WHERE' . implode(' AND ', $pb->getWhere());

        $statement = $this->database->prepare($query);
        $statement->execute($pb->getParams());


        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            // Add AssessmentImpl to ProjectImpl
            $projectAssessmentId = $row['p.assessment_id'];
            // This call should never return NULL - see SQL query above
            $assessmentImpl = $this->buildAssessmentImplFromRow($row);
            if (!$assessmentImpl instanceof IAssessable) {
                throw new InvalidDataStateException("Unable to build project implementation sub-assessment. id: {$row['ia.id']}");
            }
            /** @var Project $projectImpl */
            $projectImpl = $projectImpls->getOffset($projectAssessmentId);
            $projectImpl->getAssessables()->setOffset($assessmentImpl->getId(), $assessmentImpl);
            $assessmentImpl->setContext($projectImpl);

            // Link AssessmentImpl to AssessmentSpec
            $assessmentSpecId = $row['asi.specification_assessment_id'];
            if ($assessmentSpecId) {
                $assessmentSpec = $this->assessmentCache->getOffset($assessmentImplToSpecMap[$assessmentImpl->getId()]);
                $assessmentImpl->setSpecificationAssessment($assessmentSpec);
            }
        }

        return $assessmentImpls;
    }

    /**
     * Load all Assessment Specs + Impls for Projects that have been imported
     *
     * @param GenericCollection|AssessmentPair[][] $assessmentPairs - Sets of AssessmentPairs, indexed by UnitSpec.id
     * @param int[]                                $assessmentImplToSpecMap
     * @return GenericCollection
     */
    protected function loadProjectPairAssessables(GenericCollection $assessmentPairs, array $assessmentImplToSpecMap)
    {
        /**
         * Sets of Project Specs, grouped by UnitSpec.id
         */
        $unitProjectSpecs = new GenericCollection(GenericCollection::class);

        /**
         * Sets of Project Impls, grouped by UnitSpec.id
         */
        $unitProjectImpls = new GenericCollection(GenericCollection::class);

        /**
         * Indexed by assessment.id
         */
        $projectSpecs = new GenericCollection(Project::class);

        /**
         * Indexed by assessment.id
         */
        $projectImpls = new GenericCollection(Project::class);


        foreach ($assessmentPairs as $unitSpecId => $unitAssessmentPairs) {
            $unitProjectSpecs->setOffset($unitSpecId, new GenericCollection(Project::class));
            $unitProjectImpls->setOffset($unitSpecId, new GenericCollection(Project::class));

            foreach ($unitAssessmentPairs as $unitAssessmentPair) {
                $spec = $unitAssessmentPair->getSpecification();
                $impl = $unitAssessmentPair->getImplementation();
                if ($spec instanceof Project) {
                    $unitProjectSpecs->getOffset($unitSpecId)->setOffset($spec->getId(), $spec);
                    $projectSpecs->setOffset($spec->getId(), $spec);
                }
                if ($impl instanceof Project) {
                    $unitProjectImpls->getOffset($unitSpecId)->setOffset($impl->getId(), $impl);
                    $projectImpls->setOffset($impl->getId(), $impl);
                }
            }
        }
        if (!$projectSpecs->count()) {
            return $assessmentPairs;
        }

        // Build the AssessmentSpecs inside the ProjectSpecs
        $this->loadProjectSpecAssessables($unitProjectSpecs, $assessmentImplToSpecMap);

        // Build the AssessmentImpls inside the ProjectImpls
        $this->loadProjectImplAssessables($unitProjectImpls, $assessmentImplToSpecMap);

        return $assessmentPairs;
    }


    /**
     * Get a map of AssessmentImplIds to AssessmentSpecIds for the specified UnitImpls.
     *
     * AssessmentSpecId will be null if AssessmentImpl was created directly in a UnitImpl
     *
     * Includes Assessments in:
     * * UnitImpls, and
     * * Projects inside UnitImpls
     *
     * DOES include records for deleted Specs.
     * Does NOT include records for deleted Impls.
     *
     * @param int[] $unitImplIds
     * @return int[]
     */
    protected function getAssessmentImplToSpecMap($unitImplIds)
    {
        // Assessments directly in UnitImpls
        $query1 = "
            SELECT
                ia.id AS 'ia.id',
                sa.id AS 'sa.id'
            FROM assessment_course_unit_implementation aui
            JOIN assessment ia ON ia.id = aui.assessment_id AND ia.deleted_at IS NULL
            LEFT JOIN assessment_specification_implementation asi ON asi.implementation_assessment_id = ia.id
            LEFT JOIN assessment sa ON sa.id = asi.specification_assessment_id
        ";
        $pb1    = new Builder($this->database->getQuoter(), '');
        $pb1->in('aui.course_unit_implementation_id', $unitImplIds);
        $query1 .= ' WHERE ' . implode(' AND ', $pb1->getWhere());


        // Assessments in Projects in UnitImpls (projectImpl not deleted, assessmentImpl not deleted)
        $query2 = "
            SELECT
                ia.id AS 'ia.id',
                sa.id AS 'sa.id'
            FROM assessment_course_unit_implementation pui
            JOIN assessment pa ON pa.id = pui.assessment_id AND pa.deleted_at IS NULL
            JOIN project p ON p.assessment_id = pa.id
            JOIN project_assessment pra ON pra.project_id = p.id
            JOIN assessment ia ON ia.id = pra.assessment_id AND ia.deleted_at IS NULL
            LEFT JOIN assessment_specification_implementation asi ON asi.implementation_assessment_id = ia.id
            LEFT JOIN assessment sa ON sa.id = asi.specification_assessment_id
        ";
        $pb2    = new Builder($this->database->getQuoter(), '');
        $pb2->in('pui.course_unit_implementation_id', $unitImplIds);
        $query2 .= ' WHERE ' . implode(' AND ', $pb2->getWhere());

        $query     = "$query1 UNION $query2";
        $statement = $this->database->prepare($query);
        $statement->execute(array_merge($pb1->getParams(), $pb2->getParams()));

        $assessmentImplToSpecMap = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $assessmentImplToSpecMap[$row['ia.id']] = $row['sa.id'];
        }
        return $assessmentImplToSpecMap;
    }

    /**
     * @param string|null $dateString
     * @return Alaress_Date_Format|null
     */
    protected function createDate(?string $dateString): ?Alaress_Date_Format
    {
        if (is_null($dateString)) {
            return null;
        } else {
            return new Alaress_Date_Format($dateString, Alaress_Date_Format::DATABASE_FORMAT);
        }
    }

    /**
     * Attempt to builds an assessment spec from this row.
     *
     * If the caller to this method expected to receive an instance, and doesn't, it probably means
     * the system has broken data. In this case the caller should throw and exception which
     * halts the import, can be caught by controller, logged to sentry, and used to inform the end-user.
     *
     * @param array $row
     * @return IAssessable|null
     * @throws InvalidDataStateException if the assessment has no determinable type
     */
    protected function buildAssessmentSpecFromRow(array $row)
    {
        // Short-circuit if there's no assessment at all
        if (is_null($row['sa.id'])) {
            return null;
        }

        // Different constructor logic for different assessment types
        if ($row['sdw.id']) {
            $assessmentSpec = new DueWorkAssessment();

            // Need to know if this accepts submissions, because that
            // determines whether open/late date fields show.
            $assessmentSpec = $this->setDueWorkAccepts(
                $assessmentSpec,
                $row['sdw.accept_submission'],
                $row['sdw.multi_submission']
            );
        } elseif ($row['sqz.id']) {
            $assessmentSpec = new QuizAssessment();
            $assessmentSpec
                ->setLearnosityReference($row['sqz.learnosity_reference'])
                ->setLearnosityJobReference($row['sqz.learnosity_job_reference'])
                ->setLearnosityJobStatus($row['sqz.learnosity_job_status'])
            ;
        } elseif ($row['stk.id']) {
            $assessmentSpec = new TaskAssessment();
        } elseif ($row['slt.id']) {
            $assessmentSpec = new LtiAssessment();
        } elseif ($row['specLessonPlan.id']) {
            /* There's a secret sequential dependency here. Project Ids can be set at the same
             * time as Lesson Plan Ids, Due Work Ids, etc. If this assignation occurs after
             * the project detection
             *
             * elseif ($row['spr.id'])
             *
             * you'll get the wrong assessment type hydrated (e.g. a Lesson Plan gets hydrated
             * as a Project). Not sure if this was by design or by accident.
             */
            $assessmentSpec = new LessonPlan();
        } elseif ($row['spr.id']) {
            $assessmentSpec = new Project();
        } else {
            throw new InvalidDataStateException("Broken data detected. Unknown assessment type. id: {$row['sa.id']}");
        }

        $assessmentSpec->setId((int)$row['sa.id']);
        $assessmentSpec->setTitle($row['sa.title']);
        $assessmentSpec->setSequence((int)$row['sa.sequence']);

        $workType   = new WorkType();
        $workTypeId = $row['sa.work_type_id'];
        if (!is_null($workTypeId)) {
            $workType->setId((int)$workTypeId);
            $workType->setName($row['lwt.name']);
        }
        $assessmentSpec->setWorkType($workType);

        $assessmentSpec->setDefaultOpenSpan($row['sa.default_open_span']);
        if (is_null($row['sa.deleted_at'])) {
            $assessmentSpec->setDeletedAt(null);
        } else {
            $assessmentSpec->setDeletedAt($this->createDate($row['sa.deleted_at']));
        }
        $this->assessmentCache[$assessmentSpec->getId()] = $assessmentSpec;
        return $assessmentSpec;
    }

    /**
     * Attempts to builds an assessment implementation from this row.
     *
     * If the caller to this method expected to receive an instance, and doesn't, it probably means
     * the system has broken data. In this case the caller should throw and exception which
     * halts the import, can be caught by controller, logged to sentry, and used to inform the end-user.
     *
     * @param array $row
     * @return IAssessable|null
     * @throws InvalidDataStateException if the assessment has no determinable type
     */
    protected function buildAssessmentImplFromRow($row)
    {
        // Short-circuit if there's no assessment at all
        if (is_null($row['ia.id'])) {
            return null;
        }

        // Different constructor logic for different assessment types
        if ($row['idw.id']) {
            $assessmentImpl = new DueWorkAssessment();
            $assessmentImpl = $this->setDueWorkAccepts(
                $assessmentImpl,
                $row['idw.accept_submission'],
                $row['idw.multi_submission']
            );
        } elseif ($row['iqz.id']) {
            $assessmentImpl = new QuizAssessment();
        } elseif ($row['itk.id']) {
            $assessmentImpl = new TaskAssessment();
        } elseif ($row['ilt.id']) {
            $assessmentImpl = new LtiAssessment();
        } elseif ($row['implLessonPlan.id']) {
            $assessmentImpl = new LessonPlan();
        } elseif ($row['ipr.id']) { // Project needs to be checked at last, as this is not set for an assessment in a project
            $assessmentImpl = new Project();
        } else {
            throw new InvalidDataStateException("Broken data detected. Unknown assessment type. id: {$row['ia.id']}");
        }

        $assessmentImpl->setId((int)$row['ia.id']);
        $assessmentImpl->setTitle($row['ia.title']);
        $assessmentImpl->setSequence((int)$row['ia.sequence']);

        $workType   = new WorkType();
        $workTypeId = $row['ia.work_type_id'];
        if (!is_null($workTypeId)) {
            $workType->setId((int)$workTypeId);
            $workType->setName($row['lwt.name']);
        }
        $assessmentImpl->setWorkType($workType);

        $assessmentImpl->setPublishAt($this->createDate($row['ia.publish_date']));
        $assessmentImpl->setOpenAt($this->createDate($row['ia.open_date']));
        $assessmentImpl->setDueAt($this->createDate($row['ia.due_date']));
        $assessmentImpl->setCloseAt($this->createDate($row['ia.close_date']));
        $assessmentImpl->setDeletedAt($this->createDate($row['ia.deleted_at']));
        $this->assessmentCache[$assessmentImpl->getId()] = $assessmentImpl;
        return $assessmentImpl;
    }

    /**
     * Helper for setting the accepts assessments attribute on a constructed due work.
     *
     * @param DueWorkAssessment $assessment
     * @param bool              $accepts
     * @param bool              $acceptsMulti
     * @return DueWorkAssessment
     */
    protected function setDueWorkAccepts(DueWorkAssessment $assessment, $accepts, $acceptsMulti)
    {
        if ($accepts) {
            if ($acceptsMulti) {
                $assessment->setAcceptsMultiple();
            } else {
                $assessment->setAcceptsSingle();
            }
        } else {
            $assessment->setAcceptsNone();
        }
        return $assessment;
    }


    /**
     * Creates courseImpls for given classFolders, when courseSpec is provided and courseImplId is not provided.
     * Modifies courseImpl when courseImplId is provided (courseSpec will be ignored).
     *
     * @param CourseSpecification|null  $courseSpec
     * @param array                     $unitSpecIds
     * @param Collection                $classFolders
     * @param CourseImplementation|null $courseImpl
     * @return CourseImplementation[]
     * @throws MapperException
     */
    public function createCourseImpls(?CourseSpecification $courseSpec, array $unitSpecIds, Collection $classFolders, ?CourseImplementation $courseImpl): array
    {
        /** @var CourseImplementation[] $courseImpls */
        $courseImpls = [];
        $unitFolders = [];
        if ($courseSpec) {
            foreach ($courseSpec->getUnits() as $unitSpec) {
                if (in_array((int)$unitSpec->getId(), $unitSpecIds, true)) {
                    $unitFolders[] = $unitSpec->getFolder()->getId();
                }
            }
        }

        if (null === $courseImpl) {
            // We are creating a new import
            foreach ($classFolders as $classFolder) {
                $courseImpl = $this->courseImplementationMapper->createModel();
                $courseImpl->setSpecification($courseSpec);
                $courseImpl->setFolder($classFolder);
                $courseImpls[$classFolder->getId()] = $courseImpl;

                //Save unit criteria to class page
                $allFolders = array_merge([$courseSpec->getFolder()->getId()], $unitFolders);
                $this->copyIndicator($allFolders, $classFolder->getId());
            }
        } else {
            // We are modifying an existing import
            // We can only modify a single Course Import at a time.
            $courseImpls[$courseImpl->getFolder()->getId()] = $courseImpl;

            // Load UnitImplementations
            $courseImpl->setUnits($this->unitImplementationMapper->findAllByCourseId($courseImpl->getId()));

            // Load IAssessables
            foreach ($courseImpl->getUnits() as $unitImpl) {
                $unitImpl->setAssessables($this->assessmentMapperProxy->findAllByUnitImplementationIds([$unitImpl->getId()]));
            }

            //Save unit criteria to class page
            $classFolderId = $classFolders->first()->getId();

            $this->copyIndicator($unitFolders, $classFolderId);
        }
        return $courseImpls;
    }

    /**
     * Imports unit to a courseImpl
     * @param CourseImplementation $courseImpl
     * @param UnitSpecification    $unitSpec
     * @param Alaress_Date_Format  $unitImplOpenDate
     * @param Alaress_Date_Format  $unitImplCloseDate
     * @return UnitImplementation|null
     * @throws MapperException
     */
    public function importUnit(CourseImplementation $courseImpl, UnitSpecification $unitSpec, Alaress_Date_Format $unitImplOpenDate, Alaress_Date_Format $unitImplCloseDate): ?UnitImplementation
    {
        // Does the CourseImpl already contain this Unit ?
        $unitSpecId = $unitSpec->getId();
        $callback   = function ($unitImpl) use ($unitSpecId) {
            /** @var UnitImplementation $unitImpl */
            return (int)$unitSpecId === (int)$unitImpl->getSpecification()->getId();
        };
        /** @var UnitImplementation|null $unitImpl */
        $unitImpl = $courseImpl->getUnits()->filter($callback)->first();

        // Did we already delete this UnitImpl ?
        if ($unitImpl && $unitImpl->isDeleted()) {
            return null;
        }

        // If the CourseImpl does not yet contain this UnitSpec
        if (!$unitImpl) {
            // Create a UnitImpl from this UnitSpec
            $unitImpl = $this->unitImplementationMapper->createModel();
            $unitImpl->setSpecification($unitSpec);
            $unitImpl->setCourse($courseImpl);
            $unitImpl->setTitle($unitSpec->getFolder()->getTitle());
            $courseImpl->getUnits()->add($unitImpl);
        }
        $unitImpl->setOpenDate($unitImplOpenDate);
        $unitImpl->setDueDate($unitImplCloseDate);
        $this->unitImplementationMapper->save($unitImpl);

        return $unitImpl;
    }

    /**
     * Creates assessment impl in a given context
     *
     * @param int|null          $implAssessmentId
     * @param IAssessable|null  $specAssessment
     * @param AssessmentContext $context
     * @param bool              $delete
     * @param User              $user
     * @param array             $dateData
     * @return IAssessable|null
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    public function importAssessment(
        ?int $implAssessmentId,
        ?IAssessable $specAssessment,
        AssessmentContext $context,
        bool $delete,
        User $user,
        array $dateData
    ): ?IAssessable {
        if (!$implAssessmentId && !$specAssessment) {
            throw new UnexpectedValueException();
        }

        $notifier = $this->assessmentMapperProxy->getNotifier();

        $before = [];
        if ($implAssessmentId) {
            // We have been passed the Assessment Impl
            // Retrieve AssessmentImpl from UnitImpl
            $filter         = function ($impl) use ($implAssessmentId) {
                /** @var IAssessable $impl */
                return (int)$impl->getId() === $implAssessmentId;
            };
            $specAssessment = $context->getAssessables()->filter($filter)->first();
            $before         = $notifier->dehydrate($specAssessment);
        }

        // Base Case #2 - Is item unselected ? (and already imported)
        if ($delete) {
            $this->assessmentMapperProxy->delete($specAssessment);
            $notifier->notify($specAssessment, $user, $before);
            return null;
        }

        // Item is checked.

        // Apply dates regardless of whether it is a Spec or Impl. (If Spec, they will be cloned to the Impl)
        $specAssessment = $this->applyDates($specAssessment, $dateData);

        if (!$implAssessmentId) {
            // INSERT - Create Impl from Spec
            $newAssessment = $this->replicator->replicateInNewContext($specAssessment, $context, $user);
            $notifier->notify($newAssessment, $user, $before);
        } else {
            // UPDATE
            $newAssessment = null;
            $this->assessmentMapperProxy->save($specAssessment, $user);
            $notifier->notify($specAssessment, $user, $before);
        }

        return $newAssessment ?: $specAssessment;
    }


    /**
     * Apply date strings from an array to the specified Assessment
     *
     * @param IAssessable $assessment
     * @param string[]    $dates
     * @return IAssessable
     * @throws \DomainException if property isn't recognized
     */
    protected function applyDates(IAssessable $assessment, array $dates): IAssessable
    {
        // Set/Update Dates
        foreach ($dates as $property => $local) {
            if (!in_array($property, ['publish', 'open', 'due', 'close'])) {
                continue;
            }
            $date = Alaress_Date_Format::newLocal($local);
            switch (strtolower($property)) {
                case 'publish':
                    $assessment->setPublishAt($date);
                    break;

                case 'open':
                    $assessment->setOpenAt($date);
                    break;

                case 'due':
                    $assessment->setDueAt($date);
                    break;

                case 'close':
                    $assessment->setCloseAt($date);
                    break;

                default:
                    // ignore
            }
        }
        return $assessment;
    }

    /**
     * Copies indicators and capabilities from units which are not existing in the destination folder
     *
     * @param int[] $sourceIds
     * @param int   $destinationId
     * @return static
     * @throws MapperException
     */
    private function copyIndicator(array $sourceIds, int $destinationId): self
    {
        $indicatorMapper = IndicatorMapper::getInstance();

        // Save copy of indicators with class page as parent
        foreach ($indicatorMapper->findForFolder($sourceIds) as $indicator) {
            $indicatorMapper->findOrCreateOneForFolderByKey(
                $destinationId,
                $indicator->getName(),
                $indicator->getCapability()->getName(),
                false,
                $indicator->getCapability()->getCurriculumNodes(),
                $indicator->getCurriculumNodes()
            );
        }

        return $this;
    }

    /**
     * Attempts to find a Course Outline ComponentInstance on the specified page
     * that is not currently linked to a CourseImport.
     *
     * @param int      $folderId
     * @param int|null $courseImplId
     * @return \Component_Instance|null
     */
    public function findAvailableCourseOutlineComponent(int $folderId, ?int $courseImplId = null): ?\Component_Instance
    {
        $module        = \Component_Definition::MODULE_HOMEPAGE;
        $class         = HomepageOutlineComponentController::class;
        $compInstances = $this->componentInstanceMapper->findByContextAndClass($module, $folderId, $class);
        $compInstances->sortBy('sequence', 'ASC');
        foreach ($compInstances as $component) {
            // If this component has no associated Course, or the course is not valid
            if (
                !$component->getConfigArray()['importedCourse'] ||
                !$component->getConfigArray()['importedCourse']['value']
            ) {
                // Conclude the Component is available, and use it
                return $component;
            }
            if (null !== $courseImplId && (int)$component->getConfigArray()['importedCourse']['value'] === $courseImplId) {
                return $component;
            }
        }
        return null;
    }


    /**
     * Configures the specified component to display the specified course.
     *
     * Creates the component if none specified.
     *
     * @param CourseImplementation     $courseImpl
     * @param \Component_Instance|null $compInstance
     * @return string The name of the new Component
     * @throws MapperException
     * @throws \PDOException
     */
    public function injectImportIntoComponent(
        CourseImplementation $courseImpl,
        ?\Component_Instance $compInstance
    ): string {
        // Did we find an available component?
        if ($compInstance) {
            // Find its 'importedCourse' config
            /** @var \Component_ConfigDefinition|null $configDefinition */
            $configDefinition = null;
            foreach ($compInstance->getConfig() as $configItem) {
                if ('importedCourse' === $configItem->getKey()) {
                    $configDefinition = $configItem;
                    break;
                }
            }
            if (!$configDefinition) {
                $configDefinition = $this->componentConfigDefinitionMapper->findByColumnList(
                    [
                        'component_definition_id' => $compInstance->getDefinitionId(),
                        'key'                     => 'importedCourse',
                    ]
                )->first();
                $configDefinition->setKey('importedCourse');
            }
            $configInstance = $this->componentConfigInstanceMapper->findByColumnList(
                [
                    'component_config_definition_id' => $configDefinition->getId(),
                    'component_instance_id'          => $compInstance->getId(),
                ]
            )->first();
            if (!$configInstance) {
                $configInstance = $this->componentConfigInstanceMapper->createModel();
                $configInstance->setConfigDefinitionId($configDefinition->getId());
                $configInstance->setInstanceId($compInstance->getId());
            }
            $configInstance->setValue($courseImpl->getId());
            $this->componentConfigInstanceMapper->save($configInstance);
        } else {
            // No available component, create one.
            $folder             = $courseImpl->getFolder();
            $configDefinition   = ['importedCourse' => $courseImpl->getId()];
            $courseOutlineClass = HomepageOutlineComponentController::class;
            $compInstance       = $this->componentInstanceMapper->injectNewComponent($folder, $configDefinition, $courseOutlineClass);
            // needing the hydrated component object unable to use the above, as it is just a stub
            $compInstance       = $this->componentInstanceMapper->findByInstanceId($compInstance->getId(), $folder->getId());
        }
        $config        = $compInstance->getConfigArray();
        $componentName = $config['componentname']['value'] ?: $config['componentname']['defaultValue'];
        return $componentName;
    }

    /**
     * Add folder name to unit specs so that we can render this on course import page.
     * @param array $emptyUnitSpecIds
     * @param array $unitSpecCache
     * @return void
     */
    private function loadSpecFolders(array $emptyUnitSpecIds, array $unitSpecCache): void
    {
        if (empty($emptyUnitSpecIds)) {
            return;
        }
        $emptyUnitsBindings = Builder::getBindings($emptyUnitSpecIds, 'us');
        $folderQuery        = "SELECT `folder`.`id` AS `folder_id`,
                                      `folder`.`name` AS `folder_name`,
                                      `course_unit_specification`.`id` AS `spec_id`
                               FROM   `folder` JOIN `course_unit_specification`
                                      ON `course_unit_specification`.`folder_id` = `folder`.`id`
                               WHERE  `course_unit_specification`.`id` IN ({$emptyUnitsBindings->getPlaceholder()})";
        $folderStatement    = $this->database->query($folderQuery, $emptyUnitsBindings->getParams());
        $rows               = $folderStatement->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $unitSpecId = $row['spec_id'];
            $folder     = new Folder();
            $folder->setName($row['folder_name']);
            $unitSpecCache[$unitSpecId]->setFolder($folder);
        }
    }
}
