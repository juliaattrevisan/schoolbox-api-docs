<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Test;

use Schoolbox\Filter\Filters\Assessment as AssessmentFilters;
use Schoolbox\Learning\Assessment\DataModel as Assessment;

class AssessmentTests extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provider_resultsPossibleForContextFilters
     * @param null|int[] $uis              UnitImplSpec
     * @param null|int[] $f                Folder
     * @param null|int[] $p                Project
     * @param null|int[] $ui               UnitImpl
     * @param null|int[] $us               UnitSpec
     * @param bool       $expected         Are results possible? (ie not guaranteed empty set)
     */
    public function test_areResultsPossible_contextFilters($uis, $f, $p, $ui, $us, $expected)
    {
        $qbt     = $this->getTester();
        $filters = $this->buildContextFilterArray($uis, $f, $p, $ui, $us);
        $actual  = $qbt->getAreResultsPossible($filters);
        $this->assertEquals($expected, $actual);
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
        $qbt     = $this->getTester();
        $filters = $this->buildContextFilterArray($uis, $f, $p, $ui, $us);

        list($query, $params) = $qbt->getSqlStringForFilters($filters);

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
     * @param null|int[] $var
     * @return string
     */
    private function toStr($var)
    {
        if(null === $var){
            return "NULL\n";
        }
        $string = '[';
        $first = true;
        foreach($var as $i){
            $string .= (!$first ? ',' : '') . $i;
            $first = false;
        }
        $string .= "]\n";
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
            AssessmentFilters\UnitImportSpec\Definition::getQueryKey() => $uis,
            AssessmentFilters\Folder\Definition::getQueryKey()         => $f,
            AssessmentFilters\Project\Definition::getQueryKey()        => $p,
            AssessmentFilters\UnitImpl\Definition::getQueryKey()       => $ui,
            AssessmentFilters\UnitSpec\Definition::getQueryKey()       => $us,
        ];
    }


    /**
     * @return AssessmentFilters\TestableQueryBuilder|AssessmentFilters\QueryBuilder
     */
    private function getTester()
    {
        require_once __DIR__ . '/TestableQueryBuilder.php';
        return AssessmentFilters\TestableQueryBuilder::getInstance(Assessment\Mapper::getInstance());
    }

    // ---------------- data providers --------------

    /**
     * Returns all 3^5 = 243 permutations and whether results are possible
     * @return array
     */
    public function provider_resultsPossibleForContextFilters()
    {
        $nll = null;  // Filter is not applied
        $emt = [];    // Return items that do not JOIN to this filter. ie Logically a LEFT JOIN other o ON o.a_id = a.id WHERE a.id IS NULL
        $ntE = [1];   // Return items that JOIN and match. ie JOIN other o ON o.a_id = a.id WHERE a.id IN (filterParams)

        return [
            [$nll,  $nll,  $nll,  $nll,  $nll, true],
            [$nll,  $nll,  $nll,  $nll,  $emt, true],
            [$nll,  $nll,  $nll,  $nll,  $ntE, true],
            [$nll,  $nll,  $nll,  $emt,  $nll, true],
            [$nll,  $nll,  $nll,  $emt,  $emt, true],
            [$nll,  $nll,  $nll,  $emt,  $ntE, true],
            [$nll,  $nll,  $nll,  $ntE,  $nll, true],
            [$nll,  $nll,  $nll,  $ntE,  $emt, true],
            [$nll,  $nll,  $nll,  $ntE,  $ntE, false],
            [$nll,  $nll,  $emt,  $nll,  $nll, true],
            [$nll,  $nll,  $emt,  $nll,  $emt, true],
            [$nll,  $nll,  $emt,  $nll,  $ntE, true],
            [$nll,  $nll,  $emt,  $emt,  $nll, true],
            [$nll,  $nll,  $emt,  $emt,  $emt, true],
            [$nll,  $nll,  $emt,  $emt,  $ntE, true],
            [$nll,  $nll,  $emt,  $ntE,  $nll, true],
            [$nll,  $nll,  $emt,  $ntE,  $emt, true],
            [$nll,  $nll,  $emt,  $ntE,  $ntE, false],
            [$nll,  $nll,  $ntE,  $nll,  $nll, true],
            [$nll,  $nll,  $ntE,  $nll,  $emt, true],
            [$nll,  $nll,  $ntE,  $nll,  $ntE, true],
            [$nll,  $nll,  $ntE,  $emt,  $nll, true],
            [$nll,  $nll,  $ntE,  $emt,  $emt, true],
            [$nll,  $nll,  $ntE,  $emt,  $ntE, true],
            [$nll,  $nll,  $ntE,  $ntE,  $nll, true],
            [$nll,  $nll,  $ntE,  $ntE,  $emt, true],
            [$nll,  $nll,  $ntE,  $ntE,  $ntE, false],
            [$nll,  $emt,  $nll,  $nll,  $nll, false],
            [$nll,  $emt,  $nll,  $nll,  $emt, false],
            [$nll,  $emt,  $nll,  $nll,  $ntE, false],
            [$nll,  $emt,  $nll,  $emt,  $nll, false],
            [$nll,  $emt,  $nll,  $emt,  $emt, false],
            [$nll,  $emt,  $nll,  $emt,  $ntE, false],
            [$nll,  $emt,  $nll,  $ntE,  $nll, false],
            [$nll,  $emt,  $nll,  $ntE,  $emt, false],
            [$nll,  $emt,  $nll,  $ntE,  $ntE, false],
            [$nll,  $emt,  $emt,  $nll,  $nll, false],
            [$nll,  $emt,  $emt,  $nll,  $emt, false],
            [$nll,  $emt,  $emt,  $nll,  $ntE, false],
            [$nll,  $emt,  $emt,  $emt,  $nll, false],
            [$nll,  $emt,  $emt,  $emt,  $emt, false],
            [$nll,  $emt,  $emt,  $emt,  $ntE, false],
            [$nll,  $emt,  $emt,  $ntE,  $nll, false],
            [$nll,  $emt,  $emt,  $ntE,  $emt, false],
            [$nll,  $emt,  $emt,  $ntE,  $ntE, false],
            [$nll,  $emt,  $ntE,  $nll,  $nll, false],
            [$nll,  $emt,  $ntE,  $nll,  $emt, false],
            [$nll,  $emt,  $ntE,  $nll,  $ntE, false],
            [$nll,  $emt,  $ntE,  $emt,  $nll, false],
            [$nll,  $emt,  $ntE,  $emt,  $emt, false],
            [$nll,  $emt,  $ntE,  $emt,  $ntE, false],
            [$nll,  $emt,  $ntE,  $ntE,  $nll, false],
            [$nll,  $emt,  $ntE,  $ntE,  $emt, false],
            [$nll,  $ntE,  $ntE,  $ntE,  $ntE, false],
            [$nll,  $ntE,  $nll,  $nll,  $nll, true],
            [$nll,  $ntE,  $nll,  $nll,  $emt, true],
            [$nll,  $ntE,  $nll,  $nll,  $ntE, true],
            [$nll,  $ntE,  $nll,  $emt,  $nll, true],
            [$nll,  $ntE,  $nll,  $emt,  $emt, true],
            [$nll,  $ntE,  $nll,  $emt,  $ntE, true],
            [$nll,  $ntE,  $nll,  $ntE,  $nll, true],
            [$nll,  $ntE,  $nll,  $ntE,  $emt, true],
            [$nll,  $ntE,  $nll,  $ntE,  $ntE, false],
            [$nll,  $ntE,  $emt,  $nll,  $nll, true],
            [$nll,  $ntE,  $emt,  $nll,  $emt, true],
            [$nll,  $ntE,  $emt,  $nll,  $ntE, true],
            [$nll,  $ntE,  $emt,  $emt,  $nll, true],
            [$nll,  $ntE,  $emt,  $emt,  $emt, true],
            [$nll,  $ntE,  $emt,  $emt,  $ntE, true],
            [$nll,  $ntE,  $emt,  $ntE,  $nll, true],
            [$nll,  $ntE,  $emt,  $ntE,  $emt, true],
            [$nll,  $ntE,  $emt,  $ntE,  $ntE, false],
            [$nll,  $ntE,  $ntE,  $nll,  $nll, true],
            [$nll,  $ntE,  $ntE,  $nll,  $emt, true],
            [$nll,  $ntE,  $ntE,  $nll,  $ntE, true],
            [$nll,  $ntE,  $ntE,  $emt,  $nll, true],
            [$nll,  $ntE,  $ntE,  $emt,  $emt, true],
            [$nll,  $ntE,  $ntE,  $emt,  $ntE, true],
            [$nll,  $ntE,  $ntE,  $ntE,  $nll, true],
            [$nll,  $ntE,  $ntE,  $ntE,  $emt, true],
            [$nll,  $ntE,  $ntE,  $ntE,  $ntE, false],
            [$emt,  $nll,  $nll,  $nll,  $nll, true],
            [$emt,  $nll,  $nll,  $nll,  $emt, true],
            [$emt,  $nll,  $nll,  $nll,  $ntE, true],
            [$emt,  $nll,  $nll,  $emt,  $nll, true],
            [$emt,  $nll,  $nll,  $emt,  $emt, true],
            [$emt,  $nll,  $nll,  $emt,  $ntE, true],
            [$emt,  $nll,  $nll,  $ntE,  $nll, false],
            [$emt,  $nll,  $nll,  $ntE,  $emt, false],
            [$emt,  $nll,  $nll,  $ntE,  $ntE, false],
            [$emt,  $nll,  $emt,  $nll,  $nll, true],
            [$emt,  $nll,  $emt,  $nll,  $emt, true],
            [$emt,  $nll,  $emt,  $nll,  $ntE, true],
            [$emt,  $nll,  $emt,  $emt,  $nll, true],
            [$emt,  $nll,  $emt,  $emt,  $emt, true],
            [$emt,  $nll,  $emt,  $emt,  $ntE, true],
            [$emt,  $nll,  $emt,  $ntE,  $nll, false],
            [$emt,  $nll,  $emt,  $ntE,  $emt, false],
            [$emt,  $nll,  $emt,  $ntE,  $ntE, false],
            [$emt,  $nll,  $ntE,  $nll,  $nll, true],
            [$emt,  $nll,  $ntE,  $nll,  $emt, true],
            [$emt,  $nll,  $ntE,  $nll,  $ntE, true],
            [$emt,  $nll,  $ntE,  $emt,  $nll, true],
            [$emt,  $nll,  $ntE,  $emt,  $emt, true],
            [$emt,  $nll,  $ntE,  $emt,  $ntE, true],
            [$emt,  $nll,  $ntE,  $ntE,  $nll, false],
            [$emt,  $nll,  $ntE,  $ntE,  $emt, false],
            [$emt,  $nll,  $ntE,  $ntE,  $ntE, false],
            [$emt,  $emt,  $nll,  $nll,  $nll, false],
            [$emt,  $emt,  $nll,  $nll,  $emt, false],
            [$emt,  $emt,  $nll,  $nll,  $ntE, false],
            [$emt,  $emt,  $nll,  $emt,  $nll, false],
            [$emt,  $emt,  $nll,  $emt,  $emt, false],
            [$emt,  $emt,  $nll,  $emt,  $ntE, false],
            [$emt,  $emt,  $nll,  $ntE,  $nll, false],
            [$emt,  $emt,  $nll,  $ntE,  $emt, false],
            [$emt,  $emt,  $nll,  $ntE,  $ntE, false],
            [$emt,  $emt,  $emt,  $nll,  $nll, false],
            [$emt,  $emt,  $emt,  $nll,  $emt, false],
            [$emt,  $emt,  $emt,  $nll,  $ntE, false],
            [$emt,  $emt,  $emt,  $emt,  $nll, false],
            [$emt,  $emt,  $emt,  $emt,  $emt, false],
            [$emt,  $emt,  $emt,  $emt,  $ntE, false],
            [$emt,  $emt,  $emt,  $ntE,  $nll, false],
            [$emt,  $emt,  $emt,  $ntE,  $emt, false],
            [$emt,  $emt,  $emt,  $ntE,  $ntE, false],
            [$emt,  $emt,  $ntE,  $nll,  $nll, false],
            [$emt,  $emt,  $ntE,  $nll,  $emt, false],
            [$emt,  $emt,  $ntE,  $nll,  $ntE, false],
            [$emt,  $emt,  $ntE,  $emt,  $nll, false],
            [$emt,  $emt,  $ntE,  $emt,  $emt, false],
            [$emt,  $emt,  $ntE,  $emt,  $ntE, false],
            [$emt,  $emt,  $ntE,  $ntE,  $nll, false],
            [$emt,  $emt,  $ntE,  $ntE,  $emt, false],
            [$emt,  $ntE,  $ntE,  $ntE,  $ntE, false],
            [$emt,  $ntE,  $nll,  $nll,  $nll, true],
            [$emt,  $ntE,  $nll,  $nll,  $emt, true],
            [$emt,  $ntE,  $nll,  $nll,  $ntE, true],
            [$emt,  $ntE,  $nll,  $emt,  $nll, true],
            [$emt,  $ntE,  $nll,  $emt,  $emt, true],
            [$emt,  $ntE,  $nll,  $emt,  $ntE, true],
            [$emt,  $ntE,  $nll,  $ntE,  $nll, false],
            [$emt,  $ntE,  $nll,  $ntE,  $emt, false],
            [$emt,  $ntE,  $nll,  $ntE,  $ntE, false],
            [$emt,  $ntE,  $emt,  $nll,  $nll, true],
            [$emt,  $ntE,  $emt,  $nll,  $emt, true],
            [$emt,  $ntE,  $emt,  $nll,  $ntE, true],
            [$emt,  $ntE,  $emt,  $emt,  $nll, true],
            [$emt,  $ntE,  $emt,  $emt,  $emt, true],
            [$emt,  $ntE,  $emt,  $emt,  $ntE, true],
            [$emt,  $ntE,  $emt,  $ntE,  $nll, false],
            [$emt,  $ntE,  $emt,  $ntE,  $emt, false],
            [$emt,  $ntE,  $emt,  $ntE,  $ntE, false],
            [$emt,  $ntE,  $ntE,  $nll,  $nll, true],
            [$emt,  $ntE,  $ntE,  $nll,  $emt, true],
            [$emt,  $ntE,  $ntE,  $nll,  $ntE, true],
            [$emt,  $ntE,  $ntE,  $emt,  $nll, true],
            [$emt,  $ntE,  $ntE,  $emt,  $emt, true],
            [$emt,  $ntE,  $ntE,  $emt,  $ntE, true],
            [$emt,  $ntE,  $ntE,  $ntE,  $nll, false],
            [$emt,  $ntE,  $ntE,  $ntE,  $emt, false],
            [$emt,  $ntE,  $ntE,  $ntE,  $ntE, false],
            [$ntE,  $nll,  $nll,  $nll,  $nll, true],
            [$ntE,  $nll,  $nll,  $nll,  $emt, true],
            [$ntE,  $nll,  $nll,  $nll,  $ntE, false],
            [$ntE,  $nll,  $nll,  $emt,  $nll, false],
            [$ntE,  $nll,  $nll,  $emt,  $emt, false],
            [$ntE,  $nll,  $nll,  $emt,  $ntE, false],
            [$ntE,  $nll,  $nll,  $ntE,  $nll, true],
            [$ntE,  $nll,  $nll,  $ntE,  $emt, true],
            [$ntE,  $nll,  $nll,  $ntE,  $ntE, false],
            [$ntE,  $nll,  $emt,  $nll,  $nll, true],
            [$ntE,  $nll,  $emt,  $nll,  $emt, true],
            [$ntE,  $nll,  $emt,  $nll,  $ntE, false],
            [$ntE,  $nll,  $emt,  $emt,  $nll, false],
            [$ntE,  $nll,  $emt,  $emt,  $emt, false],
            [$ntE,  $nll,  $emt,  $emt,  $ntE, false],
            [$ntE,  $nll,  $emt,  $ntE,  $nll, true],
            [$ntE,  $nll,  $emt,  $ntE,  $emt, true],
            [$ntE,  $nll,  $emt,  $ntE,  $ntE, false],
            [$ntE,  $nll,  $ntE,  $nll,  $nll, true],
            [$ntE,  $nll,  $ntE,  $nll,  $emt, true],
            [$ntE,  $nll,  $ntE,  $nll,  $ntE, false],
            [$ntE,  $nll,  $ntE,  $emt,  $nll, false],
            [$ntE,  $nll,  $ntE,  $emt,  $emt, false],
            [$ntE,  $nll,  $ntE,  $emt,  $ntE, false],
            [$ntE,  $nll,  $ntE,  $ntE,  $nll, true],
            [$ntE,  $nll,  $ntE,  $ntE,  $emt, true],
            [$ntE,  $nll,  $ntE,  $ntE,  $ntE, false],
            [$ntE,  $emt,  $nll,  $nll,  $nll, false],
            [$ntE,  $emt,  $nll,  $nll,  $emt, false],
            [$ntE,  $emt,  $nll,  $nll,  $ntE, false],
            [$ntE,  $emt,  $nll,  $emt,  $nll, false],
            [$ntE,  $emt,  $nll,  $emt,  $emt, false],
            [$ntE,  $emt,  $nll,  $emt,  $ntE, false],
            [$ntE,  $emt,  $nll,  $ntE,  $nll, false],
            [$ntE,  $emt,  $nll,  $ntE,  $emt, false],
            [$ntE,  $emt,  $nll,  $ntE,  $ntE, false],
            [$ntE,  $emt,  $emt,  $nll,  $nll, false],
            [$ntE,  $emt,  $emt,  $nll,  $emt, false],
            [$ntE,  $emt,  $emt,  $nll,  $ntE, false],
            [$ntE,  $emt,  $emt,  $emt,  $nll, false],
            [$ntE,  $emt,  $emt,  $emt,  $emt, false],
            [$ntE,  $emt,  $emt,  $emt,  $ntE, false],
            [$ntE,  $emt,  $emt,  $ntE,  $nll, false],
            [$ntE,  $emt,  $emt,  $ntE,  $emt, false],
            [$ntE,  $emt,  $emt,  $ntE,  $ntE, false],
            [$ntE,  $emt,  $ntE,  $nll,  $nll, false],
            [$ntE,  $emt,  $ntE,  $nll,  $emt, false],
            [$ntE,  $emt,  $ntE,  $nll,  $ntE, false],
            [$ntE,  $emt,  $ntE,  $emt,  $nll, false],
            [$ntE,  $emt,  $ntE,  $emt,  $emt, false],
            [$ntE,  $emt,  $ntE,  $emt,  $ntE, false],
            [$ntE,  $emt,  $ntE,  $ntE,  $nll, false],
            [$ntE,  $emt,  $ntE,  $ntE,  $emt, false],
            [$ntE,  $ntE,  $ntE,  $ntE,  $ntE, false],
            [$ntE,  $ntE,  $nll,  $nll,  $nll, true],
            [$ntE,  $ntE,  $nll,  $nll,  $emt, true],
            [$ntE,  $ntE,  $nll,  $nll,  $ntE, false],
            [$ntE,  $ntE,  $nll,  $emt,  $nll, false],
            [$ntE,  $ntE,  $nll,  $emt,  $emt, false],
            [$ntE,  $ntE,  $nll,  $emt,  $ntE, false],
            [$ntE,  $ntE,  $nll,  $ntE,  $nll, true],
            [$ntE,  $ntE,  $nll,  $ntE,  $emt, true],
            [$ntE,  $ntE,  $nll,  $ntE,  $ntE, false],
            [$ntE,  $ntE,  $emt,  $nll,  $nll, true],
            [$ntE,  $ntE,  $emt,  $nll,  $emt, true],
            [$ntE,  $ntE,  $emt,  $nll,  $ntE, false],
            [$ntE,  $ntE,  $emt,  $emt,  $nll, false],
            [$ntE,  $ntE,  $emt,  $emt,  $emt, false],
            [$ntE,  $ntE,  $emt,  $emt,  $ntE, false],
            [$ntE,  $ntE,  $emt,  $ntE,  $nll, true],
            [$ntE,  $ntE,  $emt,  $ntE,  $emt, true],
            [$ntE,  $ntE,  $emt,  $ntE,  $ntE, false],
            [$ntE,  $ntE,  $ntE,  $nll,  $nll, true],
            [$ntE,  $ntE,  $ntE,  $nll,  $emt, true],
            [$ntE,  $ntE,  $ntE,  $nll,  $ntE, false],
            [$ntE,  $ntE,  $ntE,  $emt,  $nll, false],
            [$ntE,  $ntE,  $ntE,  $emt,  $emt, false],
            [$ntE,  $ntE,  $ntE,  $emt,  $ntE, false],
            [$ntE,  $ntE,  $ntE,  $ntE,  $nll, true],
            [$ntE,  $ntE,  $ntE,  $ntE,  $emt, true],
            [$ntE,  $ntE,  $ntE,  $ntE,  $ntE, false],
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
            [$emt, $ntE, $emt, $emt, $emt, [   1,                     ]],
            [$emt, $ntE, $ntE, $emt, $emt, [      2,                  ]],
            [$emt, $ntE, $nll, $emt, $emt, [   1, 2,                  ]],
            [$emt, $ntE, $emt, $emt, $ntE, [               5,         ]],
            [$emt, $ntE, $ntE, $emt, $ntE, [                  6,      ]],
            [$emt, $ntE, $nll, $emt, $ntE, [               5, 6,      ]],
            [$emt, $ntE, $emt, $emt, $nll, [   1,          5,         ]],
            [$emt, $ntE, $ntE, $emt, $nll, [      2,          6,      ]],
            [$emt, $ntE, $nll, $emt, $nll, [   1, 2,       5, 6,      ]],
            [$emt, $ntE, $emt, $nll, $emt, [   1,                     ]],
            [$emt, $ntE, $ntE, $nll, $emt, [      2,                  ]],
            [$emt, $ntE, $nll, $nll, $emt, [   1, 2,                  ]],
            [$emt, $ntE, $emt, $nll, $ntE, [               5,         ]],
            [$emt, $ntE, $ntE, $nll, $ntE, [                  6,      ]],
            [$emt, $ntE, $nll, $nll, $ntE, [               5, 6,      ]],
            [$emt, $ntE, $emt, $nll, $nll, [   1,          5,         ]],
            [$emt, $ntE, $ntE, $nll, $nll, [      2,          6,      ]],
            [$emt, $ntE, $nll, $nll, $nll, [   1, 2,       5, 6,      ]],
            [$ntE, $ntE, $emt, $ntE, $emt, [                     7,   ]],
            [$ntE, $ntE, $ntE, $ntE, $emt, [                        8,]],
            [$ntE, $ntE, $nll, $ntE, $emt, [                     7, 8,]],
            [$ntE, $ntE, $emt, $ntE, $nll, [                     7,   ]],
            [$ntE, $ntE, $ntE, $ntE, $nll, [                        8,]],
            [$ntE, $ntE, $nll, $ntE, $nll, [                     7, 8,]],
            [$ntE, $ntE, $emt, $nll, $emt, [                     7,   ]],
            [$ntE, $ntE, $ntE, $nll, $emt, [                        8,]],
            [$ntE, $ntE, $nll, $nll, $emt, [                     7, 8,]],
            [$ntE, $ntE, $emt, $nll, $nll, [                     7,   ]],
            [$ntE, $ntE, $ntE, $nll, $nll, [                        8,]],
            [$ntE, $ntE, $nll, $nll, $nll, [                     7, 8,]],
            [$nll, $ntE, $emt, $emt, $emt, [   1,                     ]],
            [$nll, $ntE, $ntE, $emt, $emt, [      2,                  ]],
            [$nll, $ntE, $nll, $emt, $emt, [   1, 2,                  ]],
            [$nll, $ntE, $emt, $emt, $ntE, [               5,         ]],
            [$nll, $ntE, $ntE, $emt, $ntE, [                  6,      ]],
            [$nll, $ntE, $nll, $emt, $ntE, [               5, 6,      ]],
            [$nll, $ntE, $emt, $emt, $nll, [   1,          5,         ]],
            [$nll, $ntE, $ntE, $emt, $nll, [      2,          6,      ]],
            [$nll, $ntE, $nll, $emt, $nll, [   1, 2,       5, 6,      ]],
            [$nll, $ntE, $emt, $ntE, $emt, [         3,               ]],
            [$nll, $ntE, $ntE, $ntE, $emt, [            4,            ]],
            [$nll, $ntE, $nll, $ntE, $emt, [         3, 4,            ]],
            [$nll, $ntE, $emt, $ntE, $nll, [         3,               ]],
            [$nll, $ntE, $ntE, $ntE, $nll, [            4,            ]],
            [$nll, $ntE, $nll, $ntE, $nll, [         3, 4,            ]],
            [$nll, $ntE, $emt, $nll, $emt, [   1,    3,               ]],
            [$nll, $ntE, $ntE, $nll, $emt, [      2,    4,            ]],
            [$nll, $ntE, $nll, $nll, $emt, [   1, 2, 3, 4,            ]],
            [$nll, $ntE, $emt, $nll, $ntE, [               5,         ]],
            [$nll, $ntE, $ntE, $nll, $ntE, [                  6,      ]],
            [$nll, $ntE, $nll, $nll, $ntE, [               5, 6,      ]],
            [$nll, $ntE, $emt, $nll, $nll, [   1,    3,    5,         ]],
            [$nll, $ntE, $ntE, $nll, $nll, [      2,    4,    6,      ]],
            [$nll, $ntE, $nll, $nll, $nll, [   1, 2, 3, 4, 5, 6,      ]],
            [$emt, $nll, $emt, $emt, $emt, [   1,                     ]],
            [$emt, $nll, $ntE, $emt, $emt, [      2,                  ]],
            [$emt, $nll, $nll, $emt, $emt, [   1, 2,                  ]],
            [$emt, $nll, $emt, $emt, $ntE, [               5,         ]],
            [$emt, $nll, $ntE, $emt, $ntE, [                  6,      ]],
            [$emt, $nll, $nll, $emt, $ntE, [               5, 6,      ]],
            [$emt, $nll, $emt, $emt, $nll, [   1,          5,         ]],
            [$emt, $nll, $ntE, $emt, $nll, [      2,          6,      ]],
            [$emt, $nll, $nll, $emt, $nll, [   1, 2,       5, 6,      ]],
            [$emt, $nll, $emt, $nll, $emt, [   1,                     ]],
            [$emt, $nll, $ntE, $nll, $emt, [      2,                  ]],
            [$emt, $nll, $nll, $nll, $emt, [   1, 2,                  ]],
            [$emt, $nll, $emt, $nll, $ntE, [               5,         ]],
            [$emt, $nll, $ntE, $nll, $ntE, [                  6,      ]],
            [$emt, $nll, $nll, $nll, $ntE, [               5, 6,      ]],
            [$emt, $nll, $emt, $nll, $nll, [   1,          5,         ]],
            [$emt, $nll, $ntE, $nll, $nll, [      2,          6,      ]],
            [$emt, $nll, $nll, $nll, $nll, [   1, 2,       5, 6,      ]],
            [$ntE, $nll, $emt, $ntE, $emt, [                     7,   ]],
            [$ntE, $nll, $ntE, $ntE, $emt, [                        8,]],
            [$ntE, $nll, $nll, $ntE, $emt, [                     7, 8,]],
            [$ntE, $nll, $emt, $ntE, $nll, [                     7,   ]],
            [$ntE, $nll, $ntE, $ntE, $nll, [                        8,]],
            [$ntE, $nll, $nll, $ntE, $nll, [                     7, 8,]],
            [$ntE, $nll, $emt, $nll, $emt, [                     7,   ]],
            [$ntE, $nll, $ntE, $nll, $emt, [                        8,]],
            [$ntE, $nll, $nll, $nll, $emt, [                     7, 8,]],
            [$ntE, $nll, $emt, $nll, $nll, [                     7,   ]],
            [$ntE, $nll, $ntE, $nll, $nll, [                        8,]],
            [$ntE, $nll, $nll, $nll, $nll, [                     7, 8,]],
            [$nll, $nll, $emt, $emt, $emt, [   1,                     ]],
            [$nll, $nll, $ntE, $emt, $emt, [      2,                  ]],
            [$nll, $nll, $nll, $emt, $emt, [   1, 2,                  ]],
            [$nll, $nll, $emt, $emt, $ntE, [               5,         ]],
            [$nll, $nll, $ntE, $emt, $ntE, [                  6,      ]],
            [$nll, $nll, $nll, $emt, $ntE, [               5, 6,      ]],
            [$nll, $nll, $emt, $emt, $nll, [   1,          5,         ]],
            [$nll, $nll, $ntE, $emt, $nll, [      2,          6,      ]],
            [$nll, $nll, $nll, $emt, $nll, [   1, 2,       5, 6,      ]],
            [$nll, $nll, $emt, $ntE, $emt, [         3,               ]],
            [$nll, $nll, $ntE, $ntE, $emt, [            4,            ]],
            [$nll, $nll, $nll, $ntE, $emt, [         3, 4,            ]],
            [$nll, $nll, $emt, $ntE, $nll, [         3,               ]],
            [$nll, $nll, $ntE, $ntE, $nll, [            4,            ]],
            [$nll, $nll, $nll, $ntE, $nll, [         3, 4,            ]],
            [$nll, $nll, $emt, $nll, $emt, [   1,    3,               ]],
            [$nll, $nll, $ntE, $nll, $emt, [      2,    4,            ]],
            [$nll, $nll, $nll, $nll, $emt, [   1, 2, 3, 4,            ]],
            [$nll, $nll, $emt, $nll, $ntE, [               5,         ]],
            [$nll, $nll, $ntE, $nll, $ntE, [                  6,      ]],
            [$nll, $nll, $nll, $nll, $ntE, [               5, 6,      ]],
            [$nll, $nll, $emt, $nll, $nll, [   1,    3,    5,         ]],
            [$nll, $nll, $ntE, $nll, $nll, [      2,    4,    6,      ]],
            [$nll, $nll, $nll, $nll, $nll, [0,                        ]],
        ];
    }

//
//    /**
//     * Build a filter array where null filters are not included
//     * @param null|int[] $uis              UnitImplSpec
//     * @param null|int[] $f                Folder
//     * @param null|int[] $p                Project
//     * @param null|int[] $ui               UnitImpl
//     * @param null|int[] $us               UnitSpec
//     * @return int[][]
//     */
//    private function buildSpareFilterArray($uis, $f, $p, $ui, $us)
//    {
//        $completeFilters = $this->buildContextFilterArray($uis, $f, $p, $ui, $us);
//        $sparseFilters = [];
//        foreach($completeFilters as $filterKey => $val){
//            if(is_array($val)){
//                $sparseFilters[$filterKey] = $val;
//            }
//        }
//        return $sparseFilters;
//    }
}
