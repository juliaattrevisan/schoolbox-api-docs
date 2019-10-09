<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress_Date_Format;
use function array_intersect;
use function array_merge;
use function array_unique;
use function count;
use function implode;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\Term;
use Schoolbox\Filter\Filters\Assessment\WorkType;
use Schoolbox\Filter\Filters\Assessment\AssessmentType;
use Schoolbox\Filter\Mapper;
use Schoolbox\IMapper;
use UnderflowException;
use UnexpectedValueException;

/**
 * Helper for building the query clauses.
 *
 * The overall query will be made up of 0-6 sub-queries that are combined using UNION.
 * If 0, then caller should short-circuit to return an empty set
 * Sub-query for each extra assessment context.
 *
 * * 0. Base (no context considered)
 * * 1. In Folder
 * * 2. In Project  on Folder
 * * 3. In UnitImpl on Folder
 * * 4. In Project  in UnitImpl on Folder
 * * 5. In UnitSpec in Folder   on Folder
 * * 6. In Project  in UnitSpec on Folder
 *
 * Callers (eg Controllers) should call this like:
 *  1. $ids                = $this->assessmentIdFinder->findIdsByFilters($filters, $deleted);
 *  2. Then either of:
 *      $assessmentModels  = $assessmentMapper->findByColumn('id', $ids, $options);
 *      $assessableObjects = $assessmentMapperProxy->findByColumn('id', $ids, $options);
 *
 * It is *NOT* the responsibility of this class to determine if a filter represents a full set, and therefore should not be applied.
 * That is the responsibility of IKeyConverter::getSelectedItems().
 * eg, if Weighting filter is specified as both weighted and unweighted, logically we
 * don't need to apply that filter, but that optimisation is NOT done here.
 *
 * @method static IdFinder getInstance(Mapper $filterMapper, IMapper $mapper)
 */
class IdFinder extends Filter\AbstractIdFinder
{

    // Which date column does the term filter relate to?
    private const DATE_COL_DUE = 'due_date';
    private const DATE_COL_CLS = 'close_date';


