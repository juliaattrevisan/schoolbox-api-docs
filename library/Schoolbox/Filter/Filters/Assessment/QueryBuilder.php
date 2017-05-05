<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment;

use Schoolbox\Collection;
use Schoolbox\Filter\IFilterDefinition;
use \Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Assessment\Term;


/**
 * Helper for building the query clauses.
 *
 * The overall query will be made up of 0-8 sub-queries that are UNIONed together.
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
 *  1. $ids                = $this->assessmentFilterer->findIdsByFilters($filters);
 *  2. Either of:
 *      $assessmentModels  = $assessmentMapper->findByColumn('id', $ids, $options);
 *      $assessableObjects = $assessmentMapperProxy->findByColumn('id', $ids, $options);
 *
 * @todo, once other analogous versions are required, abstract common logic into abstract and place behind interface IFilterer
 */
class QueryBuilder
{
    /** @var static[] $instance */
    protected static $instance = [];

    /**
     * A list of filter definitions this class knows how to apply
     *
     * Indexed by IFilterDefinition->getQueryKey()
     * @var string[]
     */
    private $knownFilterDefinitionRegistry = [];

    /**
     * @var Assessment\Mapper
     */
    private $assessmentMapper;



    /**
     * @param Assessment\Mapper $assessmentMapper
     */
    private function __construct(Assessment\Mapper $assessmentMapper)
    {
        $this->assessmentMapper = $assessmentMapper;
    }


    /**
     * Fetch the singleton instance of this class
     *
     * @param Assessment\Mapper $assessmentMapper
     * @return QueryBuilder
     * @throws \DomainException If any matched filter definition files are not IFilterDefinitions
     */
    public static function getInstance(Assessment\Mapper $assessmentMapper)
    {
        $className = get_called_class();
        if (!isset(self::$instance[$className])) {
            self::$instance[$className] = new static($assessmentMapper);
            self::$instance[$className]->buildRegistry();
        }
        return self::$instance[$className];
    }


    /**
     * Fetch an array of assessment IDs that match the specified filters and options
     *
     * Filters are applied using ∩ (intersection).
     *
     * For each filter:
     * * null  => don't apply filter
     * * []    => LEFT JOIN must match NULL. eg if project filter is [] then assessment does not live in project
     * * [X..] => INNER JOIN must be in X.   eg if project filter is [1] then assessments must reside directly in project_1
     *
     * @param array $filters Indexed by filter definition key
     * @param array $options Conventional mapper query modifiers
     * @see IFilterDefinition::getSlug()
     * @return int[]
     * @throws \InvalidArgumentException If any filter keys not recognised
     */
    public function findIdsByFilters(array $filters = [], array $options = [])
    {
        // No filters ?
        if (0 === count($filters)) {
            return $this->assessmentMapper->findAllIds($options);
        }

        // Filters supplied. Ensure they are recognisable.
        $this->checkValidFilterKeys($filters);

        // Ensure all filter keys so we don't need to perform repeated isset() checks
        $completeFilters = $this->normaliseFilters($filters);

        // Can we conclude filters will never permit any assessments to match? ie always an empty set?
        if ($this->doFiltersGuaranteeEmptySet($completeFilters)) {
            return [];
        }

        list($query, $params) = $this->buildQuery($completeFilters);
        $idStatement = $this->assessmentMapper->getDatabase()->query($query, $params);
        return array_column($idStatement->fetchAll(), $this->assessmentMapper->getIdentifierColumn());
    }


