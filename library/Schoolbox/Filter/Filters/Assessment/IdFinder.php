<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\Term as TermFilter;
use Schoolbox\Filter\Filters\Assessment\WorkType as WorkTypeFilter;


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
 * @method static IdFinder getInstance(\Schoolbox\Filter\Mapper $filterMapper, \Schoolbox\IMapper $mapper)
 */
class IdFinder extends Filter\AbstractIdFinder
{

    // Which date column does the term filter relate to?
    const DATE_COL_DUE = 'due_date';
    const DATE_COL_CLS = 'close_date';


    /**
     * Different sub-queries are required depending on which assessment-contexts filters are specified
     * Each sub-queries are modified depending on which non-assessment-context filters are specified
     *
     * @inheritdoc
     * @throws Filter\BrokenFilterException If filter SQL fragment cannot be built
     * @throws \InvalidArgumentException If no filter name injected when building a BrokenFilterException
     */
    protected function buildQuery(array $completeFilters, $deleted)
    {
        // Filters which effect which sub-queries are required
        $teacherFolderIds = $completeFilters[Folder\TeacherInvolved\Definition::class];
        $studentFolderIds = $completeFilters[Folder\StudentInteractions\Definition::class];
        $projectIds       = $completeFilters[NullableContext\Project\Definition::class];
        $unitImplIds      = $completeFilters[NullableContext\UnitImpl\Definition::class];
        $unitSpecIds      = $completeFilters[NullableContext\UnitSpec\Definition::class];
        $unitImpSpcIds    = $completeFilters[NullableContext\UnitImportSpec\Definition::class];

        list($folderWhere, $folderParams) = $this->getClauseFolder($teacherFolderIds, $studentFolderIds);
        list($projectWhere, $projectParams) = $this->getClauseProject($projectIds);
        list($unitImplWhere, $unitImplParams) = $this->getClauseUnitImpl($unitImplIds);
        list($unitSpecWhere, $unitSpecParams) = $this->getClauseUnitSpec($unitSpecIds);

        // This filter affects which sub-queries are used, and also potentially modifies the unit-impl-context sub-queries
        list($uImpSpcJoin, $uImpSpcWhere, $uImpSpcParams) = $this->getClauseUnitImplSpec($unitImpSpcIds);

        // Sub-query modifiers
        $aTypes         = $completeFilters[AssessmentType\Definition::class];
        $temporalStates = $completeFilters[TemporalState\Definition::class];
        $termsDue       = $completeFilters[TermFilter\Due\Definition::class];
        $termsClose     = $completeFilters[TermFilter\Close\Definition::class];
        $weightings     = $completeFilters[WeightingType\Definition::class];
        $workTypeIds    = $completeFilters[WorkTypeFilter\Definition::class];
        $yearsDue       = $completeFilters[YearDue\Definition::class];

        list($aTypeJoin, $aTypeWhere)               = $this->getClauseAType($aTypes);
        list($temporalWhere, $temporalParams)       = $this->getClauseTemporalState($temporalStates);
        list($termDJoin, $termDWhere, $termDParams) = $this->getClauseTerm($termsDue, self::DATE_COL_DUE);
        list($termCJoin, $termCWhere, $termCParams) = $this->getClauseTerm($termsClose, self::DATE_COL_CLS);
        list($weightingWhere, $weightingParams)     = $this->getClauseWeighting($weightings);
        list($workTypeWhere,  $workTypeParams)      = $this->getClauseWorkType($workTypeIds);
        list($yearDueWhere, $yearDueParams)         = $this->getClauseYear($yearsDue, self::DATE_COL_DUE);
        list($deletedWhere)                         = $this->getClauseDeleted($deleted);

        // Merge all the params
        $params = array_merge(
            $folderParams,
            $projectParams,
            $unitImplParams,
            $unitSpecParams,
            $uImpSpcParams,
            $temporalParams,
            $termDParams,
            $termCParams,
            $weightingParams,
            $workTypeParams,
            $yearDueParams
        );

        $subQueries = [];
        if ($this->subQueryBase($unitImpSpcIds, $teacherFolderIds, $studentFolderIds, $projectIds, $unitImplIds, $unitSpecIds)) {
            $subQueries[] = "
                SELECT a.id, 0 AS 'case' FROM `assessment` a
                {$aTypeJoin} {$termDJoin} {$termCJoin}
                WHERE {$deletedWhere} AND {$temporalWhere} AND {$termDWhere} AND {$termCWhere} AND {$weightingWhere}
                AND {$workTypeWhere} AND {$yearDueWhere}
                ";
        } else {
            if ($this->subQueryFolder($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)) {
                $folderJoin   = count($folderParams) ? 'JOIN folder f ON af.folder_id = f.id' : '';
                $subQueries[] = "
                    SELECT a.id, 1 AS 'case' FROM `assessment` a
                    JOIN assessment_folder af ON af.assessment_id = a.id
                    {$folderJoin} {$aTypeJoin} {$termDJoin} {$termCJoin}
                    WHERE {$deletedWhere} AND {$folderWhere} AND {$aTypeWhere} AND {$temporalWhere} AND {$termDWhere}
                    AND {$termCWhere} AND {$weightingWhere} AND {$workTypeWhere} AND {$yearDueWhere}
                    ";
            }
            if ($this->subQueryProjectFolder($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)) {
                $folderJoin   = count($folderParams) ? 'JOIN folder f ON paf.folder_id = f.id' : '';
                $subQueries[] = "
                    SELECT a.id, 2 AS 'case' FROM `assessment` a
                    JOIN project_assessment ap ON ap.assessment_id  = a.id
                    JOIN project p             ON ap.project_id     = p.id
                    JOIN assessment_folder paf ON paf.assessment_id = p.assessment_id
                    {$folderJoin} {$aTypeJoin} {$termDJoin} {$termCJoin}
                    WHERE {$deletedWhere} AND {$folderWhere} AND {$projectWhere} AND {$aTypeWhere} AND {$temporalWhere}
                    AND {$termDWhere} AND {$termCWhere} AND {$weightingWhere} AND {$workTypeWhere} AND {$yearDueWhere}
                    ";
            }
            if ($this->subQueryUnitImpl($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)) {
                $folderJoin   = count($folderParams)
                    ? 'JOIN course_implementation ci ON ui.course_implementation_id = ci.id
                       JOIN folder f                 ON ci.folder_id                = f.id'
                    : '';
                $subQueries[] = "
                    SELECT a.id, 3 AS 'case' FROM `assessment` a
                    JOIN assessment_course_unit_implementation aui ON aui.assessment_id = a.id
                    JOIN course_unit_implementation ui             ON aui.course_unit_implementation_id = ui.id
                    {$uImpSpcJoin} {$folderJoin} {$aTypeJoin} {$termDJoin} {$termCJoin}
                    WHERE {$deletedWhere} AND {$uImpSpcWhere} AND {$folderWhere} AND {$unitImplWhere}
                    AND {$aTypeWhere} AND {$temporalWhere} AND {$termDWhere} AND {$termCWhere}
                    AND {$weightingWhere} AND {$workTypeWhere} AND {$yearDueWhere}
                    
                    ";
            }
            if ($this->subQueryProjectUnitImpl($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)) {
                $folderJoin   = count($folderParams)
                    ? 'JOIN course_implementation ci ON ui.course_implementation_id = ci.id
                       JOIN folder f                 ON ci.folder_id                = f.id'
                    : '';
                $subQueries[] = "
                    SELECT a.id, 4 AS 'case' FROM `assessment` a
                    JOIN project_assessment ap                     ON ap.assessment_id                  = a.id
                    JOIN project p                                 ON ap.project_id                     = p.id 
                    JOIN assessment_course_unit_implementation aui ON aui.assessment_id                 = p.assessment_id
                    JOIN course_unit_implementation ui             ON aui.course_unit_implementation_id = ui.id
                    {$uImpSpcJoin} {$folderJoin} {$aTypeJoin} {$termDJoin} {$termCJoin}
                    WHERE {$deletedWhere} AND {$uImpSpcWhere} AND {$folderWhere} AND {$projectWhere} AND {$unitImplWhere}
                    AND {$aTypeWhere} AND {$temporalWhere} AND {$termDWhere} AND {$termCWhere}
                    AND {$weightingWhere} AND {$workTypeWhere} AND {$yearDueWhere}
                    ";
            }
            if ($this->subQueryUnitSpec($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)) {
                $folderJoin   = count($folderParams) ? 'JOIN folder f ON us.folder_id = f.id' : '';
                $subQueries[] = "
                    SELECT a.id, 5 AS 'case' FROM `assessment` a
                    JOIN assessment_course_unit_specification aus ON aus.assessment_id = a.id
                    JOIN course_unit_specification us ON aus.course_unit_specification_id = us.id
                    {$folderJoin} {$aTypeJoin} {$termDJoin} {$termCJoin}
                    WHERE {$deletedWhere} AND {$folderWhere} AND {$unitSpecWhere}
                    AND {$aTypeWhere} AND {$temporalWhere} AND {$termDWhere} AND {$termCWhere}
                    AND {$weightingWhere} AND {$workTypeWhere} AND {$yearDueWhere}
                    ";
            }
            if ($this->subQueryProjectUnitSpec($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)) {
                $folderJoin   = count($folderParams) ? 'JOIN folder f ON us.folder_id = f.id' : '';
                $subQueries[] = "
                    SELECT a.id, 6 AS 'case' FROM `assessment` a
                    JOIN project_assessment ap                     ON ap.assessment_id  = a.id
                    JOIN project p                                 ON ap.project_id     = p.id
                    JOIN assessment_course_unit_specification aus  ON aus.assessment_id = p.assessment_id
                    JOIN course_unit_specification us              ON aus.course_unit_specification_id = us.id
                    {$folderJoin} {$aTypeJoin} {$termDJoin} {$termCJoin}
                    WHERE {$deletedWhere} AND {$folderWhere} AND {$projectWhere} AND {$unitSpecWhere}
                    AND {$aTypeWhere} AND {$temporalWhere} AND {$termDWhere} AND {$termCWhere}
                    AND {$weightingWhere} AND {$workTypeWhere} AND {$yearDueWhere}
                    ";
            }
        }
        return [implode("\n\n\tUNION\n\n", $subQueries), $params];
    }