    /**
     * Different sub-queries are required depending on which assessment-contexts filters are specified
     * Each sub-queries are modified depending on which non-assessment-context filters are specified
     *
     * @inheritdoc
     * @throws Filter\BrokenFilterException If filter SQL fragment cannot be built
     * @throws InvalidColumnException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    protected function buildQuery(array $completeFilters, $deleted): array
    {
        // Filters which effect which sub-queries are required
        $teacherFolderIds      = $completeFilters[Folder\TeacherInvolved\Definition::class];
        $studentFolderIds      = $completeFilters[Folder\StudentInteractions\Definition::class];
        $courseImportFolderIds = $completeFilters[Folder\CourseImports\Definition::class];
        $readableFolderIds     = $completeFilters[Folder\Readable\Definition::class];
        $projectIds            = $completeFilters[NullableContext\Project\Definition::class];
        $unitImplIds           = $completeFilters[NullableContext\UnitImpl\Definition::class];
        $unitSpecIds           = $completeFilters[NullableContext\UnitSpec\Definition::class];
        $unitImpSpcIds         = $completeFilters[NullableContext\UnitImportSpec\Definition::class];
        $courseImpSpcIds       = $completeFilters[NullableContext\CourseImportSpec\Definition::class];
        $folderYearLevels      = $completeFilters[FolderYearLevel\Definition::class];


        // Augment unit imp spc ids with course imp's unit imp spc ids
        // That's easier than writing it into the query.
        $unitImpSpcIds = $this->augmentUnitImplSpecIds($courseImpSpcIds, $unitImpSpcIds);


        [$folderWhere, $folderParams]     = $this->getClauseFolder(
            $teacherFolderIds,
            $studentFolderIds,
            $courseImportFolderIds,
            $readableFolderIds
        );
        [$yearLevelJoin, $yearLevelWhere, $yearLevelParams] = $this->getClauseFolderYearLevels($folderYearLevels);
        [$projectWhere, $projectParams]   = $this->getClauseProject($projectIds);
        [$unitImplWhere, $unitImplParams] = $this->getClauseUnitImpl($unitImplIds);
        [$unitSpecWhere, $unitSpecParams] = $this->getClauseUnitSpec($unitSpecIds);

        // This filter affects which sub-queries are used, and also potentially modifies the unit-impl-context sub-queries
        [$uImpSpcJoin, $uImpSpcWhere, $uImpSpcParams] = $this->getClauseUnitImplSpec($unitImpSpcIds);

        // Sub-query modifiers
        $aTypes         = $completeFilters[AssessmentType\Definition::class];
        $temporalStates = $completeFilters[TemporalState\Definition::class];
        $termsDue       = $completeFilters[Term\Due\Definition::class];
        $termsClose     = $completeFilters[Term\Close\Definition::class];
        $weightings     = $completeFilters[WeightingType\Definition::class];
        $workTypeIds    = $completeFilters[WorkType\Definition::class];
        $yearsDue       = $completeFilters[YearDue\Definition::class];
        $rubricUsages   = $completeFilters[RubricUsage\Definition::class];
        $markPerCap     = $completeFilters[MarkPerCap\Definition::class];

        $dueWorkAlias                                             = 'dw';
        [$aTypeJoin, $aTypeWhere]                                 = $this->getClauseAType($aTypes, $rubricUsages, $markPerCap, $dueWorkAlias);
        [$rubricUsageJoin, $rubricUsageWhere, $rubricUsageParams] = $this->getClauseRubricUsage($rubricUsages, $aTypes, $dueWorkAlias);
        [$markPerCapWhere]                                        = $this->getClauseMarkPerCap($markPerCap, $dueWorkAlias);
        [$temporalWhere, $temporalParams, $participantPlanJoin]   = $this->getClauseTemporalState($temporalStates);
        [$termDJoin, $termDWhere, $termDParams]                   = $this->getClauseTerm($termsDue, self::DATE_COL_DUE);
        [$termCJoin, $termCWhere, $termCParams]                   = $this->getClauseTerm($termsClose, self::DATE_COL_CLS);
        [$weightingWhere, $weightingParams]                       = $this->getClauseWeighting($weightings);
        [$workTypeWhere,  $workTypeParams]                        = $this->getClauseWorkType($workTypeIds);
        [$yearDueWhere, $yearDueParams]                           = $this->getClauseYear($yearsDue, self::DATE_COL_DUE);
        [$deletedWhere]                                           = $this->getClauseDeleted($deleted);

        // Build the non-context JOINs
        $nonContextJoins = [
            $participantPlanJoin,
            $aTypeJoin,
            $rubricUsageJoin,
            $termDJoin,
            $termCJoin,
        ];
        $nonContextJoin = implode("\n", $nonContextJoins);

        // Build the non-context WHERE clauses.
        $nonContextWhereClauses = [
            $aTypeWhere,
            $rubricUsageWhere,
            $markPerCapWhere,
            $temporalWhere,
            $termDWhere,
            $termCWhere,
            $weightingWhere,
            $workTypeWhere,
            $yearDueWhere,
            $deletedWhere,
            $yearLevelWhere,
        ];
        $nonContextWhereClause = implode(' AND ', $nonContextWhereClauses);

        // Merge all the params
        $params = array_merge(
            $folderParams,
            $projectParams,
            $unitImplParams,
            $unitSpecParams,
            $uImpSpcParams,
            $temporalParams,
            $rubricUsageParams,
            $termDParams,
            $termCParams,
            $weightingParams,
            $workTypeParams,
            $yearDueParams,
            $yearLevelParams
        );

        $subQueries = [];
        if ($this->subQueryBase(
            $unitImpSpcIds,
            $teacherFolderIds,
            $studentFolderIds,
            $readableFolderIds,
            $projectIds,
            $unitImplIds,
            $unitSpecIds
        , $folderYearLevels)) {
            $subQueries[] = "
                SELECT a.id, 0 AS 'case' FROM `assessment` a
                {$nonContextJoin}
                WHERE {$nonContextWhereClause}
                ";
        } else {
            if ($this->subQueryFolder($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)) {
                // Need to join to folder table if filtering on folder attributes
                $folderJoin   = count($folderParams) ? 'JOIN folder f ON af.folder_id = f.id' : '';
                $folderYearLevelJoin = $yearLevelJoin ? $yearLevelJoin . ' JOIN folder_year_level fyl ON af.folder_id = fyl.folder_id' : '';
                $subQueries[] = "
                    SELECT a.id, 1 AS 'case' FROM `assessment` a
                    JOIN assessment_folder af ON af.assessment_id = a.id
                    {$folderJoin}
                    {$folderYearLevelJoin}
                    {$nonContextJoin}
                    WHERE {$folderWhere} AND {$nonContextWhereClause}
                    ";
            }
            if ($this->subQueryProjectFolder($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)) {
                $folderJoin   = count($folderParams) ? 'JOIN folder f ON paf.folder_id = f.id' : '';
                $folderYearLevelJoin = $yearLevelJoin ? $yearLevelJoin . ' JOIN folder_year_level fyl ON paf.folder_id = fyl.folder_id' : '';
                $subQueries[] = "
                    SELECT a.id, 2 AS 'case' FROM `assessment` a
                    JOIN project_assessment ap ON ap.assessment_id  = a.id
                    JOIN project p             ON ap.project_id     = p.id
                    JOIN assessment_folder paf ON paf.assessment_id = p.assessment_id
                    {$folderJoin}
                    {$folderYearLevelJoin}
                    {$nonContextJoin}
                    WHERE {$folderWhere} AND {$projectWhere} AND {$nonContextWhereClause}
                    ";
            }
            if ($this->subQueryUnitImpl($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)) {
                $courseImplJoin      = $yearLevelJoin || count($folderParams) ? 'JOIN course_implementation ci ON ui.course_implementation_id = ci.id' : '';
                $folderJoin          = count($folderParams) ? 'JOIN folder f  ON ci.folder_id = f.id' : '';
                $folderYearLevelJoin = $yearLevelJoin ? $yearLevelJoin . ' JOIN folder_year_level fyl ON ci.folder_id = fyl.folder_id' : '';
                $subQueries[] = "
                    SELECT a.id, 3 AS 'case' FROM `assessment` a
                    JOIN assessment_course_unit_implementation aui ON aui.assessment_id = a.id
                    JOIN course_unit_implementation ui             ON aui.course_unit_implementation_id = ui.id
                    {$uImpSpcJoin}
                    {$courseImplJoin}
                    {$folderJoin}
                    {$folderYearLevelJoin}
                    {$nonContextJoin}
                    WHERE {$uImpSpcWhere} AND {$folderWhere} AND {$unitImplWhere} AND {$nonContextWhereClause}
                    ";
            }
            if ($this->subQueryProjectUnitImpl($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)) {
                $courseImplJoin      = $yearLevelJoin || count($folderParams) ? 'JOIN course_implementation ci ON ui.course_implementation_id = ci.id' : '';
                $folderJoin          = count($folderParams) ? 'JOIN folder f  ON ci.folder_id = f.id' : '';
                $folderYearLevelJoin = $yearLevelJoin ? $yearLevelJoin . ' JOIN folder_year_level fyl ON ci.folder_id = fyl.folder_id' : '';
                $subQueries[] = "
                    SELECT a.id, 4 AS 'case' FROM `assessment` a
                    JOIN project_assessment ap                     ON ap.assessment_id                  = a.id
                    JOIN project p                                 ON ap.project_id                     = p.id 
                    JOIN assessment_course_unit_implementation aui ON aui.assessment_id                 = p.assessment_id
                    JOIN course_unit_implementation ui             ON aui.course_unit_implementation_id = ui.id
                    {$uImpSpcJoin}
                    {$courseImplJoin}
                    {$folderJoin}
                    {$folderYearLevelJoin}
                    {$nonContextJoin}
                    WHERE {$uImpSpcWhere} AND {$folderWhere} AND {$projectWhere} AND {$unitImplWhere} AND {$nonContextWhereClause}
                    ";
            }
            if ($this->subQueryUnitSpec($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)) {
                $folderJoin   = count($folderParams) ? 'JOIN folder f ON us.folder_id = f.id' : '';
                $folderYearLevelJoin = $yearLevelJoin ? $yearLevelJoin . ' JOIN folder_year_level fyl ON us.folder_id = fyl.folder_id' : '';
                $subQueries[] = "
                    SELECT a.id, 5 AS 'case' FROM `assessment` a
                    JOIN assessment_course_unit_specification aus ON aus.assessment_id = a.id
                    JOIN course_unit_specification us ON aus.course_unit_specification_id = us.id
                    {$folderJoin}
                    {$folderYearLevelJoin}
                    {$nonContextJoin}
                    WHERE {$folderWhere} AND {$unitSpecWhere} AND {$nonContextWhereClause}
                    ";
            }
            if ($this->subQueryProjectUnitSpec($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)) {
                $folderJoin   = count($folderParams) ? 'JOIN folder f ON us.folder_id = f.id' : '';
                $folderYearLevelJoin = $yearLevelJoin ? $yearLevelJoin . ' JOIN folder_year_level fyl ON us.folder_id = fyl.folder_id' : '';
                $subQueries[] = "
                    SELECT a.id, 6 AS 'case' FROM `assessment` a
                    JOIN project_assessment ap                     ON ap.assessment_id  = a.id
                    JOIN project p                                 ON ap.project_id     = p.id
                    JOIN assessment_course_unit_specification aus  ON aus.assessment_id = p.assessment_id
                    JOIN course_unit_specification us              ON aus.course_unit_specification_id = us.id
                    {$folderJoin}
                    {$folderYearLevelJoin}
                    {$nonContextJoin}
                    WHERE {$folderWhere} AND {$projectWhere} AND {$unitSpecWhere} AND {$nonContextWhereClause}
                    ";
            }
        }

        return [implode("\n\n\tUNION\n\n", $subQueries), $params];
    }


    /**
     * Include (#0) 'base' sub-query? (and just the base query)
     *
     * This will only be true if no context or folderYearLevel filters are specified
     *
     * @param null|Collection|int[] $uImpSpcIds
     * @param null|Collection|int[] $teacherFolderIds
     * @param null|Collection|int[] $studentFolderIds
     * @param null|Collection|int[] $readableFolderIds
     * @param null|Collection|int[] $projectIds
     * @param null|Collection|int[] $unitImplIds
     * @param null|Collection|int[] $unitSpecIds
     * @param Collection|null       $folderYearLevels
     * @return bool
     */
    private function subQueryBase(
        ?Collection $uImpSpcIds,
        ?Collection $teacherFolderIds,
        ?Collection $studentFolderIds,
        ?Collection $readableFolderIds,
        ?Collection $projectIds,
        ?Collection $unitImplIds,
        ?Collection $unitSpecIds,
        ?Collection $folderYearLevels
    ): bool {
        return
            null === $uImpSpcIds &&
            null === $teacherFolderIds &&
            null === $studentFolderIds &&
            null === $readableFolderIds &&
            null === $projectIds &&
            null === $unitImplIds &&
            null === $unitSpecIds &&
            $folderYearLevels === null;
    }