    /**
     * Protected so we can call from child unit-testing class
     *
     * Different sub-queries are required depending on which assessment-contexts filters are specified
     * Each sub-queries are modified depending on which non-assessment-context filters are specified
     *
     * @param array $completeFilters
     * @return mixed[]
     */
    protected function buildQuery(array $completeFilters)
    {
        // Filters which effect which sub-queries are required
        $folderIds     = $completeFilters[Folder\Definition::SLUG];
        $projectIds    = $completeFilters[Project\Definition::SLUG];
        $unitImplIds   = $completeFilters[UnitImpl\Definition::SLUG];
        $unitSpecIds   = $completeFilters[UnitSpec\Definition::SLUG];
        $unitImpSpcIds = $completeFilters[UnitImportSpec\Definition::SLUG];

        list($folderWhere,   $folderParams)    = $this->getClauseFolder($folderIds);
        list($projectWhere,  $projectParams)   = $this->getClauseProject($projectIds);
        list($unitImplWhere, $unitImplParams)  = $this->getClauseUnitImpl($unitImplIds);
        list($unitSpecWhere, $unitSpecParams)  = $this->getClauseUnitSpec($unitSpecIds);
        list($uImpSpcWhere,  $uImpSpcParams)   = $this->getClauseUnitImplSpec($unitImpSpcIds);


        $params = array_merge($folderParams, $projectParams, $unitImplParams, $unitSpecParams, $uImpSpcParams);

        // Sub-query modifiers
        $aTypes = $completeFilters[AssessmentType\Definition::SLUG];
        $termIds = $completeFilters[TermDue\Definition::SLUG];
        list($aTypeJoin, $aTypeWhere) = $this->getClauseAType($aTypes);
        list($termWhere) = $this->getClauseTerm($termIds);
//        list($weightingJoin, $weightingWhere, $weightingParams) = $this->getClauseWeighting($filters);
//        list($workTypeJoin,  $workTypeWhere,  $workTypeParams)  = $this->getClauseWorkType($filters);


        $subQueries = [];
        if($this->subQueryBase($unitImpSpcIds, $folderIds, $projectIds, $unitImplIds, $unitSpecIds)){
            $subQueries[] = "
                SELECT a.id, 0 AS 'case' FROM `assessment` a
                {$aTypeJoin}
                WHERE TRUE
                AND {$termWhere}
                ";
        } else {
            if($this->subQueryFolder($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)){
                $folderJoin = count($folderParams) ? 'JOIN folder f ON af.folder_id = f.id' : '';
                $subQueries[] = "
                    SELECT a.id, 1 AS 'case' FROM `assessment` a
                    JOIN assessment_folder af ON af.assessment_id = a.id
                    {$folderJoin}
                    {$aTypeJoin}
                    WHERE {$folderWhere}
                    AND {$aTypeWhere} AND {$termWhere}
                    ";
            }
            if($this->subQueryProjectFolder($unitImpSpcIds, $projectIds, $unitImplIds, $unitSpecIds)){
                $folderJoin = count($folderParams) ? 'JOIN folder f ON paf.folder_id = f.id' : '';
                $subQueries[] = "
                    SELECT a.id, 2 AS 'case' FROM `assessment` a
                    JOIN project_assessment ap ON ap.assessment_id  = a.id
                    JOIN project p             ON ap.project_id     = p.id
                    JOIN assessment_folder paf ON paf.assessment_id = p.assessment_id
                    {$folderJoin}
                    {$aTypeJoin}
                    WHERE {$folderWhere} AND {$projectWhere}
                    AND {$aTypeWhere} AND {$termWhere}
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
                    {$folderJoin}
                    {$aTypeJoin}
                    WHERE {$folderWhere} AND {$unitImplWhere}
                    AND {$aTypeWhere} AND {$termWhere}
                    
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
                    {$folderJoin}
                    {$aTypeJoin}
                    WHERE {$folderWhere} AND {$projectWhere} AND {$unitImplWhere}
                    AND {$aTypeWhere} AND {$termWhere}
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
                    WHERE {$folderWhere} AND {$unitSpecWhere}
                    AND {$aTypeWhere} AND {$termWhere}
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
                    WHERE {$folderWhere} AND {$projectWhere} AND {$unitSpecWhere}
                    AND {$aTypeWhere} AND {$termWhere}
                    ";
            }
            if($this->subQueryUnitImplUnitSpec($unitImpSpcIds, $projectIds)){
                $folderJoin = count($folderParams) ? 'JOIN folder f ON uis.folder_id = f.id' : '';
                $subQueries[] = "
                    SELECT a.id, 7 AS 'case' FROM `assessment` a
                    JOIN assessment_course_unit_implementation aui ON aui.assessment_id                 =  a.id 
                    JOIN course_unit_implementation ui             ON aui.course_unit_implementation_id = ui.id 
                    JOIN course_unit_specification uis             ON ui.course_unit_specification_id   = uis.id
                    {$folderJoin}
                    {$aTypeJoin}
                    WHERE {$folderWhere} AND {$unitImplWhere} AND {$uImpSpcWhere}
                    AND {$aTypeWhere} AND {$termWhere}
                    ";
            }
            if($this->subQueryProjectUnitImplUnitSpec($unitImpSpcIds, $projectIds)){
                $folderJoin = count($folderParams) ? 'JOIN folder f ON uis.folder_id = f.id' : '';
                $subQueries[] = "
                    SELECT a.id, 8 AS 'case' FROM `assessment` a
                    JOIN project_assessment ap                      ON ap.assessment_id                  = a.id
                    JOIN project p                                  ON ap.project_id                     = p.id
                    JOIN assessment_course_unit_implementation aui  ON aui.assessment_id                 = p.assessment_id
                    JOIN course_unit_implementation ui              ON aui.course_unit_implementation_id = ui.id 
                    JOIN course_unit_specification uis ON ui.course_unit_specification_id = uis.id
                    {$folderJoin}
                    {$aTypeJoin}
                    WHERE {$folderWhere} AND {$projectWhere} AND {$unitImplWhere} AND {$uImpSpcWhere}
                    AND {$aTypeWhere} AND {$termWhere}
                    ";
            }
        }

        return [implode(' UNION ', $subQueries), $params];
    }


