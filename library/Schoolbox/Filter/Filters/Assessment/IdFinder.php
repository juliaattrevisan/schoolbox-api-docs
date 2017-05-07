<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment;

use Schoolbox\Collection;
use Schoolbox\Assessment\Term;
use Schoolbox\Learning\Assessment\WorkType as AWorkType;
use Schoolbox\Filter;


/**
 * Helper for building the query clauses.
 *
 * The overall query will be made up of 0-8 sub-queries that are combined using UNION.
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
 * * 7. In UnitImpl in UnitSpec on Folder
 * * 8. In Project  in UnitImpl in UnitSpec on Folder
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
        $folderIds     = $completeFilters[Folder\Definition::SLUG];
        $projectIds    = $completeFilters[NullableContext\Project\Definition::SLUG];
        $unitImplIds   = $completeFilters[NullableContext\UnitImpl\Definition::SLUG];
        $unitSpecIds   = $completeFilters[NullableContext\UnitSpec\Definition::SLUG];
        $unitImpSpcIds = $completeFilters[NullableContext\UnitImportSpec\Definition::SLUG];

        list($folderWhere,   $folderParams)               = $this->getClauseFolder($folderIds);
        list($projectWhere,  $projectParams)              = $this->getClauseProject($projectIds);
        list($unitImplWhere, $unitImplParams)             = $this->getClauseUnitImpl($unitImplIds);
        list($unitSpecWhere, $unitSpecParams)             = $this->getClauseUnitSpec($unitSpecIds);

        // This filter affects which sub-queries are used, and also potentially modifies the unit-impl-context sub-queries
        list($uImpSpcJoin, $uImpSpcWhere, $uImpSpcParams) = $this->getClauseUnitImplSpec($unitImpSpcIds);

        // Sub-query modifiers
        $aTypes     = $completeFilters[AssessmentType\Definition::SLUG];
        $termsDue   = $completeFilters[TermDue\Definition::SLUG];
        $termsClose = $completeFilters[TermClose\Definition::SLUG];
        $weightings = $completeFilters[WeightingType\Definition::SLUG];
        $workTypes  = $completeFilters[WorkType\Definition::SLUG];

        list($aTypeJoin, $aTypeWhere)           = $this->getClauseAType($aTypes);
        list($termDueWhere)                     = $this->getClauseTerm($termsDue, self::DATE_COL_DUE);
        list($termCloseWhere)                   = $this->getClauseTerm($termsClose, self::DATE_COL_CLS);
        list($weightingWhere, $weightingParams) = $this->getClauseWeighting($weightings);
        list($workTypeWhere,  $workTypeParams)  = $this->getClauseWorkType($workTypes);
        list($deletedWhere)                     = $this->getClauseDeleted($deleted);

        // Merge all the params
        $params = array_merge($folderParams, $projectParams, $unitImplParams, $unitSpecParams, $uImpSpcParams, $weightingParams, $workTypeParams);

        $subQueries = [];
        if($this->subQueryBase($unitImpSpcIds, $folderIds, $projectIds, $unitImplIds, $unitSpecIds)){
            $subQueries[] = "
                SELECT a.id, 0 AS 'case' FROM `assessment` a
                {$aTypeJoin}
                WHERE {$deletedWhere}
                AND {$termDueWhere} AND {$termCloseWhere} AND {$weightingWhere} AND {$workTypeWhere}
                ";
        } else {
            if($this->subQueryFolder($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)){
                $folderJoin = count($folderParams) ? 'JOIN folder f ON af.folder_id = f.id' : '';
                $subQueries[] = "
                    SELECT a.id, 1 AS 'case' FROM `assessment` a
                    JOIN assessment_folder af ON af.assessment_id = a.id
                    {$folderJoin} {$aTypeJoin}
                    WHERE {$deletedWhere} AND {$folderWhere}
                    AND {$aTypeWhere} AND {$termDueWhere} AND {$termCloseWhere} AND {$weightingWhere} AND {$workTypeWhere}
                    ";
            }
            if($this->subQueryProjectFolder($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)){
                $folderJoin = count($folderParams) ? 'JOIN folder f ON paf.folder_id = f.id' : '';
                $subQueries[] = "
                    SELECT a.id, 2 AS 'case' FROM `assessment` a
                    JOIN project_assessment ap ON ap.assessment_id  = a.id
                    JOIN project p             ON ap.project_id     = p.id
                    JOIN assessment_folder paf ON paf.assessment_id = p.assessment_id
                    {$folderJoin} {$aTypeJoin}
                    WHERE {$deletedWhere} AND {$folderWhere} AND {$projectWhere}
                    AND {$aTypeWhere} AND {$termDueWhere} AND {$termCloseWhere} AND {$weightingWhere} AND {$workTypeWhere}
                    ";
            }
            if($this->subQueryUnitImpl($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)){
                $folderJoin = count($folderParams)
                    ? 'JOIN course_implementation ci ON ui.course_implementation_id = ci.id
                       JOIN folder f                 ON ci.folder_id                = f.id'
                    : '';
                $subQueries[] = "
                    SELECT a.id, 3 AS 'case' FROM `assessment` a
                    JOIN assessment_course_unit_implementation aui ON aui.assessment_id = a.id
                    JOIN course_unit_implementation ui             ON aui.course_unit_implementation_id = ui.id
                    {$uImpSpcJoin} {$folderJoin} {$aTypeJoin}
                    WHERE {$deletedWhere} AND {$uImpSpcWhere} AND {$folderWhere} AND {$unitImplWhere}
                    AND {$aTypeWhere} AND {$termDueWhere} AND {$termCloseWhere} AND {$weightingWhere} AND {$workTypeWhere}
                    
                    ";
            }
            if($this->subQueryProjectUnitImpl($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)){
                $folderJoin = count($folderParams)
                    ? 'JOIN course_implementation ci ON ui.course_implementation_id = ci.id
                       JOIN folder f                 ON ci.folder_id                = f.id'
                    : '';
                $subQueries[] = "
                    SELECT a.id, 4 AS 'case' FROM `assessment` a
                    JOIN project_assessment ap                     ON ap.assessment_id                  = a.id
                    JOIN project p                                 ON ap.project_id                     = p.id 
                    JOIN assessment_course_unit_implementation aui ON aui.assessment_id                 = p.assessment_id
                    JOIN course_unit_implementation ui             ON aui.course_unit_implementation_id = ui.id
                    {$uImpSpcJoin} {$folderJoin} {$aTypeJoin}
                    WHERE {$deletedWhere} AND {$uImpSpcWhere} AND {$folderWhere} AND {$projectWhere} AND {$unitImplWhere}
                    AND {$aTypeWhere} AND {$termDueWhere} AND {$termCloseWhere} AND {$weightingWhere} AND {$workTypeWhere}
                    ";
            }
            if($this->subQueryUnitSpec($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)){
                $folderJoin = count($folderParams) ? 'JOIN folder f ON us.folder_id = f.id' : '';
                $subQueries[] = "
                    SELECT a.id, 5 AS 'case' FROM `assessment` a
                    JOIN assessment_course_unit_specification aus ON aus.assessment_id = a.id
                    JOIN course_unit_specification us ON aus.course_unit_specification_id = us.id
                    {$folderJoin}
                    {$aTypeJoin}
                    WHERE {$deletedWhere} AND {$folderWhere} AND {$unitSpecWhere}
                    AND {$aTypeWhere} AND {$termDueWhere} AND {$termCloseWhere} AND {$weightingWhere} AND {$workTypeWhere}
                    ";
            }
            if($this->subQueryProjectUnitSpec($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)){
                $folderJoin = count($folderParams) ? 'JOIN folder f ON us.folder_id = f.id' : '';
                $subQueries[] = "
                    SELECT a.id, 6 AS 'case' FROM `assessment` a
                    JOIN project_assessment ap                     ON ap.assessment_id  = a.id
                    JOIN project p                                 ON ap.project_id     = p.id
                    JOIN assessment_course_unit_specification aus  ON aus.assessment_id = p.assessment_id
                    JOIN course_unit_specification us              ON aus.course_unit_specification_id = us.id
                    {$folderJoin}
                    {$aTypeJoin}
                    WHERE {$deletedWhere} AND {$folderWhere} AND {$projectWhere} AND {$unitSpecWhere}
                    AND {$aTypeWhere} AND {$termDueWhere} AND {$termCloseWhere} AND {$weightingWhere} AND {$workTypeWhere}
                    ";
            }
        }
        return [implode("\n\n\tUNION\n\n", $subQueries), $params];
    }


    /**
     * @inheritdoc
     */
    protected function buildRegistry()
    {
        // Manually register instead of auto-glob, as we have to manually implement anyway.
        return [
            // Context-filters
            NullableContext\UnitImportSpec\Definition::SLUG => NullableContext\UnitImportSpec\Definition::class,
            Folder\Definition::SLUG                         => Folder\Definition::class,
            NullableContext\Project\Definition::SLUG        => NullableContext\Project\Definition::class,
            NullableContext\UnitImpl\Definition::SLUG       => NullableContext\UnitImpl\Definition::class,
            NullableContext\UnitSpec\Definition::SLUG       => NullableContext\UnitSpec\Definition::class,

            // Non-context filters
            AssessmentType\Definition::SLUG                 => AssessmentType\Definition::class,
            TermDue\Definition::SLUG                        => TermDue\Definition::class,
            TermClose\Definition::SLUG                      => TermClose\Definition::class,
            WeightingType\Definition::SLUG                  => WeightingType\Definition::class,
            WorkType\Definition::SLUG                       => WorkType\Definition::class,
        ];
    }

    /**
     * Include (#0) 'base' sub-query? (and just the base query)
     *
     * This will only be true if no context filters are specified
     *
     * @param null|Collection|int[] $uImpSpcIds
     * @param null|Collection|int[] $folderIds
     * @param null|Collection|int[] $projectIds
     * @param null|Collection|int[] $unitImplIds
     * @param null|Collection|int[] $unitSpecIds
     * @return bool
     */
    private function subQueryBase(
        Collection $uImpSpcIds = null,
        Collection $folderIds = null,
        Collection $projectIds = null,
        Collection $unitImplIds = null,
        Collection $unitSpecIds = null)
    {
        return
            null === $uImpSpcIds  &&
            null === $folderIds   &&
            null === $projectIds  &&
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
    ){
        if($projectIds instanceOf Collection && $projectIds->count() && !$projectIds->keyExists(NullableContext\Definition::KEY_NONE)){
            return false;
        }
        if($unitSpecIds instanceOf Collection && $unitSpecIds->count() && !$unitSpecIds->keyExists(NullableContext\Definition::KEY_NONE)){
            return false;
        }
        if($unitImplIds instanceOf Collection && $unitImplIds->count() && !$unitImplIds->keyExists(NullableContext\Definition::KEY_NONE)){
            return false;
        }
        if($uImpSpcIds instanceOf Collection && $uImpSpcIds->count() && !$uImpSpcIds->keyExists(NullableContext\Definition::KEY_NONE)){
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
        Collection $unitSpecIds = null)
    {
        if($projectIds instanceOf Collection && !$projectIds->count()){
            return false;
        }
        if($unitImplIds instanceOf Collection && $unitImplIds->count() && !$unitImplIds->keyExists(NullableContext\Definition::KEY_NONE)){
            return false;
        }
        if($unitSpecIds instanceOf Collection && $unitSpecIds->count() && !$unitSpecIds->keyExists(NullableContext\Definition::KEY_NONE)){
            return false;
        }
        if($uImpSpcIds instanceOf Collection && $uImpSpcIds->count() && !$uImpSpcIds->keyExists(NullableContext\Definition::KEY_NONE)){
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
        if($unitImplIds instanceOf Collection && !$unitImplIds->count()){
            return false;
        }

        if($uImpSpcIds instanceOf Collection  && !$uImpSpcIds->count()){
            return false;
        }
        if($projectIds instanceOf Collection && $projectIds->count() && !$projectIds->keyExists(NullableContext\Definition::KEY_NONE)){
            return false;
        }
        if($unitSpecIds instanceOf Collection && $unitSpecIds->count() && !$unitSpecIds->keyExists(NullableContext\Definition::KEY_NONE)){
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
        if($projectIds instanceOf Collection && !$projectIds->count()){
            return false;
        }
        if($unitImplIds instanceOf Collection && !$unitImplIds->count()){
            return false;
        }
        if($uImpSpcIds instanceOf Collection && !$uImpSpcIds->count()){
            return false;
        }
        if($unitSpecIds instanceOf Collection && $unitSpecIds->count() && !$unitSpecIds->keyExists(NullableContext\Definition::KEY_NONE)){
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
        Collection $unitSpecIds = null)
    {
        if($unitSpecIds instanceOf Collection && !$unitSpecIds->count()){
            return false;
        }
        if($unitImplIds instanceOf Collection && $unitImplIds->count() && !$unitImplIds->keyExists(NullableContext\Definition::KEY_NONE)){
            return false;
        }
        if($uImpSpcIds instanceOf Collection && $uImpSpcIds->count() && !$uImpSpcIds->keyExists(NullableContext\Definition::KEY_NONE)){
            return false;
        }
        if($projectIds instanceOf Collection && $projectIds->count() && !$projectIds->keyExists(NullableContext\Definition::KEY_NONE)){
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
        Collection $unitSpecIds = null)
    {
        if($projectIds instanceOf Collection && !$projectIds->count()){
            return false;
        }
        if($unitSpecIds instanceOf Collection && !$unitSpecIds->count()){
            return false;
        }
        if($unitImplIds instanceOf Collection && $unitImplIds->count() && !$unitImplIds->keyExists(NullableContext\Definition::KEY_NONE)){
            return false;
        }
        if($uImpSpcIds instanceOf Collection && $uImpSpcIds->count() && !$uImpSpcIds->keyExists(NullableContext\Definition::KEY_NONE)){
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
        if(null === $aTypes) {
            return ['', 'TRUE'];
        }

        $joinClause = '';
        $builder    = $this->getMapper()->getPredicateBuilder();

        // Task
        if (array_key_exists(AssessmentType\Definition::KEY_TASK, $aTypes)) {
            $joinClause .= '
                LEFT JOIN task tk           ON tk.assessment_id = a.id
            ';
            $builder->notNull('tk.id');
        }

        // Quiz
        if (array_key_exists(AssessmentType\Definition::KEY_QUIZ, $aTypes)) {
            $joinClause .= '
                LEFT JOIN assess qz         ON qz.assessment_id = a.id
            ';
            $builder->notNull('qz.assess_id');
        }

        // DueWork
        if (array_key_exists(AssessmentType\Definition::KEY_DUE_WORK, $aTypes)) {
            $joinClause .= '
                LEFT JOIN submission_box dw ON dw.assessment_id = a.id
            ';
            $builder->notNull('dw.id');
        }

        // DueWork
        if (array_key_exists(AssessmentType\Definition::KEY_PROJECT, $aTypes)) {
            $joinClause .= '
                LEFT JOIN project pr ON pr.assessment_id = a.id
            ';
            $builder->notNull('pr.id');
        }
        return [$joinClause, '('. implode(' OR ', $builder->getWhere()) .')'];

    }

    /**
     * Get query fragments dependent on 'Folder' filter
     *
     * @param null|Collection|int[] $folderIds
     * @return mixed[]
     */
    private function getClauseFolder(Collection $folderIds = null)
    {
        if(null === $folderIds){
            $whereClause = 'TRUE';
            $whereParams = [];
        } else {
            $builder = $this->getMapper()->getPredicateBuilder();
            $builder->equals('f.id', $folderIds);
            $whereClause = $builder->getWhere()[0];
            $whereParams = $builder->getParams();
        }
        return [$whereClause, $whereParams];
    }

    /**
     * Get query fragments dependent on 'Project' filter
     *
     * @param null|Collection|int[] $projectIds
     * @return mixed[]
     */
    private function getClauseProject(Collection $projectIds = null)
    {
        if(null === $projectIds || !$projectIds->count()){
            $whereClause = 'TRUE';
            $whereParams = [];
        } else {
            $ids = $projectIds->getAll();
            if(array_key_exists(NullableContext\Definition::KEY_NONE, $ids)){
                unset($ids[NullableContext\Definition::KEY_NONE]);
            }
            $builder = $this->getMapper()->getPredicateBuilder();
            $builder->equals('p.id', $ids);
            $whereClause = $builder->getWhere()[0];
            $whereParams = $builder->getParams();
        }
        return [$whereClause, $whereParams];
    }

    /**
     * Get query fragments dependent on 'TermClose' filter
     *
     * @param Collection|Term\Model[] $terms (can also be NULL, but storm doesn't like that type-hint)
     * @param string $dateColumn ['due_date'|'close_date']
     * @return string[]
     * @throws Filter\BrokenFilterException If filter cannot be constructed
     * @throws \InvalidArgumentException If no filter name injected when building a BrokenFilterException
     */
    private function getClauseTerm(Collection $terms = null, $dateColumn)
    {
        // Is this filter being applied ?
        if(NULL === $terms){
            return [' TRUE '];
        }

        if(!in_array($dateColumn, [self::DATE_COL_DUE, self::DATE_COL_CLS], true)){
            throw new Filter\BrokenFilterException('Term', "Unrecognised dataColumn: $dateColumn");
        }

        // Generate the dueDate filtering
        $anyBuilder = $this->getMapper()->getPredicateBuilder();
        foreach($terms as $term){
            $allBuilder = $this->getMapper()->getPredicateBuilder();
            $allBuilder->greaterThanOrEqualTo("a.$dateColumn", $term->getStart()->getDatabaseDate());

            if(!$term->getActualFinish()->isNull()){
                $allBuilder->lessThanOrEqualTo("a.$dateColumn", $term->getActualFinish()->getDatabaseDate());
            }
            $anyBuilder->all($allBuilder);
        }
        return ['(' . implode(' OR ', $anyBuilder->getWhere()) . ')'];
    }

    /**
     * Get query fragments dependent on 'UnitImpl' filter
     *
     * @param null|Collection|int[] $unitImplIds
     * @return mixed[]
     */
    private function getClauseUnitImpl(Collection $unitImplIds = null)
    {
        if(null === $unitImplIds || !$unitImplIds->count()){
            $whereClause = 'TRUE';
            $whereParams = [];
        } else {
            $ids = $unitImplIds->getAll();
            if(array_key_exists(NullableContext\Definition::KEY_NONE, $ids)){
                unset($ids[NullableContext\Definition::KEY_NONE]);
            }
            $builder = $this->getMapper()->getPredicateBuilder();
            $builder->equals('ui.id', $ids);
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

        if(null === $unitImpSpcIds || !$unitImpSpcIds->count()){
            $joinClause  = '';
            $whereClause = 'TRUE';
            $whereParams = [];
        } else {
            $ids = $unitImpSpcIds->getAll();
            if(array_key_exists(NullableContext\Definition::KEY_NONE, $ids)){
                unset($ids[NullableContext\Definition::KEY_NONE]);
            }
            $builder = $this->getMapper()->getPredicateBuilder();
            $builder->equals('uis.id', $ids);
            $joinClause = 'JOIN course_unit_specification uis ON ui.course_unit_specification_id = uis.id';
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
        if(null === $unitSpecIds || !$unitSpecIds->count()){
            $whereClause = 'TRUE';
            $whereParams = [];
        } else {
            $ids = $unitSpecIds->getAll();
            if(array_key_exists(NullableContext\Definition::KEY_NONE, $ids)){
                unset($ids[NullableContext\Definition::KEY_NONE]);
            }
            $builder = $this->getMapper()->getPredicateBuilder();
            $builder->equals('us.id', $ids);
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
        if(NULL === $weightings){
            return ['TRUE', []];
        }

        $builder = $this->getMapper()->getPredicateBuilder();

        foreach($weightings as $weightingType){
            if(WeightingType\Definition::KEY_WEIGHTED === $weightingType){
                $builder->notEquals('a.weight', 0);
            } else {
                $builder->equals('a.weight', 0);
            }
        }
        return ['(' . implode(' OR ', $builder->getWhere()) . ')', $builder->getParams()];
    }

    /**
     * Get query fragments dependent on 'WorkType' filter
     *
     * @param Collection|AWorkType\Model[] $workTypes
     * @return mixed[]
     */
    private function getClauseWorkType(Collection $workTypes = null)
    {
        // If no workTypes selected, don't filter
        if(NULL === $workTypes){
            return ['TRUE', []];
        }

        $builder = $this->getMapper()->getPredicateBuilder();

        // There are two ways to specify that you want assessments without a workType.
        // The first is by passing an empty Collection, the latter is by inserting NULL in that collection.
        // Need to support both.
        if(!$workTypes->count()){
            $builder->isNull('a.work_type_id');
        }

        // If Collection is empty, this will never execute - so no need to wrap
        foreach($workTypes as $workType){
            if(null === $workType){
                $builder->isNull('a.work_type_id');
            } else {
                $builder->equals('a.work_type_id', $workType->getId());
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
        if(false !== $deleted){
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
    protected function doContextFiltersGuaranteeEmptySet(array $completeFilters){

        // Ultimate folder (ie either direct or indirectly associated)
        $folderIds = $completeFilters[Folder\Definition::SLUG];

        // Did call explicitly request assessments that do not have an ultimate folder?
        if($folderIds instanceOf Collection && !$folderIds->count()){
            // All assessments have an ultimate folder
            return true;
        }

        $unitImplIds = $completeFilters[NullableContext\UnitImpl\Definition::SLUG];
        $unitSpecIds = $completeFilters[NullableContext\UnitSpec\Definition::SLUG];

        // Did caller request assessments that live in unit-impls and unit-specs simultaneously?
        if($unitImplIds instanceOf Collection && $unitSpecIds instanceOf Collection && $unitImplIds->count() && $unitSpecIds->count()){
            // Assessments cannot live in both a unitImpl and unitSpec at the same time.
            return true;
        }

        // Did caller specify assessment must reside directly in some unit-spec, and also be
        // directly in a unit-impl (that was imported from some spec)?
        $unitImportSpecIds = $completeFilters[NullableContext\UnitImportSpec\Definition::SLUG];
        if($unitImportSpecIds instanceOf Collection && $unitSpecIds instanceOf Collection && $unitImportSpecIds->count() && $unitSpecIds->count()){
            return true;
        }


        // Did caller request assessment that live inside unit-impls that were not imported, or were imported from unit-specs
        // but don't live in a unit-spec?
        if($unitImplIds instanceOf Collection && $unitImportSpecIds instanceOf Collection && (bool)$unitImplIds->count() !== (bool)$unitImportSpecIds->count()){
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
        $assessmentTypes = $completeFilters[AssessmentType\Definition::SLUG];
        if ($assessmentTypes instanceOf Collection && !$assessmentTypes->count()) {
            // All assessments have a type
            return true;
        }

        // Did call explicitly request assessments due in some term, but no terms supplied?
        $termsDue = $completeFilters[TermDue\Definition::SLUG];
        if ($termsDue instanceOf Collection && !$termsDue->count()) {
            // All assessments have due dates
            return true;
        }

        // Did call explicitly request assessments close in some term, but no terms supplied?
        $termsClose = $completeFilters[TermClose\Definition::SLUG];
        if ($termsClose instanceOf Collection && !$termsClose->count()) {
            // All assessments have have a close date
            return true;
        }

        // Note: It is valid to request assessments without a workType (it is nullable)


        // Did call explicitly request assessments with a weightingType, but no weightingTypes supplied?
        $weightingType = $completeFilters[WeightingType\Definition::SLUG];
        if ($weightingType instanceOf Collection && !$weightingType->count()) {
            // All assessments have a weighting type
            return true;
        }

        // Unable to conclude that result set is guaranteed to be empty
        return false;
    }

}