    /**
     * Include (#0) 'base' sub-query? (and just the base query)
     *
     * This will only be true if no context filters are specified
     *
     * @param null|Collection|int[] $uImpSpcIds
     * @param null|Collection|int[] $teacherFolderIds
     * @param null|Collection|int[] $studentFolderIds
     * @param null|Collection|int[] $projectIds
     * @param null|Collection|int[] $unitImplIds
     * @param null|Collection|int[] $unitSpecIds
     * @return bool
     */
    private function subQueryBase(
        Collection $uImpSpcIds = null,
        Collection $teacherFolderIds = null,
        Collection $studentFolderIds = null,
        Collection $projectIds = null,
        Collection $unitImplIds = null,
        Collection $unitSpecIds = null
    ) {
        return
            null === $uImpSpcIds &&
            null === $teacherFolderIds &&
            null === $studentFolderIds &&
            null === $projectIds &&
            null === $unitImplIds &&
            null === $unitSpecIds;
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
        Collection $uImpSpcIds = null,
        Collection $projectIds = null,
        Collection $unitImplIds = null,
        Collection $unitSpecIds = null
    ) {
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
        Collection $uImpSpcIds = null,
        Collection $projectIds = null,
        Collection $unitImplIds = null,
        Collection $unitSpecIds = null
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
        Collection $uImpSpcIds = null,
        Collection $projectIds = null,
        Collection $unitImplIds = null,
        Collection $unitSpecIds = null
    ) {
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
        Collection $uImpSpcIds = null,
        Collection $projectIds = null,
        Collection $unitImplIds = null,
        Collection $unitSpecIds = null
    ) {
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
        Collection $uImpSpcIds = null,
        Collection $projectIds = null,
        Collection $unitImplIds = null,
        Collection $unitSpecIds = null
    ) {
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
        Collection $uImpSpcIds = null,
        Collection $projectIds = null,
        Collection $unitImplIds = null,
        Collection $unitSpecIds = null
    ) {
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
     * Get query fragments dependent on 'AssessmentType' filter
     *
     * @param null|Collection|int[] $aTypes
     * @return mixed[]
     */
    private function getClauseAType(Collection $aTypes = null)
    {
        // Not filtering by assessment type?
        if (null === $aTypes) {
            return ['', 'TRUE'];
        }

        $joinClause = '';
        $builder    = $this->getMapper()->getPredicateBuilder();

        // Task
        if ($aTypes->keyExists(AssessmentType\Definition::KEY_TASK)) {
            $joinClause .= '
                LEFT JOIN task tk           ON tk.assessment_id = a.id
            ';
            $builder->notNull('tk.id');
        }

        // Quiz
        if ($aTypes->keyExists(AssessmentType\Definition::KEY_QUIZ)) {
            $joinClause .= '
                LEFT JOIN assess qz         ON qz.assessment_id = a.id
            ';
            $builder->notNull('qz.assess_id');
        }

        // DueWork
        if ($aTypes->keyExists(AssessmentType\Definition::KEY_DUE_WORK)) {
            $joinClause .= '
                LEFT JOIN submission_box dw ON dw.assessment_id = a.id
            ';
            $builder->notNull('dw.id');
        }

        // Project
        if ($aTypes->keyExists(AssessmentType\Definition::KEY_PROJECT)) {
            $joinClause .= '
                LEFT JOIN project pr ON pr.assessment_id = a.id
            ';
            $builder->notNull('pr.id');
        }
        return [$joinClause, '(' . implode(' OR ', $builder->getWhere()) . ')'];

    }


    /**
     * Get query fragments dependent on 'Folder' filter
     *
     * @param null|Collection|int[] $teacherFolderIds
     * @param null|Collection|int[] $studentFolderIds
     * @return mixed[]
     */
    private function getClauseFolder(Collection $teacherFolderIds = null, Collection $studentFolderIds = null)
    {
        // Different filters are always combined using INTERSECT

        // Both NULL ?
        if (null === $teacherFolderIds && null === $studentFolderIds) {
            return ['TRUE', []];
        }

        // Just teacher folders?
        if (null === $studentFolderIds) {
            $folderIds = $teacherFolderIds->getAll();
        } // Just student folders?
        elseif (null === $teacherFolderIds) {
            $folderIds = $studentFolderIds->getAll();
        } // Both specified. Take INTERSECT
        else {
            $folderIds = array_intersect($teacherFolderIds->getAll(), $studentFolderIds->getAll());
        }

        $builder = $this->getMapper()->getPredicateBuilder();
        $builder->in('f.id', $folderIds);

        return [$builder->getWhere()[0], $builder->getParams()];
    }


    /**
     * Get query fragments dependent on 'Project' filter
     *
     * @param null|Collection|int[] $projectIds
     * @return mixed[]
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
     * @throws \InvalidArgumentException If no filter name injected when building a BrokenFilterException
     */
    private function getClauseTerm(Collection $termIds = null, $dateColumn)
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
     * @throws \InvalidArgumentException If no filter name injected when building a BrokenFilterException
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
     */
    private function getClauseTemporalState(Collection $temporalStates = null)
    {
        // If no weighting types are selected, don't filter
        if (null === $temporalStates) {
            return ['TRUE', []];
        }

        /* We cannot use the MySQL 'BETWEEN' operator as that includes both limits.
         * To ensure distinct subsets, we need to treat one limit as inclusive, the other as exclusive
         */

        $now     = \Alaress_Date_Format::now()->getDatabase();
        $orParts = [];
        foreach ($temporalStates as $temporalState) {
            if (TemporalState\Definition::KEY_UNPUBLISHED === $temporalState) {
                $orParts[] = '(:now < a.publish_date)';
            } elseif (TemporalState\Definition::KEY_UPCOMING === $temporalState) {
                $orParts[] = '(a.publish_date <= :now AND :now < a.open_date)';
            } elseif (TemporalState\Definition::KEY_OPEN === $temporalState) {
                $orParts[] = '(a.open_date <= :now AND :now < a.due_date)';
            } elseif (TemporalState\Definition::KEY_OVERDUE === $temporalState) {
                $orParts[] = '(a.due_date <= :now AND :now < a.close_date)';
            } elseif (TemporalState\Definition::KEY_CLOSED === $temporalState) {
                $orParts[] = '(a.close_date <= :now)';
            }
        }
        return ['(' . implode(' OR ', $orParts) . ')', [':now' => $now]];
    }


    /**
     * Get query fragments dependent on 'WorkType' filter
     *
     * @param Collection|int[] $workTypeIds
     * @return mixed[]
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
    protected function doFiltersGuaranteeEmptySet(array $completeFilters)
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
     * Can examination of the filters using set-theory enable us to conclude that no assessments could ever match?
     *
     * eg, an assessment can not live directly in a unit-impl and unit-spec at the same time.
     *
     * @param int[][]|null[] $completeFilters
     * @return bool
     */
    protected function doContextFiltersGuaranteeEmptySet(array $completeFilters)
    {

        // Teacher folder (ie either direct or indirectly associated)
        $teacherFolderIds = $completeFilters[Folder\TeacherInvolved\Definition::class];

        // Did call explicitly request assessments that do not have an teacher folder?
        if ($teacherFolderIds instanceOf Collection && !$teacherFolderIds->count()) {
            // All assessments have an ultimate folder
            return true;
        }

        // Student folder (ie either direct or indirectly associated)
        $studentFolderIds = $completeFilters[Folder\StudentInteractions\Definition::class];

        // Did call explicitly request assessments that do not have an student folder?
        if ($studentFolderIds instanceOf Collection && !$studentFolderIds->count()) {
            // All assessments have an ultimate folder
            return true;
        }

        $unitImplIds = $completeFilters[NullableContext\UnitImpl\Definition::class];
        $unitSpecIds = $completeFilters[NullableContext\UnitSpec\Definition::class];

        // Did caller request assessments that live in unit-impls and unit-specs simultaneously?
        if ($unitImplIds instanceOf Collection && $unitSpecIds instanceOf Collection && $unitImplIds->count() && $unitSpecIds->count()) {
            // Assessments cannot live in both a unitImpl and unitSpec at the same time.
            return true;
        }

        // Did caller specify assessment must reside directly in some unit-spec, and also be
        // directly in a unit-impl (that was imported from some spec)?
        $unitImportSpecIds = $completeFilters[NullableContext\UnitImportSpec\Definition::class];
        if ($unitImportSpecIds instanceOf Collection && $unitSpecIds instanceOf Collection && $unitImportSpecIds->count() && $unitSpecIds->count()) {
            return true;
        }


        // Did caller request assessment that live inside unit-impls that were not imported, or were imported from unit-specs
        // but don't live in a unit-spec?
        if ($unitImplIds instanceOf Collection && $unitImportSpecIds instanceOf Collection && (bool)$unitImplIds->count() !== (bool)$unitImportSpecIds->count()) {
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
    protected function doNonContextFiltersGuaranteeEmptySet(array $completeFilters)
    {
        // Did call explicitly request assessments that do not have an assessment type?
        $assessmentTypes = $completeFilters[AssessmentType\Definition::class];
        if ($assessmentTypes instanceOf Collection && !$assessmentTypes->count()) {
            // All assessments have a type
            return true;
        }

        // Did call explicitly request assessments due in some term, but no terms supplied?
        $termsDue = $completeFilters[TermFilter\Due\Definition::class];
        if ($termsDue instanceOf Collection && !$termsDue->count()) {
            // All assessments have due dates
            return true;
        }

        // Did call explicitly request assessments close in some term, but no terms supplied?
        $termsClose = $completeFilters[TermFilter\Close\Definition::class];
        if ($termsClose instanceOf Collection && !$termsClose->count()) {
            // All assessments have have a close date
            return true;
        }

        // Note: It is valid to request assessments without a workType (it is nullable)


        // Did call explicitly request assessments with a weightingType, but no weightingTypes supplied?
        $weightingType = $completeFilters[WeightingType\Definition::class];
        if ($weightingType instanceOf Collection && !$weightingType->count()) {
            // All assessments have a weighting type
            return true;
        }

        // Did caller explicitly request assessments due in some year/s, but no years supplied?
        $yearsDue = $completeFilters[YearDue\Definition::class];
        if ($yearsDue instanceof Collection && !$yearsDue->count()) {
            // All assessments are due in some year
            return true;
        }

        // Unable to conclude that result set is guaranteed to be empty
        return false;
    }

}