    /**
     * Build a registry of filters known to this class
     */
    private function buildRegistry()
    {
        // Manually register instead of auto-glob, as we have to manually implement anyway.
        $this->knownFilterDefinitionRegistry = [
            AssessmentType\Definition::SLUG => AssessmentType\Definition::class,
            Folder\Definition::SLUG         => Folder\Definition::class,
            Project\Definition::SLUG        => Project\Definition::class,
            TermDue\Definition::SLUG        => TermDue\Definition::class,
            TermClose\Definition::SLUG      => TermClose\Definition::class,
            UnitImpl\Definition::SLUG       => UnitImpl\Definition::class,
            UnitImportSpec\Definition::SLUG => UnitImportSpec\Definition::class,
            UnitSpec\Definition::SLUG       => UnitSpec\Definition::class,
            WeightingType\Definition::SLUG  => WeightingType\Definition::class,
            WorkType\Definition::SLUG       => WorkType\Definition::class,
        ];
    }


    /**
     * Ensures that any filters passed in are known to this class
     *
     * @param array $filters
     * @throws \InvalidArgumentException If filter key not recognised
     * @todo move to abstract
     */
    protected function checkValidFilterKeys(array $filters = [])
    {
        foreach($filters as $key => $val){
            if(!array_key_exists($key, $this->knownFilterDefinitionRegistry)){
                throw new \InvalidArgumentException("Unknown filter: $key");
            }
        }
    }


    /**
     * Inserts null values for missing filters and ensures any scalars are wrapped in arrays
     *
     * This enables us to rely on filters being present, even if set explicitly to NULL
     *
     * @param array $sparseFilters
     * @return mixed[]
     * @todo move to abstract
     */
    protected function normaliseFilters(array $sparseFilters)
    {
        $completeFilters = [];
        foreach($this->knownFilterDefinitionRegistry as $filterKey => $filterClass){
            // Not in the passed filters? Set it explicitly to null.
            if(!array_key_exists($filterKey, $sparseFilters)){
                $completeFilters[$filterKey] = null;
                continue;
            }
            // Ensure it is either null, or an array (ok if empty)
            $completeFilters[$filterKey] = null === $sparseFilters[$filterKey]
                ? null
                : (array)$sparseFilters[$filterKey];
        }
        return $completeFilters;
    }


    /**
     * Include (#0) 'base' sub-query? (and just the base query)
     *
     * This will only be true if no context filters are specified
     *
     * @param int[]|null $uImpSpcIds
     * @param int[]|null $folderIds
     * @param int[]|null $projectIds
     * @param int[]|null $unitImplIds
     * @param int[]|null $unitSpecIds
     * @return bool
     */
    private function subQueryBase(
        array $uImpSpcIds = null,
        array $folderIds = null,
        array $projectIds = null,
        array $unitImplIds = null,
        array $unitSpecIds = null)
    {
        return
            null === $uImpSpcIds &&
            null === $folderIds &&
            null === $projectIds &&
            null === $unitImplIds &&
            null === $unitSpecIds;
    }

    /**
     * Include (#1) 'folder' sub-query?
     *
     * @param int[]|null $uImpSpcIds
     * @param int[]|null $projectIds
     * @param int[]|null $unitImplIds
     * @param int[]|null $unitSpecIds
     * @return bool
     */
    private function subQueryFolder(array $uImpSpcIds = null, array $projectIds = null, array $unitImplIds = null, array $unitSpecIds = null)
    {
        if(is_array($projectIds) && count($projectIds)){
            return false;
        }
        if(is_array($unitSpecIds) && count($unitSpecIds)){
            return false;
        }
        if(is_array($unitImplIds) && count($unitImplIds)){
            return false;
        }
        if(is_array($uImpSpcIds) && count($uImpSpcIds)){
            return false;
        }
        return true;
    }