    /**
     * Include (#1) 'folder' sub-query?
     *
     * @param null|Collection|int[] $uImpSpcIds
     * @param null|Collection|int[] $projectIds
     * @param null|Collection|int[] $unitImplIds
     * @param null|Collection|int[] $unitSpecIds
     * @return bool
     */
    private function subQueryFolder(
        ?Collection $uImpSpcIds,
        ?Collection $projectIds,
        ?Collection $unitImplIds,
        ?Collection $unitSpecIds
    ) {
        // Semantically, each of these tests equate to: "Do filters indicate assessment must reside in instances of this non-folder context?"
        if ($projectIds instanceOf Collection && $projectIds->count() && !$projectIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        if ($unitSpecIds instanceOf Collection && $unitSpecIds->count() && !$unitSpecIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        if ($unitImplIds instanceOf Collection && $unitImplIds->count() && !$unitImplIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        if ($uImpSpcIds instanceOf Collection && $uImpSpcIds->count() && !$uImpSpcIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        return true;
    }


    /**
     * Include (#2) 'in project' sub-query?
     *
     * @param null|Collection|int[] $uImpSpcIds
     * @param null|Collection|int[] $projectIds
     * @param null|Collection|int[] $unitImplIds
     * @param null|Collection|int[] $unitSpecIds
     * @return bool
     */
    private function subQueryProjectFolder(
        ?Collection $uImpSpcIds,
        ?Collection $projectIds,
        ?Collection $unitImplIds,
        ?Collection $unitSpecIds
    ) {
        if ($projectIds instanceOf Collection && !$projectIds->count()) {
            return false;
        }
        if ($unitImplIds instanceOf Collection && $unitImplIds->count() && !$unitImplIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        if ($unitSpecIds instanceOf Collection && $unitSpecIds->count() && !$unitSpecIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        if ($uImpSpcIds instanceOf Collection && $uImpSpcIds->count() && !$uImpSpcIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        return true;
    }


    /**
     * Include (#3) 'in unit-impl' sub-query?
     *
     * @param null|Collection|int[] $uImpSpcIds
     * @param null|Collection|int[] $projectIds
     * @param null|Collection|int[] $unitImplIds
     * @param null|Collection|int[] $unitSpecIds
     * @return bool
     */
    private function subQueryUnitImpl(
        ?Collection $uImpSpcIds,
        ?Collection $projectIds,
        ?Collection $unitImplIds,
        ?Collection $unitSpecIds
    ): bool {
        if ($unitImplIds instanceOf Collection && !$unitImplIds->count()) {
            return false;
        }

        if ($uImpSpcIds instanceOf Collection && !$uImpSpcIds->count()) {
            return false;
        }
        if ($projectIds instanceOf Collection && $projectIds->count() && !$projectIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        if ($unitSpecIds instanceOf Collection && $unitSpecIds->count() && !$unitSpecIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        return true;
    }


    /**
     * Include (#4) 'in project in unit-impl' sub-query?
     *
     * @param null|Collection|int[] $uImpSpcIds
     * @param null|Collection|int[] $projectIds
     * @param null|Collection|int[] $unitImplIds
     * @param null|Collection|int[] $unitSpecIds
     * @return bool
     */
    private function subQueryProjectUnitImpl(
        ?Collection $uImpSpcIds,
        ?Collection $projectIds,
        ?Collection $unitImplIds,
        ?Collection $unitSpecIds
    ): bool {
        if ($projectIds instanceOf Collection && !$projectIds->count()) {
            return false;
        }
        if ($unitImplIds instanceOf Collection && !$unitImplIds->count()) {
            return false;
        }
        if ($uImpSpcIds instanceOf Collection && !$uImpSpcIds->count()) {
            return false;
        }
        if ($unitSpecIds instanceOf Collection && $unitSpecIds->count() && !$unitSpecIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        return true;
    }


    /**
     * Include (#5) 'in unit-spec' sub-query?
     *
     * @param null|Collection|int[] $uImpSpcIds
     * @param null|Collection|int[] $projectIds
     * @param null|Collection|int[] $unitImplIds
     * @param null|Collection|int[] $unitSpecIds
     * @return bool
     */
    private function subQueryUnitSpec(
        ?Collection $uImpSpcIds,
        ?Collection $projectIds,
        ?Collection $unitImplIds,
        ?Collection $unitSpecIds
    ): bool {
        if ($unitSpecIds instanceOf Collection && !$unitSpecIds->count()) {
            return false;
        }
        if ($unitImplIds instanceOf Collection && $unitImplIds->count() && !$unitImplIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        if ($uImpSpcIds instanceOf Collection && $uImpSpcIds->count() && !$uImpSpcIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        if ($projectIds instanceOf Collection && $projectIds->count() && !$projectIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        return true;
    }


    /**
     * Include (#6) 'in project in unit-spec' sub-query?
     *
     * @param null|Collection|int[] $uImpSpcIds
     * @param null|Collection|int[] $projectIds
     * @param null|Collection|int[] $unitImplIds
     * @param null|Collection|int[] $unitSpecIds
     * @return bool
     */
    private function subQueryProjectUnitSpec(
        ?Collection $uImpSpcIds,
        ?Collection $projectIds,
        ?Collection $unitImplIds,
        ?Collection $unitSpecIds
    ): bool {
        if ($projectIds instanceOf Collection && !$projectIds->count()) {
            return false;
        }
        if ($unitSpecIds instanceOf Collection && !$unitSpecIds->count()) {
            return false;
        }
        if ($unitImplIds instanceOf Collection && $unitImplIds->count() && !$unitImplIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        if ($uImpSpcIds instanceOf Collection && $uImpSpcIds->count() && !$uImpSpcIds->keyExists(NullableContext\Definition::KEY_NONE)) {
            return false;
        }
        return true;
    }


    /**
     * Get query fragments dependent on filters:
     * * AssessmentType
     * * RubricUsage
     * * MarkPerCap
     *
     * There is a coupling between the implementation of these filters.
     * If any combination implies that we need to inspect the submission_box table, or JOIN via it, then the join from
     * `assessment` to `submission_box` is generated here. Hence the other two getClause*() methods can assume the `submission_box`
     * JOIN is already handled elsewhere.
     *
     * @precondition Filter combinations that logically can never return rows are not passed to this method. They are already handled.
     * @precondition Filter sets that logically cause no filtering to occur have been set to NULL
     * @param null|Collection|int[] $aTypes
     * @param Collection|null       $rubricUsages
     * @param Collection|null       $markPerCap
     * @param string                $dueWorkAlias
     * @return mixed[]
     * @throws InvalidColumnException
     */
    private function getClauseAType(?Collection $aTypes, ?Collection $rubricUsages, ?Collection $markPerCap, string $dueWorkAlias): array
    {
        // Are we not filtering by assessment type, have no cap/Ind usage, and no markPerCap considerations?
        if (null === $aTypes && null === $rubricUsages && null === $markPerCap) {
            // We don't need to JOIN to any assessment type table.
            return ['', 'TRUE'];
        }

        // We do need to JOIN to assessment type table/s. Start building JOIN and WHERE clause.
        $joinClause = '';

        // Do we need to JOIN to `submission_box`?
        $joinToSubBox = ($aTypes && $aTypes->keyExists(AssessmentType\Definition::KEY_DUE_WORK)) || $rubricUsages || $markPerCap;

        // Are we only joining to a single assessment type table?
        $oneJoinOnly =
            ($aTypes && $aTypes->count() === 1 && !$rubricUsages && !$markPerCap) ||                             // Just 1 of [project/quiz/assess/subBox]
            (!$aTypes && ($rubricUsages || $markPerCap)) ||                                                      // Just subBox due to rubricUsage || markPerCap
            ($aTypes && $aTypes->count() === 1 && $aTypes->keyExists(AssessmentType\Definition::KEY_DUE_WORK));  // Just [subBox], others don't matter here


        // If we are only fetching 1 of the assessment types, we can INNER JOIN, otherwise we must LEFT JOIN and add WHERE clause
        if ($oneJoinOnly) {
            $joinType = 'INNER';
            $builder  = null;
        } else {
            $joinType = 'LEFT';
            $builder  = $this->getMapper()->getPredicateBuilder();
        }

        // Task
        if ($aTypes && $aTypes->keyExists(AssessmentType\Definition::KEY_TASK)) {
            $joinClause .= "
                $joinType JOIN task tk           ON tk.assessment_id = a.id
            ";
            if ($builder) {
                $builder->notNull('tk.id');
            }
        }

        // Quiz
        if ($aTypes && $aTypes->keyExists(AssessmentType\Definition::KEY_QUIZ)) {
            $joinClause .= "
                $joinType JOIN assess qz         ON qz.assessment_id = a.id
            ";
            if ($builder) {
                $builder->notNull('qz.assess_id');
            }
        }

        // DueWork
        if ($joinToSubBox) {
            $joinClause .= "
                $joinType JOIN submission_box $dueWorkAlias ON $dueWorkAlias.assessment_id = a.id
            ";
            if ($builder) {
                $builder->notNull("$dueWorkAlias.id");
            }
        }

        // Project
        if ($aTypes && $aTypes->keyExists(AssessmentType\Definition::KEY_PROJECT)) {
            $joinClause .= "
                $joinType JOIN project pr ON pr.assessment_id = a.id
            ";
            if ($builder) {
                $builder->notNull('pr.id');
            }
        }
        return [$joinClause, $builder ? '(' . implode(' OR ', $builder->getWhere()) . ')' : 'TRUE'];
    }


    /**
     * Modify query so we only return assessments that use any of the specified Capabilities or Indicators
     *
     * If the filters include the 'none' option, also fetch assessments that don't use any Capabilities/Indicators at all.
     *
     * @param Collection|null $rubricUsages
     * @param Collection|null $aTypes
     * @param string          $dueWorkAlias
     * @return array
     * @throws InvalidColumnException
     * @throws UnexpectedValueException
     *
     * We don't to join to `submission_box` here:
     * @see getClauseAType
     */
    private function getClauseRubricUsage(?Collection $rubricUsages, ?Collection $aTypes, string $dueWorkAlias): array
    {
        // Do we not wish to apply this filter?
        if (null === $rubricUsages) {
            // Always return the row, no params
            return ['', 'TRUE', []];
        }

        // Do we need to join to usage table?
        if ($rubricUsages->count() === 1 && $rubricUsages->keyExists(RubricUsage\Definition::KEY_NONE)) {
            // No need to join to usage, we can just examine the `submission_box.use_rubric` column.
            $join = '';
        } else {
            // We need to examine, usage, how should we join?
            $otherAssessmentTypes = $aTypes && ($aTypes->count() > 1 || !$aTypes->keyExists(AssessmentType\Definition::KEY_DUE_WORK));
            $joinType             = $otherAssessmentTypes ? 'LEFT' : 'INNER';
            $join                 = "$joinType JOIN rubric_capability_usage rgu ON $dueWorkAlias.id = rgu.submission_box_id";
        }

        $pb    = $this->getMapper()->getPredicateBuilder();
        $codex = new RubricUsage\Codec();

        $capabilityIds = [];

        foreach($rubricUsages as $rubricUsage) {
            if ($rubricUsage === RubricUsage\Definition::KEY_NONE) {
                $pb->isFalse("$dueWorkAlias.use_rubric");
            } else {
                // This assumes that if an assessment is using an indicator, it is using the indicator's capability.
                // Hence we can just JOIN to capability usage
                [$capId] = $codex->decode($rubricUsage);
                $capabilityIds[] = $capId;
            }
        }

        foreach(array_unique($capabilityIds) as $uniqueCapId) {
            $pb->equals('rgu.rubric_capability_id', $uniqueCapId);
        }

        return [$join, '(' . implode(' OR ', $pb->getWhere()) . ')', $pb->getParams()];
    }


    /**
     * Modify query so we only return assessments that mark-per-capability
     *
     * If the filters include the 'none' option, also fetch assessments that don't use rubrics

     * @param Collection|null $markPerCap
     * @param string          $dueWorkAlias
     * @return array
     * @throws InvalidColumnException
     *
     * We don't to join to `submission_box` here:
     * @see getClauseAType
     */
    private function getClauseMarkPerCap(?Collection $markPerCap, string $dueWorkAlias): array
    {
        // Do we not wish to apply this filter?
        if (null === $markPerCap) {
            // Always return the row, no params
            return ['TRUE', []];
        }

        $pb                   = $this->getMapper()->getPredicateBuilder();

        if ($markPerCap->keyExists(MarkPerCap\Definition::KEY_NONE)) {
            // Not a DueWork
            $pb->isNull("$dueWorkAlias.id");

            // DueWork not using rubric
            $pb->isFalse("$dueWorkAlias.use_rubric");
        }

        if ($markPerCap->keyExists(MarkPerCap\Definition::KEY_ON)) {
            $pb->isTrue("$dueWorkAlias.rubric_mark_per_capability");
        }
        if ($markPerCap->keyExists(MarkPerCap\Definition::KEY_OFF)) {
            $pb->isFalse("$dueWorkAlias.rubric_mark_per_capability");
        }

        return ['('. implode(' OR ', $pb->getWhere()) . ')'];
    }


    /**
     * Get query fragments dependent on 'Folder' filter
     *
     * @param null|Collection|int[] $teacherFolderIds
     * @param null|Collection|int[] $studentFolderIds
     * @param null|Collection|int[] $courseImportFolderIds
     * @param null|Collection|int[] $readableFolderIds
     * @return mixed[]
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function getClauseFolder(
        ?Collection $teacherFolderIds,
        ?Collection $studentFolderIds,
        ?Collection $courseImportFolderIds,
        ?Collection $readableFolderIds
    ): array {
        // Different filters are always combined using INTERSECT

        // All NULL ?
        if (null === $teacherFolderIds
            && null === $studentFolderIds
            && null === $courseImportFolderIds
            && null === $readableFolderIds
        ) {
            return ['TRUE', []];
        }

        $folderIds = null;
        if ($teacherFolderIds) {
            $folderIds = $teacherFolderIds->getAll();
        }
        if ($studentFolderIds) {
            $folderIds = null === $folderIds
                ? $studentFolderIds->getAll()
                : array_intersect($folderIds, $studentFolderIds->getAll());
        }
        if ($courseImportFolderIds) {
            $folderIds = null === $folderIds
                ? $courseImportFolderIds->getAll() :
                array_intersect($folderIds, $courseImportFolderIds->getAll());
        }
        if ($readableFolderIds) {
            $folderIds = null === $folderIds
                ? $readableFolderIds->getAll() :
                array_intersect($folderIds, $readableFolderIds->getAll());
        }

        // No folder IDs (but search not null)?
        // Return a predicate that matches nothing
        if (empty($folderIds)) {
            return ['FALSE', []];
        }

        $builder = $this->getMapper()->getPredicateBuilder();
        $builder->in('f.id', $folderIds);

        return [$builder->getWhere()[0], $builder->getParams()];
    }


    /**
     * Return structure indicates:
     * * Whether we need to join to the `folder_year_level` table, and the type of join (INNER | LEFT)
     * * The WHERE clause with placeholders
     * * Any params to use in the WHERE clause
     *
     * @param Collection|null $folderYearLevels
     * @return array
     * @internal null|string return[0] join type to folder_year_level table
     * @internal string      return[1] Where clause with placeholders
     * @internal array       return[2] Where clause params
     * @throws InvalidColumnException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    private function getClauseFolderYearLevels(?Collection $folderYearLevels): array
    {
        // Case #1 - null, don't apply the filter at all
        if ($folderYearLevels === null) {
            return [null, 'TRUE', []];
        }

        // Case #2 - empty collection, should already be handled by pre-query-build optimiser
        if (!$folderYearLevels->count()) {
            throw new UnexpectedValueException('Folder YearLevels filter is empty');
        }

        # Case #3 - options present.

        // What does the filter options semantically contain?
        $concreteYearLevelIds = [];
        $matchNone            = false;
        foreach ($folderYearLevels as $yearLevelId) {
            if ($yearLevelId === null) {
                $matchNone = true;
                continue;
            }
            $concreteYearLevelIds[] = $yearLevelId;
        }

        $pb = $this->getMapper()->getPredicateBuilder();
        if ($matchNone) {
            $pb->isNull('fyl.year_level_id');
        }
        if (count($concreteYearLevelIds)) {
            $pb->in('fyl.year_level_id', $concreteYearLevelIds);
        }

        if ($matchNone && count($concreteYearLevelIds)) {
            $pb = $this->getMapper()->getPredicateBuilder()->any($pb);
        }

        return [$matchNone ? 'LEFT' : 'INNER', $pb->getWhere()[0], $pb->getParams()];
    }


    /**
     * Get query fragments dependent on 'Project' filter
     *
     * @param null|Collection|int[] $projectIds
     * @return mixed[]
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function getClauseProject(Collection $projectIds = null)
    {
        if (null === $projectIds || !$projectIds->count()) {
            $whereClause = 'TRUE';
            $whereParams = [];
        } else {
            $ids = $projectIds->getAll();
            if (array_key_exists(NullableContext\Definition::KEY_NONE, $ids)) {
                unset($ids[NullableContext\Definition::KEY_NONE]);
            }
            $builder = $this->getMapper()->getPredicateBuilder();
            $builder->in('p.id', $ids);
            $whereClause = $builder->getWhere()[0];
            $whereParams = $builder->getParams();
        }
        return [$whereClause, $whereParams];
    }


    /**
     * Get query fragments dependent on 'TermClose' filter
     *
     * @param Collection|int[] $termIds    (can also be NULL, but storm doesn't like that type-hint)
     * @param string           $dateColumn ['due_date'|'close_date']
     * @return string[]
     * @throws Filter\BrokenFilterException If filter cannot be constructed
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function getClauseTerm(?Collection $termIds, $dateColumn)
    {
        // Is this filter being applied ?
        if (null === $termIds) {
            return ['', 'TRUE', []];
        }

        if (!in_array($dateColumn, [self::DATE_COL_DUE, self::DATE_COL_CLS], true)) {
            throw new Filter\BrokenFilterException('Term', "Unrecognised dateColumn: $dateColumn");
        }

        // Term alias
        $t = $dateColumn === self::DATE_COL_DUE ? 'td' : 'tc';

        // Note the +1 for the actual finish date. This is because `term.actual_finish` is date only, whereas assessment dates
        // include a time component. If assessment is due at 11pm on the 10th, and term finishes on the 10th, we want to match
        // all assessments that are due before the end of the 10th, which is the start of the 11th, ie 'YYYY/MM/11 00:00:00'
        $joinClause = "
        JOIN term $t ON (a.$dateColumn >= $t.start  AND ($t.actual_finish IS NULL OR a.$dateColumn < DATE_ADD($t.actual_finish, INTERVAL 1 DAY)))";

        $builder = $this->getMapper()->getPredicateBuilder();
        $builder->in("$t.id", $termIds->getAll());

        return [$joinClause, implode(' AND ', $builder->getWhere()), $builder->getParams() ];
    }


    /**
     * Get query fragments dependent on 'YearDue' filter
     *
     * @param Collection|int[] $years      (can also be NULL, but storm doesn't like that type-hint)
     * @param string           $dateColumn ['due_date'|'close_date']
     * @return string[]
     * @throws Filter\BrokenFilterException If filter cannot be constructed
     */
    private function getClauseYear(?Collection $years, $dateColumn)
    {
        // Is this filter being applied ?
        if (null === $years) {
            return ['TRUE', []];
        }

        if (!in_array($dateColumn, [self::DATE_COL_DUE, self::DATE_COL_CLS], true)) {
            throw new Filter\BrokenFilterException('YearDue', "Unrecognised dateColumn: $dateColumn");
        }

        return ["YEAR(a.$dateColumn) IN (" . implode(',', $years->getAll()) . ')', []];
    }


    /**
     * Get query fragments dependent on 'UnitImpl' filter
     *
     * @param null|Collection|int[] $unitImplIds
     * @return mixed[]
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function getClauseUnitImpl(Collection $unitImplIds = null)
    {
        if (null === $unitImplIds || !$unitImplIds->count()) {
            $whereClause = 'TRUE';
            $whereParams = [];
        } else {
            $ids = $unitImplIds->getAll();
            if (array_key_exists(NullableContext\Definition::KEY_NONE, $ids)) {
                unset($ids[NullableContext\Definition::KEY_NONE]);
            }
            $builder = $this->getMapper()->getPredicateBuilder();
            $builder->in('ui.id', $ids);
            $whereClause = $builder->getWhere()[0];
            $whereParams = $builder->getParams();
        }
        return [$whereClause, $whereParams];
    }


    /**
     * Get query fragments dependent on 'UnitImportSpec' filter
     *
     * @param null|Collection|int[] $unitImpSpcIds
     * @return mixed[]
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function getClauseUnitImplSpec(Collection $unitImpSpcIds = null)
    {
        if (null === $unitImpSpcIds || !$unitImpSpcIds->count()) {
            $joinClause  = '';
            $whereClause = 'TRUE';
            $whereParams = [];
        } else {
            $ids = $unitImpSpcIds->getAll();
            if (array_key_exists(NullableContext\Definition::KEY_NONE, $ids)) {
                unset($ids[NullableContext\Definition::KEY_NONE]);
            }
            $builder = $this->getMapper()->getPredicateBuilder();
            $builder->in('uis.id', $ids);
            $joinClause  = 'JOIN course_unit_specification uis ON ui.course_unit_specification_id = uis.id';
            $whereClause = $builder->getWhere()[0];
            $whereParams = $builder->getParams();
        }
        return [$joinClause, $whereClause, $whereParams];
    }


    /**
     * Get query fragments dependent on 'UnitSpec' filter
     *
     * @param null|Collection|int[] $unitSpecIds
     * @return mixed[]
     * @throws InvalidColumnException
     * @throws UnderflowException
     */
    private function getClauseUnitSpec(Collection $unitSpecIds = null)
    {
        if (null === $unitSpecIds || !$unitSpecIds->count()) {
            $whereClause = 'TRUE';
            $whereParams = [];
        } else {
            $ids = $unitSpecIds->getAll();
            if (array_key_exists(NullableContext\Definition::KEY_NONE, $ids)) {
                unset($ids[NullableContext\Definition::KEY_NONE]);
            }
            $builder = $this->getMapper()->getPredicateBuilder();
            $builder->in('us.id', $ids);
            $whereClause = $builder->getWhere()[0];
            $whereParams = $builder->getParams();
        }
        return [$whereClause, $whereParams];
    }


    /**
     * Get query fragments dependent on 'Weighting' filter
     *
     * @param Collection|string[] $weightings (can also be NULL but storm doesn't like iterating that type-hint)
     * @return mixed[]
     * @throws InvalidColumnException
     */
    private function getClauseWeighting(Collection $weightings = null)
    {
        // If no weighting types are selected, don't filter
        if (null === $weightings) {
            return ['TRUE', []];
        }

        $builder = $this->getMapper()->getPredicateBuilder();

        foreach ($weightings as $weightingType) {
            if (WeightingType\Definition::KEY_WEIGHTED === $weightingType) {
                $builder->notEquals('a.weight', 0);
            } else {
                $builder->equals('a.weight', 0);
            }
        }
        return ['(' . implode(' OR ', $builder->getWhere()) . ')', $builder->getParams()];
    }


    /**
     * Get query fragments dependent on 'TemporalState' filter
     *
     * @param Collection|string[] $temporalStates (can also be NULL but storm doesn't like iterating that type-hint)
     * @return mixed[]
     * @throws UnexpectedValueException
     */
    private function getClauseTemporalState(Collection $temporalStates = null): array
    {
        // If no options are selected, don't filter
        if (null === $temporalStates) {
            return ['TRUE', [], ''];
        }

        /* We cannot use the MySQL 'BETWEEN' operator as that includes both limits.
         * To ensure distinct subsets, we need to treat one limit as inclusive, the other as exclusive
         */

        $now     = Alaress_Date_Format::now()->getDatabase();
        $orParts = [];

        $joinParticipantPlan = false;
        foreach ($temporalStates as $temporalState) {
            switch ($temporalState) {
                case TemporalState\Definition::KEY_UNPUBLISHED:
                    $orParts[] = '(:now < a.publish_date)';
                    break;
                case TemporalState\Definition::KEY_UPCOMING:
                    $orParts[] = '(a.publish_date <= :now AND :now < a.open_date)';
                    break;
                case TemporalState\Definition::KEY_OPEN:
                    $orParts[] = '(a.open_date <= :now AND :now < a.due_date)';
                    break;
                case TemporalState\Definition::KEY_OVERDUE:
                    $orParts[] = '(a.due_date <= :now AND :now < a.close_date)';
                    break;
                case TemporalState\Definition::KEY_CLOSED:
                    $orParts[] = '(a.close_date <= :now)';
                    break;
                case TemporalState\Definition::KEY_CUSTOM_UNPUBLISHED:
                    $joinParticipantPlan = true;
                    $orParts[]           = '(:now < COALESCE(pp.publish_date, a.publish_date))';
                    break;
                case TemporalState\Definition::KEY_CUSTOM_UPCOMING:
                    $joinParticipantPlan = true;
                    $orParts[]           = '(COALESCE(pp.publish_date, a.publish_date) <= :now AND :now < COALESCE(pp.open_date, a.open_date))';
                    break;
                case TemporalState\Definition::KEY_CUSTOM_OPEN:
                    $joinParticipantPlan = true;
                    $orParts[]           = '(COALESCE(pp.open_date, a.open_date) <= :now AND :now < COALESCE(pp.due_date, a.due_date))';
                    break;
                case TemporalState\Definition::KEY_CUSTOM_OVERDUE:
                    $joinParticipantPlan = true;
                    $orParts[]           = '(COALESCE(pp.due_date, a.due_date) <= :now AND :now < COALESCE(pp.close_date, a.close_date))';
                    break;
                case  TemporalState\Definition::KEY_CUSTOM_CLOSED:
                    $joinParticipantPlan = true;
                    $orParts[]           = '(COALESCE(pp.close_date, a.close_date) <= :now)';
                    break;
                default:
                    throw new UnexpectedValueException("Unrecognised temporal state filter key: '{$temporalState}");
            }
        }

        $participantPlanJoin = $joinParticipantPlan ? 'LEFT JOIN participant_plan pp ON pp.assessment_id = a.id' : '';

        return ['(' . implode(' OR ', $orParts) . ')', [':now' => $now], $participantPlanJoin];
    }


    /**
     * Get query fragments dependent on 'WorkType' filter
     *
     * @param Collection|int[] $workTypeIds
     * @return mixed[]
     * @throws InvalidColumnException
     */
    private function getClauseWorkType(Collection $workTypeIds = null)
    {
        // If no workTypes selected, don't filter
        if (null === $workTypeIds) {
            return ['TRUE', []];
        }

        $builder = $this->getMapper()->getPredicateBuilder();

        // There are two ways to specify that you want assessments without a workType.
        // The first is by passing an empty Collection, the latter is by inserting NULL in that collection.
        // Need to support both.
        if (!$workTypeIds->count()) {
            $builder->isNull('a.work_type_id');
        }

        // If Collection is empty, this will never execute - so no need to wrap
        foreach ($workTypeIds as $workTypeId) {
            if (null === $workTypeId) {
                $builder->isNull('a.work_type_id');
            } else {
                $builder->equals('a.work_type_id', $workTypeId);
            }
        }
        return ['(' . implode(' OR ', $builder->getWhere()) . ')', $builder->getParams()];
    }


    /**
     * Return a SQL clause fragment that determines whether soft-deleted assessments are filtered off
     *
     * @param bool $deleted
     * @return string[]
     * @throws InvalidColumnException
     */
    private function getClauseDeleted($deleted)
    {
        if (false !== $deleted) {
            return ['TRUE'];
        }
        // Exclude deleted assessments
        $builder = $this->getMapper()->getPredicateBuilder();
        $builder->isNull('a.deleted_at');
        return [$builder->getWhere()[0]];
    }


    /**
     * eg, an assessment can not live directly in a unit-impl and unit-spec at the same time.
     *
     * @inheritdoc
     */
    protected function doFiltersGuaranteeEmptySet(array $completeFilters): bool
    {
        // Examine the context-filters
        if ($this->doContextFiltersGuaranteeEmptySet($completeFilters)) {
            return true;
        }

        // Examine the non-context-filters
        if ($this->doNonContextFiltersGuaranteeEmptySet($completeFilters)) {
            return true;
        }

        return false;
    }


    /**
     * @inheritDoc
     */
    protected function simplifyFilters(array $completeFilters): array
    {
        // Get handles to the interrelated filters.
        $assessmentTypes = $completeFilters[AssessmentType\Definition::class];
        $rubricUsage     = $completeFilters[RubricUsage\Definition::class];
        $markPerCap      = $completeFilters[MarkPerCap\Definition::class];

        // Are we filtering to specific assessment types?
        if ($assessmentTypes instanceof Collection) {
            // And also filtering to assessments that either:
            // 1. Use specific rubric components, or
            // 2. Have mark-per-cap ON/OFF (but not NULL)
            if (
                ($rubricUsage instanceof Collection && !$rubricUsage->keyExists(RubricUsage\Definition::KEY_NONE)) ||
                ($markPerCap instanceof Collection && !$markPerCap->keyExists(MarkPerCap\Definition::KEY_NONE))
            ) {
                // We can ignore the assessment type filter (the usage/mark-per-cap filter implies a sub-set of due-works)
                // This is a relatively substantial optimisation as we can avoid the LEFT joins to large assessment tables in all 8 sub-queries
                $assessmentTypes = null;
            }
        }

        if ($assessmentTypes instanceof Collection && !$assessmentTypes->keyExists(AssessmentType\Definition::KEY_DUE_WORK)) {
            // Filtering to [!DW ∩ some usage combo)]?
            if ($rubricUsage instanceof Collection) {
                // Can be simplified to just [!DW]
                $rubricUsage = null;
            }
            // Filtering to [!DW ∩ some mark-per-cap combo]?
            if ($markPerCap instanceof Collection) {
                // Can be simplified to just [!DW]
                $markPerCap = null;
            }
        }

        // Filtering to [DW ∩ mark-per-cap = ON|OFF]
        if (
            $assessmentTypes instanceof Collection &&
            $markPerCap instanceof Collection &&
            $assessmentTypes->count() === 1 &&
            $assessmentTypes->keyExists(AssessmentType\Definition::KEY_DUE_WORK) &&
            !$markPerCap->keyExists(MarkPerCap\Definition::KEY_NONE)
        ) {
            // DW check is redundant, [mark-per-cap = ON|OFF] is complete sub-set
            $assessmentTypes = null;
        }

        // Filtering to intersection of rubric usage and mark-per-cap, and both include the NULL option?
        if (
            $rubricUsage instanceof Collection &&
            $markPerCap instanceof Collection &&
            $markPerCap->keyExists(MarkPerCap\Definition::KEY_NONE) &&
            $rubricUsage->keyExists(RubricUsage\Definition::KEY_NONE)
        ) {
            // Filtering to just assessment with no rubric usage?
            if ($markPerCap->count() === 1) {
                // Simplify to [no rubric usage]
                $rubricUsage = null;
            } // Filtering to just assessments with no relevant mark-per-cap attribute?
            elseif ($rubricUsage->count() === 1) {
                // Simplify to [NULL mark-per-cap]
                $markPerCap = null;
            }
        }

        // Insert possibly simplified filters back in filter set
        $completeFilters[AssessmentType\Definition::class] = $assessmentTypes;
        $completeFilters[RubricUsage\Definition::class]    = $rubricUsage;
        $completeFilters[MarkPerCap\Definition::class]     = $markPerCap;

        return $completeFilters;
    }


    /**
     * Can examination of the filters using set-theory enable us to conclude that no assessments could ever match?
     *
     * eg, an assessment can not live directly in a unit-impl and unit-spec at the same time.
     *
     * @param int[][]|null[] $completeFilters
     * @return bool
     */
    protected function doContextFiltersGuaranteeEmptySet(array $completeFilters)
    {
        // Course-import folder (ie either direct or indirectly associated)
        $courseImportFolderIds = $completeFilters[Folder\CourseImports\Definition::class];

        // Did call explicitly request assessments that do not have an ultimate folder?
        if ($courseImportFolderIds instanceof Collection && !$courseImportFolderIds->count()) {
            // All assessments have an ultimate folder
            return true;
        }

        // Teacher folder (ie either direct or indirectly associated)
        $teacherFolderIds = $completeFilters[Folder\TeacherInvolved\Definition::class];

        // Did call explicitly request assessments that do not have an teacher folder?
        if ($teacherFolderIds instanceof Collection && !$teacherFolderIds->count()) {
            // All assessments have an ultimate folder
            return true;
        }

        // Student folder (ie either direct or indirectly associated)
        $studentFolderIds = $completeFilters[Folder\StudentInteractions\Definition::class];

        // Did call explicitly request assessments that do not have an student folder?
        if ($studentFolderIds instanceof Collection && !$studentFolderIds->count()) {
            // All assessments have an ultimate folder
            return true;
        }

        $unitImplIds = $completeFilters[NullableContext\UnitImpl\Definition::class];
        $unitSpecIds = $completeFilters[NullableContext\UnitSpec\Definition::class];

        // Did caller request assessments that live in unit-impls and unit-specs simultaneously?
        if ($unitImplIds instanceof Collection && $unitSpecIds instanceof Collection && $unitImplIds->count() && $unitSpecIds->count()) {
            // Assessments cannot live in both a unitImpl and unitSpec at the same time.
            return true;
        }

        // Did caller specify assessment must reside directly in some unit-spec, and also be
        // directly in a unit-impl (that was imported from some spec)?
        $unitImportSpecIds = $completeFilters[NullableContext\UnitImportSpec\Definition::class];
        if ($unitImportSpecIds instanceof Collection && $unitSpecIds instanceof Collection && $unitImportSpecIds->count() && $unitSpecIds->count()) {
            return true;
        }


        // Did caller request assessment that live inside unit-impls that were not imported, or were imported from unit-specs
        // but don't live in a unit-spec?
        if ($unitImplIds instanceof Collection && $unitImportSpecIds instanceof Collection && (bool)$unitImplIds->count() !== (bool)$unitImportSpecIds->count()) {
            return true;
        }

        // Unable to conclude that result set is guaranteed to be empty
        return false;
    }


    /**
     * Can examination of the non-context filters using set-theory enable us to conclude that no results will ever be returned?
     *
     * @param int[][]|null[] $completeFilters
     * @return bool
     */
    protected function doNonContextFiltersGuaranteeEmptySet(array $completeFilters): bool
    {
        // Did call explicitly request assessments that do not have an assessment type?
        $assessmentTypes = $completeFilters[AssessmentType\Definition::class];
        if ($assessmentTypes instanceof Collection && !$assessmentTypes->count()) {
            // All assessments have a type
            return true;
        }

        // Did call explicitly request assessments due in some term, but no terms supplied?
        $termsDue = $completeFilters[Term\Due\Definition::class];
        if ($termsDue instanceof Collection && !$termsDue->count()) {
            // All assessments have due dates
            return true;
        }

        // Did call explicitly request assessments close in some term, but no terms supplied?
        $termsClose = $completeFilters[Term\Close\Definition::class];
        if ($termsClose instanceof Collection && !$termsClose->count()) {
            // All assessments have have a close date
            return true;
        }

        // Note: It is valid to request assessments without a workType (it is nullable)


        // Did call explicitly request assessments with a weightingType, but no weightingTypes supplied?
        $weightingType = $completeFilters[WeightingType\Definition::class];
        if ($weightingType instanceof Collection && !$weightingType->count()) {
            // All assessments have a weighting type
            return true;
        }

        // Did caller explicitly request assessments due in some year/s, but no years supplied?
        $yearsDue = $completeFilters[YearDue\Definition::class];
        if ($yearsDue instanceof Collection && !$yearsDue->count()) {
            // All assessments are due in some year
            return true;
        }

        $rubricUsage = $completeFilters[RubricUsage\Definition::class];
        $markPerCap  = $completeFilters[MarkPerCap\Definition::class];

        // The full set of capability/indicator keys includes a 'none' key, so the full set is essentially [no usages, specific usages ...].
        // This means the full set matches ALL assessments, and hence the empty set matches NO assessments.
        if ($rubricUsage instanceof Collection && !$rubricUsage->count()) {
            return true;
        }

        // The full set of markPerCap keys includes a 'none' key, so the full set is essentially [no rubric, markPerCap ON, markPerCap OFF].
        // This means the full set matches ALL assessments, and hence the empty set matches NO assessments.
        if ($markPerCap instanceof Collection && !$markPerCap->count()) {
            return true;
        }

        // Intersection: AssessmentType + RubricUsage.
        // Not a DueWork && Specific Rubric Usage/s?
        if (
            $assessmentTypes instanceof Collection &&
            $rubricUsage instanceof Collection &&
            !$assessmentTypes->keyExists(AssessmentType\Definition::KEY_DUE_WORK) &&
            !$rubricUsage->keyExists(RubricUsage\Definition::KEY_NONE)
        ) {
            return true;
        }

        // Intersection: AssessmentType + MarkPerCap.
        // Not a DueWork && MarkPerCap set?
        if (
            $assessmentTypes instanceof Collection &&
            $markPerCap instanceof Collection &&
            !$assessmentTypes->keyExists(AssessmentType\Definition::KEY_DUE_WORK) &&
            !$markPerCap->keyExists(MarkPerCap\Definition::KEY_NONE)
        ) {
            return true;
        }

        // Intersection: RubricUsage + MarkPerCap.
        if ($rubricUsage instanceof Collection && $markPerCap instanceof Collection) {
            // No rubric usage && using mark per cap?
            if (
                $rubricUsage->count() === 1 &&
                $rubricUsage->keyExists(RubricUsage\Definition::KEY_NONE) &&
                !$markPerCap->keyExists(MarkPerCap\Definition::KEY_NONE)
            ) {
                return true;
            }

            // Specific rubric usage/s & markPerCap not set?
            if (
                !$rubricUsage->keyExists(RubricUsage\Definition::KEY_NONE) &&
                $markPerCap->count() === 1 &&
                $markPerCap->keyExists(MarkPerCap\Definition::KEY_NONE)
            ) {
                return true;
            }
        }

        // Did call explicitly request assessments belongs to folder with some year-level relation, but no relations found?
        // Note that 'none' is an explicit option, and must be present to match folders without any year-level associations
        $yearLevels = $completeFilters[FolderYearLevel\Definition::class];
        if ($yearLevels instanceof Collection && !$yearLevels->count()) {
            return true;
        }

        // Unable to conclude that result set is guaranteed to be empty
        return false;
    }


    /**
     * Augments imported unit specs with the units specs within imported course
     * specs.
     *
     * @param Collection|null $courseImpSpcIds Selected values (if any) from the
     *                                         course spec import filter
     *                                         `NullableContext\CourseImportSpec\Definition`
     * @param Collection|null $unitImpSpcIds   Selected values (if any) from the
     *                                         unit spec import filter
     *                                         `NullableContext\UnitImportSpec\Definition`
     *
     * @return Collection|null A selection of unit spec imports.
     *                         If both `$courseImpSpcIds` and `$unitImpSpcIds`
     *                         exist: the intersection of the units calculated
     *                         from `$courseImpSpcIds`, and the units from
     *                         `$unitImpSpcIds`.
     *                         If `$unitImpSpcIds` doesn't exist: this is the
     *                         units calculated from `$courseImpSpcIds`.
     *                         If `$courseImpSpcIds` doesn't exist: this is
     *                         `$unitImpSpcIds`.
     */
    private function augmentUnitImplSpecIds(?Collection $courseImpSpcIds, ?Collection $unitImpSpcIds): ?Collection
    {
        if ($courseImpSpcIds instanceof Collection) {

            // Build the list of course spec IDs to pass into the query
            $queryParams         = [];
            $courseImpUnitSpcIds = new Collection();

            foreach ($courseImpSpcIds as $courseImpSpcId => $value) {
                // Is this the special "NONE" selection?
                // Set it directly into the course impl unit specs
                if ($courseImpSpcId === NullableContext\Definition::KEY_NONE) {
                    $courseImpUnitSpcIds[$courseImpSpcId] = $value;
                    continue;
                }
                $queryParams[] = $courseImpSpcId;
            }

            if (!empty($queryParams)) {
                // Find the list of unit specs that are in these course specs
                $findUnitsQuery = $this->getMapper()->getDatabase()->prepare(
                    sprintf(
                        'SELECT id FROM course_unit_specification where course_specification_id IN (%s)',
                        implode(',', array_fill(0, count($queryParams), '?'))
                    )
                );
                $findUnitsQuery->execute($queryParams);

                foreach ($findUnitsQuery->fetchAll(\PDO::FETCH_COLUMN) as $unitImpSpcId) {
                    $courseImpUnitSpcIds[$unitImpSpcId] = $unitImpSpcId;
                }
            }

            // If there's already unit impl spec IDs: the list is the intersect
            // Otherwise it's the list I built
            if ($unitImpSpcIds instanceof Collection) {
                $unitImpSpcIds = $unitImpSpcIds->intersect($courseImpUnitSpcIds);
            } else {
                $unitImpSpcIds = $courseImpUnitSpcIds;
            }
        }
        return $unitImpSpcIds;
    }
}
