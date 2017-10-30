<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Filter;
use Schoolbox\Collection;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilters;
use Schoolbox\Learning\Assessment\DataModel as Assessment;

class LogicTests extends TestCase
{
    /**
     * @dataProvider provider_doContextFiltersGuaranteeEmptySet
     * @param null|int[] $uis              UnitImplSpec
     * @param null|int[] $f                Folder
     * @param null|int[] $p                Project
     * @param null|int[] $ui               UnitImpl
     * @param null|int[] $us               UnitSpec
     * @param bool       $expected         Are results possible? (ie not guaranteed empty set)
     */
    public function test_areResultsPossible_contextFilters($uis, $f, $p, $ui, $us, $expected)
    {
        $qbt             = $this->getTester();
        $filters         = $this->buildContextFilterArray($uis, $f, $p, $ui, $us);
        $completeFilters = $qbt->insertMissingFilters($filters);
        $actual          = $qbt->getDoContextFiltersGuaranteeEmptySet($completeFilters);

        $message =
            AssessmentFilters\NullableContext\UnitImportSpec\Definition::class . ': ' . $this->toStr($uis).
            AssessmentFilters\Folder\TeacherInvolved\Definition::class         . ': ' . $this->toStr($f).
            AssessmentFilters\NullableContext\Project\Definition::class        . ': ' . $this->toStr($p).
            AssessmentFilters\NullableContext\UnitImpl\Definition::class       . ': ' . $this->toStr($ui).
            AssessmentFilters\NullableContext\UnitSpec\Definition::class       . ': ' . $this->toStr($us).
            $this->toStr($completeFilters)."\n";

        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_doNonContextGuaranteeEmptySet
     * @param null|int[] $a        AssessmentType
     * @param null|int[] $tc       Term Close
     * @param null|int[] $td       Term Due
     * @param null|int[] $wt       Weighting
     * @param null|int[] $wk       WorkType
     * @param bool       $expected Are results possible? (ie not guaranteed empty set)
     */
    public function test_areResultsPossible_nonContextFilters($a, $tc, $td, $wt, $wk, $expected)
    {
        $qbt             = $this->getTester();
        $filters         = $this->buildNonContextFilterArray($a, $tc, $td, $wt, $wk);
        $completeFilters = $qbt->insertMissingFilters($filters);
        $actual          = $qbt->getDoNonContextFiltersGuaranteeEmptySet($completeFilters);

        $message =
            AssessmentFilters\AssessmentType\Definition::class . ':  '. $this->toStr($a).
            AssessmentFilters\Term\Close\Definition::class     . ': ' . $this->toStr($tc).
            AssessmentFilters\Term\Due\Definition::class       . ': ' . $this->toStr($td).
            AssessmentFilters\WeightingType\Definition::class  . ': ' . $this->toStr($wt).
            AssessmentFilters\WorkType\Definition::class       . ': ' . $this->toStr($wk).
            $this->toStr($completeFilters)."\n";

        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_subQueriesRequired
     * @param null|int[] $uis              UnitImplSpec
     * @param null|int[] $f                Folder
     * @param null|int[] $p                Project
     * @param null|int[] $ui               UnitImpl
     * @param null|int[] $us               UnitSpec
     * @param bool       $expected         The sub-queries that are required for this combination of filters
     */
    public function test_requiredSubQueries($uis, $f, $p, $ui, $us, $expected)
    {
        $qbt             = $this->getTester();
        $filters         = $this->buildContextFilterArray($uis, $f, $p, $ui, $us);
        $completeFilters = $qbt->insertMissingFilters($filters);

        list($query, $params) = $qbt->getSqlStringForFilters($completeFilters);

        $message = $this->toStr($uis) . $this->toStr($f) . $this->toStr($p) . $this->toStr($ui) . $this->toStr($us) .
            "Query: '$query'\n                    Params: {$this->paramsString($params)}\n";

        // Ensure we got at least 1 sub-query back from builder
        $found = (bool)preg_match_all('/(\d) AS \'case\'/', $query, $matches);
        $this->assertTrue($found, "No sub-queries recognised: $message");

        if($found){
            $this->assertEquals($expected, $matches[1], $message);
        }
    }


    // ---------------- helpers --------------


    /**
     * Return a compact string representation of NULL or an int[] for debugging
     *
     * @param Collection|mixed[]|int[] $var
     * @param int                      $level
     * @return string
     */
    private function toStr($var, $level = 0)
    {
        if(null === $var){
            return 'NULL' . (!$level ? "\n" : '');
        }
        if(!($var instanceOf Collection || is_array($var))){
            return $var;
        }
        $string = '[';
        $first = true;
        foreach($var as $key => $val){
            $string .= (!$first ? ', ' : '') . "'$key' => {$this->toStr($val, $level + 1)}";
            $first = false;
        }
        $string .= ']' . (!$level ? "\n" : '');
        return $string;
    }


    /**
     * Gets a nicely formatting params string for debugging
     *
     * @param array $params
     * @return string
     */
    private function paramsString(array $params){
        $string = '[';
        $first = true;
        foreach($params as $key => $val){
            $string .= (!$first ? ', ' : '') . "$key => $val";
            $first = false;
        }
        $string .= "]\n";
        return $string;
    }


    /**
     * Build a filter array where null filters are included but their value is null
     * @param null|int[] $uis              UnitImplSpec
     * @param null|int[] $f                Folder
     * @param null|int[] $p                Project
     * @param null|int[] $ui               UnitImpl
     * @param null|int[] $us               UnitSpec
     * @return mixed[]
     */
    private function buildContextFilterArray($uis, $f, $p, $ui, $us)
    {
        return [
            AssessmentFilters\NullableContext\UnitImportSpec\Definition::class => null === $uis ? null : new Collection($uis),
            AssessmentFilters\Folder\TeacherInvolved\Definition::class         => null === $f   ? null : new Collection($f),
            AssessmentFilters\NullableContext\Project\Definition::class        => null === $p   ? null : new Collection($p),
            AssessmentFilters\NullableContext\UnitImpl\Definition::class       => null === $ui  ? null : new Collection($ui),
            AssessmentFilters\NullableContext\UnitSpec\Definition::class       => null === $us  ? null : new Collection($us),
        ];
    }

    /**
     * Build a filter array where null filters are included but their value is null
     *
     * @param null|int[] $a  AssessmentType
     * @param null|int[] $tc TermClose
     * @param null|int[] $td TermDue
     * @param null|int[] $wt Weighting
     * @param null|int[] $wk WorkType
     * @return mixed[]
     */
    private function buildNonContextFilterArray($a, $tc, $td, $wt, $wk)
    {
        return [
            AssessmentFilters\AssessmentType\Definition::class => null === $a  ? null : new Collection($a),
            AssessmentFilters\Term\Close\Definition::class     => null === $tc ? null : new Collection($tc),
            AssessmentFilters\Term\Due\Definition::class       => null === $td ? null : new Collection($td),
            AssessmentFilters\WeightingType\Definition::class  => null === $wt ? null : new Collection($wt),
            AssessmentFilters\WorkType\Definition::class       => null === $wk ? null : new Collection($wk),
        ];
    }

    /**
     * @return \Schoolbox\Filter\AbstractIdFinder|\Schoolbox\Filter\IdFinder|AssessmentFilters\TestableIdFinder
     */
    private function getTester()
    {
        require_once __DIR__ . '/TestableIdFinder.php';
        return AssessmentFilters\TestableIdFinder::getInstance(Filter\Mapper::getInstance(), Assessment\Mapper::getInstance());
    }

    // ---------------- data providers --------------

    /**
     * Returns all 3^5 = 243 permutations and whether results are possible
     * @return array
     */
    public function provider_doContextFiltersGuaranteeEmptySet()
    {
        $nll = null;  // Filter is not applied
        $emt = [];    // Return items that do not JOIN to this filter. ie Logically a LEFT JOIN other o ON o.a_id = a.id WHERE a.id IS NULL
        $ntE = [1];   // Return items that JOIN and match. ie JOIN other o ON o.a_id = a.id WHERE a.id IN (filterParams)

        return [
            [$nll, $nll, $nll, $nll, $nll, false],
            [$nll, $nll, $nll, $nll, $ntE, false],
            [$nll, $nll, $nll, $nll, $emt, false],
            [$nll, $nll, $nll, $ntE, $nll, false],
            [$nll, $nll, $nll, $ntE, $ntE, true],
            [$nll, $nll, $nll, $ntE, $emt, false],
            [$nll, $nll, $nll, $emt, $nll, false],
            [$nll, $nll, $nll, $emt, $ntE, false],
            [$nll, $nll, $nll, $emt, $emt, false],
            [$nll, $nll, $ntE, $nll, $nll, false],
            [$nll, $nll, $ntE, $nll, $ntE, false],
            [$nll, $nll, $ntE, $nll, $emt, false],
            [$nll, $nll, $ntE, $ntE, $nll, false],
            [$nll, $nll, $ntE, $ntE, $ntE, true],
            [$nll, $nll, $ntE, $ntE, $emt, false],
            [$nll, $nll, $ntE, $emt, $nll, false],
            [$nll, $nll, $ntE, $emt, $ntE, false],
            [$nll, $nll, $ntE, $emt, $emt, false],
            [$nll, $nll, $emt, $nll, $nll, false],
            [$nll, $nll, $emt, $nll, $ntE, false],
            [$nll, $nll, $emt, $nll, $emt, false],
            [$nll, $nll, $emt, $ntE, $nll, false],
            [$nll, $nll, $emt, $ntE, $ntE, true],
            [$nll, $nll, $emt, $ntE, $emt, false],
            [$nll, $nll, $emt, $emt, $nll, false],
            [$nll, $nll, $emt, $emt, $ntE, false],
            [$nll, $nll, $emt, $emt, $emt, false],
            [$nll, $ntE, $nll, $nll, $nll, false],
            [$nll, $ntE, $nll, $nll, $ntE, false],
            [$nll, $ntE, $nll, $nll, $emt, false],
            [$nll, $ntE, $nll, $ntE, $nll, false],
            [$nll, $ntE, $nll, $ntE, $ntE, true],
            [$nll, $ntE, $nll, $ntE, $emt, false],
            [$nll, $ntE, $nll, $emt, $nll, false],
            [$nll, $ntE, $nll, $emt, $ntE, false],
            [$nll, $ntE, $nll, $emt, $emt, false],
            [$nll, $ntE, $ntE, $nll, $nll, false],
            [$nll, $ntE, $ntE, $nll, $ntE, false],
            [$nll, $ntE, $ntE, $nll, $emt, false],
            [$nll, $ntE, $ntE, $ntE, $nll, false],
            [$nll, $ntE, $ntE, $ntE, $ntE, true],
            [$nll, $ntE, $ntE, $ntE, $emt, false],
            [$nll, $ntE, $ntE, $emt, $nll, false],
            [$nll, $ntE, $ntE, $emt, $ntE, false],
            [$nll, $ntE, $ntE, $emt, $emt, false],
            [$nll, $ntE, $emt, $nll, $nll, false],
            [$nll, $ntE, $emt, $nll, $ntE, false],
            [$nll, $ntE, $emt, $nll, $emt, false],
            [$nll, $ntE, $emt, $ntE, $nll, false],
            [$nll, $ntE, $emt, $ntE, $ntE, true],
            [$nll, $ntE, $emt, $ntE, $emt, false],
            [$nll, $ntE, $emt, $emt, $nll, false],
            [$nll, $ntE, $emt, $emt, $ntE, false],
            [$nll, $ntE, $emt, $emt, $emt, false],
            [$nll, $emt, $nll, $nll, $nll, true],
            [$nll, $emt, $nll, $nll, $ntE, true],
            [$nll, $emt, $nll, $nll, $emt, true],
            [$nll, $emt, $nll, $ntE, $nll, true],
            [$nll, $emt, $nll, $ntE, $ntE, true],
            [$nll, $emt, $nll, $ntE, $emt, true],
            [$nll, $emt, $nll, $emt, $nll, true],
            [$nll, $emt, $nll, $emt, $ntE, true],
            [$nll, $emt, $nll, $emt, $emt, true],
            [$nll, $emt, $ntE, $nll, $nll, true],
            [$nll, $emt, $ntE, $nll, $ntE, true],
            [$nll, $emt, $ntE, $nll, $emt, true],
            [$nll, $emt, $ntE, $ntE, $nll, true],
            [$nll, $emt, $ntE, $ntE, $ntE, true],
            [$nll, $emt, $ntE, $ntE, $emt, true],
            [$nll, $emt, $ntE, $emt, $nll, true],
            [$nll, $emt, $ntE, $emt, $ntE, true],
            [$nll, $emt, $ntE, $emt, $emt, true],
            [$nll, $emt, $emt, $nll, $nll, true],
            [$nll, $emt, $emt, $nll, $ntE, true],
            [$nll, $emt, $emt, $nll, $emt, true],
            [$nll, $emt, $emt, $ntE, $nll, true],
            [$nll, $emt, $emt, $ntE, $ntE, true],
            [$nll, $emt, $emt, $ntE, $emt, true],
            [$nll, $emt, $emt, $emt, $nll, true],
            [$nll, $emt, $emt, $emt, $ntE, true],
            [$nll, $emt, $emt, $emt, $emt, true],
            [$ntE, $nll, $nll, $nll, $nll, false],
            [$ntE, $nll, $nll, $nll, $ntE, true],
            [$ntE, $nll, $nll, $nll, $emt, false],
            [$ntE, $nll, $nll, $ntE, $nll, false],
            [$ntE, $nll, $nll, $ntE, $ntE, true],
            [$ntE, $nll, $nll, $ntE, $emt, false],
            [$ntE, $nll, $nll, $emt, $nll, true],
            [$ntE, $nll, $nll, $emt, $ntE, true],
            [$ntE, $nll, $nll, $emt, $emt, true],
            [$ntE, $nll, $ntE, $nll, $nll, false],
            [$ntE, $nll, $ntE, $nll, $ntE, true],
            [$ntE, $nll, $ntE, $nll, $emt, false],
            [$ntE, $nll, $ntE, $ntE, $nll, false],
            [$ntE, $nll, $ntE, $ntE, $ntE, true],
            [$ntE, $nll, $ntE, $ntE, $emt, false],
            [$ntE, $nll, $ntE, $emt, $nll, true],
            [$ntE, $nll, $ntE, $emt, $ntE, true],
            [$ntE, $nll, $ntE, $emt, $emt, true],
            [$ntE, $nll, $emt, $nll, $nll, false],
            [$ntE, $nll, $emt, $nll, $ntE, true],
            [$ntE, $nll, $emt, $nll, $emt, false],
            [$ntE, $nll, $emt, $ntE, $nll, false],
            [$ntE, $nll, $emt, $ntE, $ntE, true],
            [$ntE, $nll, $emt, $ntE, $emt, false],
            [$ntE, $nll, $emt, $emt, $nll, true],
            [$ntE, $nll, $emt, $emt, $ntE, true],
            [$ntE, $nll, $emt, $emt, $emt, true],
            [$ntE, $ntE, $nll, $nll, $nll, false],
            [$ntE, $ntE, $nll, $nll, $ntE, true],
            [$ntE, $ntE, $nll, $nll, $emt, false],
            [$ntE, $ntE, $nll, $ntE, $nll, false],
            [$ntE, $ntE, $nll, $ntE, $ntE, true],
            [$ntE, $ntE, $nll, $ntE, $emt, false],
            [$ntE, $ntE, $nll, $emt, $nll, true],
            [$ntE, $ntE, $nll, $emt, $ntE, true],
            [$ntE, $ntE, $nll, $emt, $emt, true],
            [$ntE, $ntE, $ntE, $nll, $nll, false],
            [$ntE, $ntE, $ntE, $nll, $ntE, true],
            [$ntE, $ntE, $ntE, $nll, $emt, false],
            [$ntE, $ntE, $ntE, $ntE, $nll, false],
            [$ntE, $ntE, $ntE, $ntE, $ntE, true],
            [$ntE, $ntE, $ntE, $ntE, $emt, false],
            [$ntE, $ntE, $ntE, $emt, $nll, true],
            [$ntE, $ntE, $ntE, $emt, $ntE, true],
            [$ntE, $ntE, $ntE, $emt, $emt, true],
            [$ntE, $ntE, $emt, $nll, $nll, false],
            [$ntE, $ntE, $emt, $nll, $ntE, true],
            [$ntE, $ntE, $emt, $nll, $emt, false],
            [$ntE, $ntE, $emt, $ntE, $nll, false],
            [$ntE, $ntE, $emt, $ntE, $ntE, true],
            [$ntE, $ntE, $emt, $ntE, $emt, false],
            [$ntE, $ntE, $emt, $emt, $nll, true],
            [$ntE, $ntE, $emt, $emt, $ntE, true],
            [$ntE, $ntE, $emt, $emt, $emt, true],
            [$ntE, $emt, $nll, $nll, $nll, true],
            [$ntE, $emt, $nll, $nll, $ntE, true],
            [$ntE, $emt, $nll, $nll, $emt, true],
            [$ntE, $emt, $nll, $ntE, $nll, true],
            [$ntE, $emt, $nll, $ntE, $ntE, true],
            [$ntE, $emt, $nll, $ntE, $emt, true],
            [$ntE, $emt, $nll, $emt, $nll, true],
            [$ntE, $emt, $nll, $emt, $ntE, true],
            [$ntE, $emt, $nll, $emt, $emt, true],
            [$ntE, $emt, $ntE, $nll, $nll, true],
            [$ntE, $emt, $ntE, $nll, $ntE, true],
            [$ntE, $emt, $ntE, $nll, $emt, true],
            [$ntE, $emt, $ntE, $ntE, $nll, true],
            [$ntE, $emt, $ntE, $ntE, $ntE, true],
            [$ntE, $emt, $ntE, $ntE, $emt, true],
            [$ntE, $emt, $ntE, $emt, $nll, true],
            [$ntE, $emt, $ntE, $emt, $ntE, true],
            [$ntE, $emt, $ntE, $emt, $emt, true],
            [$ntE, $emt, $emt, $nll, $nll, true],
            [$ntE, $emt, $emt, $nll, $ntE, true],
            [$ntE, $emt, $emt, $nll, $emt, true],
            [$ntE, $emt, $emt, $ntE, $nll, true],
            [$ntE, $emt, $emt, $ntE, $ntE, true],
            [$ntE, $emt, $emt, $ntE, $emt, true],
            [$ntE, $emt, $emt, $emt, $nll, true],
            [$ntE, $emt, $emt, $emt, $ntE, true],
            [$ntE, $emt, $emt, $emt, $emt, true],
            [$emt, $nll, $nll, $nll, $nll, false],
            [$emt, $nll, $nll, $nll, $ntE, false],
            [$emt, $nll, $nll, $nll, $emt, false],
            [$emt, $nll, $nll, $ntE, $nll, true],
            [$emt, $nll, $nll, $ntE, $ntE, true],
            [$emt, $nll, $nll, $ntE, $emt, true],
            [$emt, $nll, $nll, $emt, $nll, false],
            [$emt, $nll, $nll, $emt, $ntE, false],
            [$emt, $nll, $nll, $emt, $emt, false],
            [$emt, $nll, $ntE, $nll, $nll, false],
            [$emt, $nll, $ntE, $nll, $ntE, false],
            [$emt, $nll, $ntE, $nll, $emt, false],
            [$emt, $nll, $ntE, $ntE, $nll, true],
            [$emt, $nll, $ntE, $ntE, $ntE, true],
            [$emt, $nll, $ntE, $ntE, $emt, true],
            [$emt, $nll, $ntE, $emt, $nll, false],
            [$emt, $nll, $ntE, $emt, $ntE, false],
            [$emt, $nll, $ntE, $emt, $emt, false],
            [$emt, $nll, $emt, $nll, $nll, false],
            [$emt, $nll, $emt, $nll, $ntE, false],
            [$emt, $nll, $emt, $nll, $emt, false],
            [$emt, $nll, $emt, $ntE, $nll, true],
            [$emt, $nll, $emt, $ntE, $ntE, true],
            [$emt, $nll, $emt, $ntE, $emt, true],
            [$emt, $nll, $emt, $emt, $nll, false],
            [$emt, $nll, $emt, $emt, $ntE, false],
            [$emt, $nll, $emt, $emt, $emt, false],
            [$emt, $ntE, $nll, $nll, $nll, false],
            [$emt, $ntE, $nll, $nll, $ntE, false],
            [$emt, $ntE, $nll, $nll, $emt, false],
            [$emt, $ntE, $nll, $ntE, $nll, true],
            [$emt, $ntE, $nll, $ntE, $ntE, true],
            [$emt, $ntE, $nll, $ntE, $emt, true],
            [$emt, $ntE, $nll, $emt, $nll, false],
            [$emt, $ntE, $nll, $emt, $ntE, false],
            [$emt, $ntE, $nll, $emt, $emt, false],
            [$emt, $ntE, $ntE, $nll, $nll, false],
            [$emt, $ntE, $ntE, $nll, $ntE, false],
            [$emt, $ntE, $ntE, $nll, $emt, false],
            [$emt, $ntE, $ntE, $ntE, $nll, true],
            [$emt, $ntE, $ntE, $ntE, $ntE, true],
            [$emt, $ntE, $ntE, $ntE, $emt, true],
            [$emt, $ntE, $ntE, $emt, $nll, false],
            [$emt, $ntE, $ntE, $emt, $ntE, false],
            [$emt, $ntE, $ntE, $emt, $emt, false],
            [$emt, $ntE, $emt, $nll, $nll, false],
            [$emt, $ntE, $emt, $nll, $ntE, false],
            [$emt, $ntE, $emt, $nll, $emt, false],
            [$emt, $ntE, $emt, $ntE, $nll, true],
            [$emt, $ntE, $emt, $ntE, $ntE, true],
            [$emt, $ntE, $emt, $ntE, $emt, true],
            [$emt, $ntE, $emt, $emt, $nll, false],
            [$emt, $ntE, $emt, $emt, $ntE, false],
            [$emt, $ntE, $emt, $emt, $emt, false],
            [$emt, $emt, $nll, $nll, $nll, true],
            [$emt, $emt, $nll, $nll, $ntE, true],
            [$emt, $emt, $nll, $nll, $emt, true],
            [$emt, $emt, $nll, $ntE, $nll, true],
            [$emt, $emt, $nll, $ntE, $ntE, true],
            [$emt, $emt, $nll, $ntE, $emt, true],
            [$emt, $emt, $nll, $emt, $nll, true],
            [$emt, $emt, $nll, $emt, $ntE, true],
            [$emt, $emt, $nll, $emt, $emt, true],
            [$emt, $emt, $ntE, $nll, $nll, true],
            [$emt, $emt, $ntE, $nll, $ntE, true],
            [$emt, $emt, $ntE, $nll, $emt, true],
            [$emt, $emt, $ntE, $ntE, $nll, true],
            [$emt, $emt, $ntE, $ntE, $ntE, true],
            [$emt, $emt, $ntE, $ntE, $emt, true],
            [$emt, $emt, $ntE, $emt, $nll, true],
            [$emt, $emt, $ntE, $emt, $ntE, true],
            [$emt, $emt, $ntE, $emt, $emt, true],
            [$emt, $emt, $emt, $nll, $nll, true],
            [$emt, $emt, $emt, $nll, $ntE, true],
            [$emt, $emt, $emt, $nll, $emt, true],
            [$emt, $emt, $emt, $ntE, $nll, true],
            [$emt, $emt, $emt, $ntE, $ntE, true],
            [$emt, $emt, $emt, $ntE, $emt, true],
            [$emt, $emt, $emt, $emt, $nll, true],
            [$emt, $emt, $emt, $emt, $ntE, true],
            [$emt, $emt, $emt, $emt, $emt, true],
        ];
    }


    /**
     * Truth table for whether some combination of non-context filters guarantees an empty set
     * (and hence we can short-circuit without hitting the DB)
     * @return array
     */
    public function provider_doNonContextGuaranteeEmptySet(){

        $nll = null;
        $emt = [];
        $ntE = [1];

        return [
            [$nll, $nll, $nll, $nll, $nll, false],
            [$nll, $nll, $nll, $nll, $emt, false],
            [$nll, $nll, $nll, $nll, $ntE, false],
            [$nll, $nll, $nll, $emt, $nll, true],
            [$nll, $nll, $nll, $emt, $emt, true],
            [$nll, $nll, $nll, $emt, $ntE, true],
            [$nll, $nll, $nll, $ntE, $nll, false],
            [$nll, $nll, $nll, $ntE, $emt, false],
            [$nll, $nll, $nll, $ntE, $ntE, false],
            [$nll, $nll, $emt, $nll, $nll, true],
            [$nll, $nll, $emt, $nll, $emt, true],
            [$nll, $nll, $emt, $nll, $ntE, true],
            [$nll, $nll, $emt, $emt, $nll, true],
            [$nll, $nll, $emt, $emt, $emt, true],
            [$nll, $nll, $emt, $emt, $ntE, true],
            [$nll, $nll, $emt, $ntE, $nll, true],
            [$nll, $nll, $emt, $ntE, $emt, true],
            [$nll, $nll, $emt, $ntE, $ntE, true],
            [$nll, $nll, $ntE, $nll, $nll, false],
            [$nll, $nll, $ntE, $nll, $emt, false],
            [$nll, $nll, $ntE, $nll, $ntE, false],
            [$nll, $nll, $ntE, $emt, $nll, true],
            [$nll, $nll, $ntE, $emt, $emt, true],
            [$nll, $nll, $ntE, $emt, $ntE, true],
            [$nll, $nll, $ntE, $ntE, $nll, false],
            [$nll, $nll, $ntE, $ntE, $emt, false],
            [$nll, $nll, $ntE, $ntE, $ntE, false],
            [$nll, $emt, $nll, $nll, $nll, true],
            [$nll, $emt, $nll, $nll, $emt, true],
            [$nll, $emt, $nll, $nll, $ntE, true],
            [$nll, $emt, $nll, $emt, $nll, true],
            [$nll, $emt, $nll, $emt, $emt, true],
            [$nll, $emt, $nll, $emt, $ntE, true],
            [$nll, $emt, $nll, $ntE, $nll, true],
            [$nll, $emt, $nll, $ntE, $emt, true],
            [$nll, $emt, $nll, $ntE, $ntE, true],
            [$nll, $emt, $emt, $nll, $nll, true],
            [$nll, $emt, $emt, $nll, $emt, true],
            [$nll, $emt, $emt, $nll, $ntE, true],
            [$nll, $emt, $emt, $emt, $nll, true],
            [$nll, $emt, $emt, $emt, $emt, true],
            [$nll, $emt, $emt, $emt, $ntE, true],
            [$nll, $emt, $emt, $ntE, $nll, true],
            [$nll, $emt, $emt, $ntE, $emt, true],
            [$nll, $emt, $emt, $ntE, $ntE, true],
            [$nll, $emt, $ntE, $nll, $nll, true],
            [$nll, $emt, $ntE, $nll, $emt, true],
            [$nll, $emt, $ntE, $nll, $ntE, true],
            [$nll, $emt, $ntE, $emt, $nll, true],
            [$nll, $emt, $ntE, $emt, $emt, true],
            [$nll, $emt, $ntE, $emt, $ntE, true],
            [$nll, $emt, $ntE, $ntE, $nll, true],
            [$nll, $emt, $ntE, $ntE, $emt, true],
            [$nll, $emt, $ntE, $ntE, $ntE, true],
            [$nll, $ntE, $nll, $nll, $nll, false],
            [$nll, $ntE, $nll, $nll, $emt, false],
            [$nll, $ntE, $nll, $nll, $ntE, false],
            [$nll, $ntE, $nll, $emt, $nll, true],
            [$nll, $ntE, $nll, $emt, $emt, true],
            [$nll, $ntE, $nll, $emt, $ntE, true],
            [$nll, $ntE, $nll, $ntE, $nll, false],
            [$nll, $ntE, $nll, $ntE, $emt, false],
            [$nll, $ntE, $nll, $ntE, $ntE, false],
            [$nll, $ntE, $emt, $nll, $nll, true],
            [$nll, $ntE, $emt, $nll, $emt, true],
            [$nll, $ntE, $emt, $nll, $ntE, true],
            [$nll, $ntE, $emt, $emt, $nll, true],
            [$nll, $ntE, $emt, $emt, $emt, true],
            [$nll, $ntE, $emt, $emt, $ntE, true],
            [$nll, $ntE, $emt, $ntE, $nll, true],
            [$nll, $ntE, $emt, $ntE, $emt, true],
            [$nll, $ntE, $emt, $ntE, $ntE, true],
            [$nll, $ntE, $ntE, $nll, $nll, false],
            [$nll, $ntE, $ntE, $nll, $emt, false],
            [$nll, $ntE, $ntE, $nll, $ntE, false],
            [$nll, $ntE, $ntE, $emt, $nll, true],
            [$nll, $ntE, $ntE, $emt, $emt, true],
            [$nll, $ntE, $ntE, $emt, $ntE, true],
            [$nll, $ntE, $ntE, $ntE, $nll, false],
            [$nll, $ntE, $ntE, $ntE, $emt, false],
            [$nll, $ntE, $ntE, $ntE, $ntE, false],
            [$emt, $nll, $nll, $nll, $nll, true],
            [$emt, $nll, $nll, $nll, $emt, true],
            [$emt, $nll, $nll, $nll, $ntE, true],
            [$emt, $nll, $nll, $emt, $nll, true],
            [$emt, $nll, $nll, $emt, $emt, true],
            [$emt, $nll, $nll, $emt, $ntE, true],
            [$emt, $nll, $nll, $ntE, $nll, true],
            [$emt, $nll, $nll, $ntE, $emt, true],
            [$emt, $nll, $nll, $ntE, $ntE, true],
            [$emt, $nll, $emt, $nll, $nll, true],
            [$emt, $nll, $emt, $nll, $emt, true],
            [$emt, $nll, $emt, $nll, $ntE, true],
            [$emt, $nll, $emt, $emt, $nll, true],
            [$emt, $nll, $emt, $emt, $emt, true],
            [$emt, $nll, $emt, $emt, $ntE, true],
            [$emt, $nll, $emt, $ntE, $nll, true],
            [$emt, $nll, $emt, $ntE, $emt, true],
            [$emt, $nll, $emt, $ntE, $ntE, true],
            [$emt, $nll, $ntE, $nll, $nll, true],
            [$emt, $nll, $ntE, $nll, $emt, true],
            [$emt, $nll, $ntE, $nll, $ntE, true],
            [$emt, $nll, $ntE, $emt, $nll, true],
            [$emt, $nll, $ntE, $emt, $emt, true],
            [$emt, $nll, $ntE, $emt, $ntE, true],
            [$emt, $nll, $ntE, $ntE, $nll, true],
            [$emt, $nll, $ntE, $ntE, $emt, true],
            [$emt, $nll, $ntE, $ntE, $ntE, true],
            [$emt, $emt, $nll, $nll, $nll, true],
            [$emt, $emt, $nll, $nll, $emt, true],
            [$emt, $emt, $nll, $nll, $ntE, true],
            [$emt, $emt, $nll, $emt, $nll, true],
            [$emt, $emt, $nll, $emt, $emt, true],
            [$emt, $emt, $nll, $emt, $ntE, true],
            [$emt, $emt, $nll, $ntE, $nll, true],
            [$emt, $emt, $nll, $ntE, $emt, true],
            [$emt, $emt, $nll, $ntE, $ntE, true],
            [$emt, $emt, $emt, $nll, $nll, true],
            [$emt, $emt, $emt, $nll, $emt, true],
            [$emt, $emt, $emt, $nll, $ntE, true],
            [$emt, $emt, $emt, $emt, $nll, true],
            [$emt, $emt, $emt, $emt, $emt, true],
            [$emt, $emt, $emt, $emt, $ntE, true],
            [$emt, $emt, $emt, $ntE, $nll, true],
            [$emt, $emt, $emt, $ntE, $emt, true],
            [$emt, $emt, $emt, $ntE, $ntE, true],
            [$emt, $emt, $ntE, $nll, $nll, true],
            [$emt, $emt, $ntE, $nll, $emt, true],
            [$emt, $emt, $ntE, $nll, $ntE, true],
            [$emt, $emt, $ntE, $emt, $nll, true],
            [$emt, $emt, $ntE, $emt, $emt, true],
            [$emt, $emt, $ntE, $emt, $ntE, true],
            [$emt, $emt, $ntE, $ntE, $nll, true],
            [$emt, $emt, $ntE, $ntE, $emt, true],
            [$emt, $emt, $ntE, $ntE, $ntE, true],
            [$ntE, $ntE, $nll, $nll, $nll, false],
            [$ntE, $ntE, $nll, $nll, $emt, false],
            [$ntE, $ntE, $nll, $nll, $ntE, false],
            [$ntE, $ntE, $nll, $emt, $nll, true],
            [$ntE, $ntE, $nll, $emt, $emt, true],
            [$ntE, $ntE, $nll, $emt, $ntE, true],
            [$ntE, $ntE, $nll, $ntE, $nll, false],
            [$ntE, $ntE, $nll, $ntE, $emt, false],
            [$ntE, $ntE, $nll, $ntE, $ntE, false],
            [$ntE, $ntE, $emt, $nll, $nll, true],
            [$ntE, $ntE, $emt, $nll, $emt, true],
            [$ntE, $ntE, $emt, $nll, $ntE, true],
            [$ntE, $ntE, $emt, $emt, $nll, true],
            [$ntE, $ntE, $emt, $emt, $emt, true],
            [$ntE, $ntE, $emt, $emt, $ntE, true],
            [$ntE, $ntE, $emt, $ntE, $nll, true],
            [$ntE, $ntE, $emt, $ntE, $emt, true],
            [$ntE, $ntE, $emt, $ntE, $ntE, true],
            [$ntE, $ntE, $ntE, $nll, $nll, false],
            [$ntE, $ntE, $ntE, $nll, $emt, false],
            [$ntE, $ntE, $ntE, $nll, $ntE, false],
            [$ntE, $ntE, $ntE, $emt, $nll, true],
            [$ntE, $ntE, $ntE, $emt, $emt, true],
            [$ntE, $ntE, $ntE, $emt, $ntE, true],
            [$ntE, $ntE, $ntE, $ntE, $nll, false],
            [$ntE, $ntE, $ntE, $ntE, $emt, false],
            [$ntE, $ntE, $ntE, $ntE, $ntE, false],
            [$ntE, $nll, $nll, $nll, $nll, false],
            [$ntE, $nll, $nll, $nll, $emt, false],
            [$ntE, $nll, $nll, $nll, $ntE, false],
            [$ntE, $nll, $nll, $emt, $nll, true],
            [$ntE, $nll, $nll, $emt, $emt, true],
            [$ntE, $nll, $nll, $emt, $ntE, true],
            [$ntE, $nll, $nll, $ntE, $nll, false],
            [$ntE, $nll, $nll, $ntE, $emt, false],
            [$ntE, $nll, $nll, $ntE, $ntE, false],
            [$ntE, $nll, $emt, $nll, $nll, true],
            [$ntE, $nll, $emt, $nll, $emt, true],
            [$ntE, $nll, $emt, $nll, $ntE, true],
            [$ntE, $nll, $emt, $emt, $nll, true],
            [$ntE, $nll, $emt, $emt, $emt, true],
            [$ntE, $nll, $emt, $emt, $ntE, true],
            [$ntE, $nll, $emt, $ntE, $nll, true],
            [$ntE, $nll, $emt, $ntE, $emt, true],
            [$ntE, $nll, $emt, $ntE, $ntE, true],
            [$ntE, $nll, $ntE, $nll, $nll, false],
            [$ntE, $nll, $ntE, $nll, $emt, false],
            [$ntE, $nll, $ntE, $nll, $ntE, false],
            [$ntE, $nll, $ntE, $emt, $nll, true],
            [$ntE, $nll, $ntE, $emt, $emt, true],
            [$ntE, $nll, $ntE, $emt, $ntE, true],
            [$ntE, $nll, $ntE, $ntE, $nll, false],
            [$ntE, $nll, $ntE, $ntE, $emt, false],
            [$ntE, $nll, $ntE, $ntE, $ntE, false],
            [$ntE, $emt, $nll, $nll, $nll, true],
            [$ntE, $emt, $nll, $nll, $emt, true],
            [$ntE, $emt, $nll, $nll, $ntE, true],
            [$ntE, $emt, $nll, $emt, $nll, true],
            [$ntE, $emt, $nll, $emt, $emt, true],
            [$ntE, $emt, $nll, $emt, $ntE, true],
            [$ntE, $emt, $nll, $ntE, $nll, true],
            [$ntE, $emt, $nll, $ntE, $emt, true],
            [$ntE, $emt, $nll, $ntE, $ntE, true],
            [$ntE, $emt, $emt, $nll, $nll, true],
            [$ntE, $emt, $emt, $nll, $emt, true],
            [$ntE, $emt, $emt, $nll, $ntE, true],
            [$ntE, $emt, $emt, $emt, $nll, true],
            [$ntE, $emt, $emt, $emt, $emt, true],
            [$ntE, $emt, $emt, $emt, $ntE, true],
            [$ntE, $emt, $emt, $ntE, $nll, true],
            [$ntE, $emt, $emt, $ntE, $emt, true],
            [$ntE, $emt, $emt, $ntE, $ntE, true],
            [$ntE, $emt, $ntE, $nll, $nll, true],
            [$ntE, $emt, $ntE, $nll, $emt, true],
            [$ntE, $emt, $ntE, $nll, $ntE, true],
            [$ntE, $emt, $ntE, $emt, $nll, true],
            [$ntE, $emt, $ntE, $emt, $emt, true],
            [$ntE, $emt, $ntE, $emt, $ntE, true],
            [$ntE, $emt, $ntE, $ntE, $nll, true],
            [$ntE, $emt, $ntE, $ntE, $emt, true],
            [$ntE, $emt, $ntE, $ntE, $ntE, true],
            [$ntE, $ntE, $nll, $nll, $nll, false],
            [$ntE, $ntE, $nll, $nll, $emt, false],
            [$ntE, $ntE, $nll, $nll, $ntE, false],
            [$ntE, $ntE, $nll, $emt, $nll, true],
            [$ntE, $ntE, $nll, $emt, $emt, true],
            [$ntE, $ntE, $nll, $emt, $ntE, true],
            [$ntE, $ntE, $nll, $ntE, $nll, false],
            [$ntE, $ntE, $nll, $ntE, $emt, false],
            [$ntE, $ntE, $nll, $ntE, $ntE, false],
            [$ntE, $ntE, $emt, $nll, $nll, true],
            [$ntE, $ntE, $emt, $nll, $emt, true],
            [$ntE, $ntE, $emt, $nll, $ntE, true],
            [$ntE, $ntE, $emt, $emt, $nll, true],
            [$ntE, $ntE, $emt, $emt, $emt, true],
            [$ntE, $ntE, $emt, $emt, $ntE, true],
            [$ntE, $ntE, $emt, $ntE, $nll, true],
            [$ntE, $ntE, $emt, $ntE, $emt, true],
            [$ntE, $ntE, $emt, $ntE, $ntE, true],
            [$ntE, $ntE, $ntE, $nll, $nll, false],
            [$ntE, $ntE, $ntE, $nll, $emt, false],
            [$ntE, $ntE, $ntE, $nll, $ntE, false],
            [$ntE, $ntE, $ntE, $emt, $nll, true],
            [$ntE, $ntE, $ntE, $emt, $emt, true],
            [$ntE, $ntE, $ntE, $emt, $ntE, true],
            [$ntE, $ntE, $ntE, $ntE, $nll, false],
            [$ntE, $ntE, $ntE, $ntE, $emt, false],
            [$ntE, $ntE, $ntE, $ntE, $ntE, false],
        ];
    }

    /**
     * For all 108 permutations that are not guaranteed to return empty sets, which subQueries are required?
     * @return array
     */
    public function provider_subQueriesRequired()
    {
        $nll = null;
        $emt = [];
        $ntE = [1];

        return [
            [$nll, $nll, $nll, $nll, $nll, [0,                   ]],
            [$nll, $nll, $nll, $nll, $ntE, [               5, 6, ]],
            [$nll, $nll, $nll, $nll, $emt, [   1, 2, 3, 4,       ]],
            [$nll, $nll, $nll, $ntE, $nll, [         3, 4,       ]],
            [$nll, $nll, $nll, $ntE, $emt, [         3, 4,       ]],
            [$nll, $nll, $nll, $emt, $nll, [   1, 2,       5, 6, ]],
            [$nll, $nll, $nll, $emt, $ntE, [               5, 6, ]],
            [$nll, $nll, $nll, $emt, $emt, [   1, 2,             ]],
            [$nll, $nll, $ntE, $nll, $nll, [      2,    4,    6, ]],
            [$nll, $nll, $ntE, $nll, $ntE, [                  6, ]],
            [$nll, $nll, $ntE, $nll, $emt, [      2,    4,       ]],
            [$nll, $nll, $ntE, $ntE, $nll, [            4,       ]],
            [$nll, $nll, $ntE, $ntE, $emt, [            4,       ]],
            [$nll, $nll, $ntE, $emt, $nll, [      2,          6, ]],
            [$nll, $nll, $ntE, $emt, $ntE, [                  6, ]],
            [$nll, $nll, $ntE, $emt, $emt, [      2,             ]],
            [$nll, $nll, $emt, $nll, $nll, [   1,    3,    5,    ]],
            [$nll, $nll, $emt, $nll, $ntE, [               5,    ]],
            [$nll, $nll, $emt, $nll, $emt, [   1,    3,          ]],
            [$nll, $nll, $emt, $ntE, $nll, [         3,          ]],
            [$nll, $nll, $emt, $ntE, $emt, [         3,          ]],
            [$nll, $nll, $emt, $emt, $nll, [   1,          5,    ]],
            [$nll, $nll, $emt, $emt, $ntE, [               5,    ]],
            [$nll, $nll, $emt, $emt, $emt, [   1,                ]],
            [$nll, $ntE, $nll, $nll, $nll, [   1, 2, 3, 4, 5, 6, ]],
            [$nll, $ntE, $nll, $nll, $ntE, [               5, 6, ]],
            [$nll, $ntE, $nll, $nll, $emt, [   1, 2, 3, 4,       ]],
            [$nll, $ntE, $nll, $ntE, $nll, [         3, 4,       ]],
            [$nll, $ntE, $nll, $ntE, $emt, [         3, 4,       ]],
            [$nll, $ntE, $nll, $emt, $nll, [   1, 2,       5, 6, ]],
            [$nll, $ntE, $nll, $emt, $ntE, [               5, 6, ]],
            [$nll, $ntE, $nll, $emt, $emt, [   1, 2,             ]],
            [$nll, $ntE, $ntE, $nll, $nll, [      2,    4,    6, ]],
            [$nll, $ntE, $ntE, $nll, $ntE, [                  6, ]],
            [$nll, $ntE, $ntE, $nll, $emt, [      2,    4,       ]],
            [$nll, $ntE, $ntE, $ntE, $nll, [            4,       ]],
            [$nll, $ntE, $ntE, $ntE, $emt, [            4,       ]],
            [$nll, $ntE, $ntE, $emt, $nll, [      2,          6, ]],
            [$nll, $ntE, $ntE, $emt, $ntE, [                  6, ]],
            [$nll, $ntE, $ntE, $emt, $emt, [      2,             ]],
            [$nll, $ntE, $emt, $nll, $nll, [   1,    3,    5,    ]],
            [$nll, $ntE, $emt, $nll, $ntE, [               5,    ]],
            [$nll, $ntE, $emt, $nll, $emt, [   1,    3,          ]],
            [$nll, $ntE, $emt, $ntE, $nll, [         3,          ]],
            [$nll, $ntE, $emt, $ntE, $emt, [         3,          ]],
            [$nll, $ntE, $emt, $emt, $nll, [   1,          5,    ]],
            [$nll, $ntE, $emt, $emt, $ntE, [               5,    ]],
            [$nll, $ntE, $emt, $emt, $emt, [   1,                ]],
            [$ntE, $nll, $nll, $nll, $nll, [         3, 4,       ]],
            [$ntE, $nll, $nll, $nll, $emt, [         3, 4,       ]],
            [$ntE, $nll, $nll, $ntE, $nll, [         3, 4,       ]],
            [$ntE, $nll, $nll, $ntE, $emt, [         3, 4,       ]],
            [$ntE, $nll, $ntE, $nll, $nll, [            4,       ]],
            [$ntE, $nll, $ntE, $nll, $emt, [            4,       ]],
            [$ntE, $nll, $ntE, $ntE, $nll, [            4,       ]],
            [$ntE, $nll, $ntE, $ntE, $emt, [            4,       ]],
            [$ntE, $nll, $emt, $nll, $nll, [         3,          ]],
            [$ntE, $nll, $emt, $nll, $emt, [         3,          ]],
            [$ntE, $nll, $emt, $ntE, $nll, [         3,          ]],
            [$ntE, $nll, $emt, $ntE, $emt, [         3,          ]],
            [$ntE, $ntE, $nll, $nll, $nll, [         3, 4,       ]],
            [$ntE, $ntE, $nll, $nll, $emt, [         3, 4,       ]],
            [$ntE, $ntE, $nll, $ntE, $nll, [         3, 4,       ]],
            [$ntE, $ntE, $nll, $ntE, $emt, [         3, 4,       ]],
            [$ntE, $ntE, $ntE, $nll, $nll, [            4,       ]],
            [$ntE, $ntE, $ntE, $nll, $emt, [            4,       ]],
            [$ntE, $ntE, $ntE, $ntE, $nll, [            4,       ]],
            [$ntE, $ntE, $ntE, $ntE, $emt, [            4,       ]],
            [$ntE, $ntE, $emt, $nll, $nll, [         3,          ]],
            [$ntE, $ntE, $emt, $nll, $emt, [         3,          ]],
            [$ntE, $ntE, $emt, $ntE, $nll, [         3,          ]],
            [$ntE, $ntE, $emt, $ntE, $emt, [         3,          ]],
            [$emt, $nll, $nll, $nll, $nll, [   1, 2,       5, 6, ]],
            [$emt, $nll, $nll, $nll, $ntE, [               5, 6, ]],
            [$emt, $nll, $nll, $nll, $emt, [   1, 2,             ]],
            [$emt, $nll, $nll, $emt, $nll, [   1, 2,       5, 6, ]],
            [$emt, $nll, $nll, $emt, $ntE, [               5, 6, ]],
            [$emt, $nll, $nll, $emt, $emt, [   1, 2,             ]],
            [$emt, $nll, $ntE, $nll, $nll, [      2,          6, ]],
            [$emt, $nll, $ntE, $nll, $ntE, [                  6, ]],
            [$emt, $nll, $ntE, $nll, $emt, [      2,             ]],
            [$emt, $nll, $ntE, $emt, $nll, [      2,          6, ]],
            [$emt, $nll, $ntE, $emt, $ntE, [                  6, ]],
            [$emt, $nll, $ntE, $emt, $emt, [      2,             ]],
            [$emt, $nll, $emt, $nll, $nll, [   1,          5,    ]],
            [$emt, $nll, $emt, $nll, $ntE, [               5,    ]],
            [$emt, $nll, $emt, $nll, $emt, [   1,                ]],
            [$emt, $nll, $emt, $emt, $nll, [   1,          5,    ]],
            [$emt, $nll, $emt, $emt, $ntE, [               5,    ]],
            [$emt, $nll, $emt, $emt, $emt, [   1,                ]],
            [$emt, $ntE, $nll, $nll, $nll, [   1, 2,       5, 6, ]],
            [$emt, $ntE, $nll, $nll, $ntE, [               5, 6, ]],
            [$emt, $ntE, $nll, $nll, $emt, [   1, 2,             ]],
            [$emt, $ntE, $nll, $emt, $nll, [   1, 2,       5, 6, ]],
            [$emt, $ntE, $nll, $emt, $ntE, [               5, 6, ]],
            [$emt, $ntE, $nll, $emt, $emt, [   1, 2,             ]],
            [$emt, $ntE, $ntE, $nll, $nll, [      2,          6, ]],
            [$emt, $ntE, $ntE, $nll, $ntE, [                  6, ]],
            [$emt, $ntE, $ntE, $nll, $emt, [      2,             ]],
            [$emt, $ntE, $ntE, $emt, $nll, [      2,          6, ]],
            [$emt, $ntE, $ntE, $emt, $ntE, [                  6, ]],
            [$emt, $ntE, $ntE, $emt, $emt, [      2,             ]],
            [$emt, $ntE, $emt, $nll, $nll, [   1,          5,    ]],
            [$emt, $ntE, $emt, $nll, $ntE, [               5,    ]],
            [$emt, $ntE, $emt, $nll, $emt, [   1,                ]],
            [$emt, $ntE, $emt, $emt, $nll, [   1,          5,    ]],
            [$emt, $ntE, $emt, $emt, $ntE, [               5,    ]],
            [$emt, $ntE, $emt, $emt, $emt, [   1,                ]],
        ];
    }

}