    /**
     * Include (#2) 'in project' sub-query?
     *
     * @param int[]|null $uImpSpcIds
     * @param int[]|null $projectIds
     * @param int[]|null $unitImplIds
     * @param int[]|null $unitSpecIds
     * @return bool
     */
    private function subQueryProjectFolder(array $uImpSpcIds = null, array $projectIds = null, array $unitImplIds = null, array $unitSpecIds = null)
    {
        if(is_array($projectIds) && !count($projectIds)){
            return false;
        }
        if(is_array($unitImplIds) && count($unitImplIds)){
            return false;
        }
        if(is_array($unitSpecIds) && count($unitSpecIds)){
            return false;
        }
        if(is_array($uImpSpcIds) && count($uImpSpcIds)){
            return false;
        }
        return true;
    }

    /**
     * Include (#3) 'in unit-impl' sub-query?
     *
     * @param int[]|null $uImpSpcIds
     * @param int[]|null $projectIds
     * @param int[]|null $unitImplIds
     * @param int[]|null $unitSpecIds
     * @return bool
     */
    private function subQueryUnitImpl(array $uImpSpcIds = null, array $projectIds = null, array $unitImplIds = null, array $unitSpecIds = null)
    {
        if(is_array($uImpSpcIds)){
            return false;
        }
        if(is_array($unitImplIds) && !count($unitImplIds)){
            return false;
        }
        if(is_array($projectIds) && count($projectIds)){
            return false;
        }
        if(is_array($unitSpecIds) && count($unitSpecIds)){
            return false;
        }
        return true;
    }

    /**
     * Include (#4) 'in project in unit-impl' sub-query?
     *
     * @param int[]|null $uImpSpcIds
     * @param int[]|null $projectIds
     * @param int[]|null $unitImplIds
     * @param int[]|null $unitSpecIds
     * @return bool
     */
    private function subQueryProjectUnitImpl(array $uImpSpcIds = null, array $projectIds = null, array $unitImplIds = null, array $unitSpecIds = null)
    {
        if(is_array($uImpSpcIds)){
            return false;
        }
        if(is_array($projectIds) && !count($projectIds)){
            return false;
        }
        if(is_array($unitImplIds) && !count($unitImplIds)){
            return false;
        }
        if(is_array($unitSpecIds) && count($unitSpecIds)){
            return false;
        }
        return true;
    }

    /**
     * Include (#5) 'in unit-spec' sub-query?
     *
     * @param int[]|null $uImpSpcIds
     * @param int[]|null $projectIds
     * @param int[]|null $unitImplIds
     * @param int[]|null $unitSpecIds
     * @return bool
     */
    private function subQueryUnitSpec(array $uImpSpcIds = null, array $projectIds = null, array $unitImplIds = null, array $unitSpecIds = null)
    {
        if(is_array($unitSpecIds) && !count($unitSpecIds)){
            return false;
        }
        if(is_array($unitImplIds) && count($unitImplIds)){
            return false;
        }
        if(is_array($uImpSpcIds) && count($uImpSpcIds)){
            return false;
        }
        if(is_array($projectIds) && count($projectIds)){
            return false;
        }
        return true;
    }

    /**
     * Include (#6) 'in project in unit-spec' sub-query?
     *
     * @param int[]|null $uImpSpcIds
     * @param int[]|null $projectIds
     * @param int[]|null $unitImplIds
     * @param int[]|null $unitSpecIds
     * @return bool
     */
    private function subQueryProjectUnitSpec(array $uImpSpcIds = null, array $projectIds = null, array $unitImplIds = null, array $unitSpecIds = null)
    {
        if(is_array($projectIds) && !count($projectIds)){
            return false;
        }
        if(is_array($unitSpecIds) && !count($unitSpecIds)){
            return false;
        }
        if(is_array($unitImplIds) && count($unitImplIds)){
            return false;
        }
        if(is_array($uImpSpcIds) && count($uImpSpcIds)){
            return false;
        }
        return true;
    }

    /**
     * Include (#7) 'in unit-impl in unit-spec' sub-query?
     *
     * @param int[]|null $uImpSpcIds
     * @param int[]|null $projectIds
     * @return bool
     */
    private function subQueryUnitImplUnitSpec(array $uImpSpcIds = null, array $projectIds = null)
    {
        if(null === $uImpSpcIds || !count($uImpSpcIds)){
            return false;
        }
        if(is_array($projectIds) && count($projectIds)){
            return false;
        }
        return true;
    }

    /**
     * Include (#8) 'in project in unit-impl in unit-spec' sub-query?
     *
     * @param int[]|null $uImpSpcIds
     * @param int[]|null $projectIds
     * @return bool
     */
    private function subQueryProjectUnitImplUnitSpec(array $uImpSpcIds = null, array $projectIds = null)
    {
        if(null === $uImpSpcIds || !count($uImpSpcIds)){
            return false;
        }
        if(is_array($projectIds) && !count($projectIds)){
            return false;
        }
        return true;
    }


    /**
     * Get query fragments dependent on 'AssessmentType' filter
     *
     * @param null|int[] $aTypes
     * @return mixed[]
     */
    private function getClauseAType(array $aTypes = null)
    {
        // Not filtering by assessment type?
        if(null === $aTypes) {
            return ['', 'TRUE'];
        }

        $joinClause = '';
        $builder    = $this->assessmentMapper->getPredicateBuilder();

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
     * @param null|int[] $folderIds
     * @return mixed[]
     */
    private function getClauseFolder(array $folderIds = null)
    {
        if(null === $folderIds){
            $whereClause = 'TRUE';
            $whereParams = [];
        } else {
            $builder = $this->assessmentMapper->getPredicateBuilder();
            $builder->equals('f.id', $folderIds);
            $whereClause = $builder->getWhere()[0];
            $whereParams = $builder->getParams();
        }
        return [$whereClause, $whereParams];
    }

    /**
     * Get query fragments dependent on 'Project' filter
     *
     * @param null|int[] $projectIds
     * @return mixed[]
     */
    private function getClauseProject(array $projectIds = null)
    {
        if(null === $projectIds || !count($projectIds)){
            $whereClause = 'TRUE';
            $whereParams = [];
        } else {
            $builder = $this->assessmentMapper->getPredicateBuilder();
            $builder->equals('p.id', $projectIds);
            $whereClause = $builder->getWhere()[0];
            $whereParams = $builder->getParams();
        }
        return [$whereClause, $whereParams];
    }

    /**
     * Get query fragments dependent on 'Term' filter
     *
     * @param null|Collection|Term\Model[] $terms
     * @return string
     * @todo rename this to dueDate and add 'closeDate' (can pass term models for both)
     */
    private function getClauseTerm(Collection $terms = null)
    {
        // Is this filter being applied ?
        if(NULL === $terms){
            return ' TRUE ';
        }

        // Generate the dueDate filtering
        $anyBuilder = $this->assessmentMapper->getPredicateBuilder();
        foreach($terms as $term){
            $allBuilder = $this->assessmentMapper->getPredicateBuilder();
            $allBuilder->greaterOrEqualTo('a.due_date', $term->getStart()->getDatabaseDate());

            if(!$term->getActualFinish()->isNull()){
                $allBuilder->lesserOrEqualTo('a.due_date', $term->getActualFinish()->getDatabaseDate());
            }
            $anyBuilder->all($allBuilder);
        }
        return '(' . implode(' OR ', $anyBuilder->getWhere()) . ')';
    }

    /**
     * Get query fragments dependent on 'UnitImpl' filter
     *
     * @param null|int[] $unitImplIds
     * @return mixed[]
     */
    private function getClauseUnitImpl(array $unitImplIds = null)
    {
        if(null === $unitImplIds || !count($unitImplIds)){
            $whereClause = 'TRUE';
            $whereParams = [];
        } else {
            $builder = $this->assessmentMapper->getPredicateBuilder();
            $builder->equals('ui.id', $unitImplIds);
            $whereClause = $builder->getWhere()[0];
            $whereParams = $builder->getParams();
        }
        return [$whereClause, $whereParams];
    }

    /**
     * Get query fragments dependent on 'UnitImportSpec' filter
     *
     * @param null|int[] $unitImpSpcIds
     * @return mixed[]
     */
    private function getClauseUnitImplSpec(array $unitImpSpcIds = null)
    {
        if(null === $unitImpSpcIds || !count($unitImpSpcIds)){
            $whereClause = 'TRUE';
            $whereParams = [];
        } else {
            $builder = $this->assessmentMapper->getPredicateBuilder();
            $builder->equals('uis.id', $unitImpSpcIds);
            $whereClause = $builder->getWhere()[0];
            $whereParams = $builder->getParams();
        }
        return [$whereClause, $whereParams];
    }

    /**
     * Get query fragments dependent on 'UnitSpec' filter
     *
     * @param null|int[] $unitSpecIds
     * @return mixed[]
     */
    private function getClauseUnitSpec(array $unitSpecIds = null)
    {
        if(null === $unitSpecIds || !count($unitSpecIds)){
            $whereClause = 'TRUE';
            $whereParams = [];
        } else {
            $builder = $this->assessmentMapper->getPredicateBuilder();
            $builder->equals('us.id', $unitSpecIds);
            $whereClause = $builder->getWhere()[0];
            $whereParams = $builder->getParams();
        }
        return [$whereClause, $whereParams];
    }

    /**
     * Get query fragments dependent on 'Weighting' filter
     *
     * @param array $filters
     * @return mixed[]
     */
    private function getClauseWeighting(array $filters)
    {
        return [];
    }

    /**
     * Get query fragments dependent on 'WorkType' filter
     *
     * @param array $filters
     * @return mixed[]
     */
    private function getClauseWorkType(array $filters)
    {
        return [];
    }


    /**
     * Can examination of the filters using set-theory indicate that no assessments could ever match?
     *
     * eg, an assessment can not live directly in a unit-impl and unit-spec at the same time.
     *
     * @param int[][]|null[] $completeFilters
     * @return bool
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
     * Can examination of the non-context filters using set-theory enable us to conclude that no results will ever be returned?
     *
     * @param int[][]|null[] $completeFilters
     * @return bool
     */
    private function doNonContextFiltersGuaranteeEmptySet(array $completeFilters)
    {
        // Did call explicitly request assessments that do not have an assessment type?
        $assessmentTypes = $completeFilters[AssessmentType\Definition::SLUG];
        if (is_array($assessmentTypes) && !count($assessmentTypes)) {
            // All assessments have a type
            return true;
        }

        // Did call explicitly request assessments due in some term, but no terms supplied?
        $termIds = $completeFilters[TermDue\Definition::SLUG];
        if (is_array($termIds) && !count($termIds)) {
            // All assessments have due dates
            return true;
        }

        // Note. It is valid to request assessments without a workType (it is nullable)


        // Did call explicitly request assessments with a weightingType, but no weightingTypes supplied?
        $weightingType = $completeFilters[WeightingType\Definition::SLUG];
        if (is_array($weightingType) && !count($weightingType)) {
            // All assessments have a weighting type
            return true;
        }

        // Unable to conclude that result set is guaranteed to be empty
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
    private function doContextFiltersGuaranteeEmptySet(array  $completeFilters){

        // Ultimate folder (ie either direct or indirectly associated)
        $folderIds = $completeFilters[Folder\Definition::SLUG];

        // Did call explicitly request assessments that do not have an ultimate folder?
        if(is_array($folderIds) && !count($folderIds)){
            // All assessments have an ultimate folder
            return true;
        }

        $unitImplIds = $completeFilters[UnitImpl\Definition::SLUG];
        $unitSpecIds = $completeFilters[UnitSpec\Definition::SLUG];

        // Did caller request assessments that live in unit-impls and unit-specs simultaneously?
        if(is_array($unitImplIds) && is_array($unitSpecIds) && count($unitImplIds) && count($unitSpecIds)){
            // Assessments cannot live in both a unitImpl and unitSpec at the same time.
            return true;
        }

        // Did caller specify assessment must reside directly in some unit-spec, and also be
        // directly in a unit-impl (that was imported from some spec)?
        $unitImportSpecIds = $completeFilters[UnitImportSpec\Definition::SLUG];
        if(is_array($unitImportSpecIds) && is_array($unitSpecIds) && count($unitImportSpecIds) && count($unitSpecIds)){
            return true;
        }


        // Did caller request assessment that live inside unit-impls that were not imported, or were imported from unit-specs
        // but don't live in a unit-spec?
        if(is_array($unitImplIds) && is_array($unitImportSpecIds) && (bool)count($unitImplIds) !== (bool)count($unitImportSpecIds)){
            return true;
        }

        // Unable to conclude that result set is guaranteed to be empty
        return false;
    }
}
