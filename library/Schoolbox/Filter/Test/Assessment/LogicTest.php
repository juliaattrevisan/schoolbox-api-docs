<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter\Test\Assessment;

use Alaress\Database\Predicate\InvalidColumnException;
use InvalidArgumentException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Model;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilters;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Test\MockConnector;
use UnderflowException;
use UnexpectedValueException;

use function array_merge;

class LogicTest extends TestCase
{
    /**
     * @param null|int[] $uis      UnitImplSpec
     * @param null|int[] $f        Folder
     * @param null|int[] $p        Project
     * @param null|int[] $ui       UnitImpl
     * @param null|int[] $us       UnitSpec
     * @param bool       $expected Are results possible? (ie not guaranteed empty set)
     * @throws Filter\BrokenFilterException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_doContextFiltersGuaranteeEmptySet')]
    public function test_areResultsPossible_contextFilters($uis, $f, $p, $ui, $us, $expected): void
    {
        $qbt             = $this->getTester();
        $filters         = $this->buildContextFilterArray($uis, $f, $p, $ui, $us);
        $completeFilters = $qbt->insertMissingFilters($filters);
        $actual          = $qbt->getDoContextFiltersGuaranteeEmptySet($completeFilters);

        $message =
            AssessmentFilters\NullableContext\UnitImportSpec\Definition::class . ': ' . $this->toStr($uis) .
            AssessmentFilters\Folder\TeacherInvolved\Definition::class         . ': ' . $this->toStr($f) .
            AssessmentFilters\NullableContext\Project\Definition::class        . ': ' . $this->toStr($p) .
            AssessmentFilters\NullableContext\UnitImpl\Definition::class       . ': ' . $this->toStr($ui) .
            AssessmentFilters\NullableContext\UnitSpec\Definition::class       . ': ' . $this->toStr($us) .
            $this->toStr($completeFilters) . "\n";

        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @param null|int[] $a        AssessmentType
     * @param null|int[] $tc       Term Close
     * @param null|int[] $td       Term Due
     * @param null|int[] $wt       Weighting
     * @param null|int[] $wk       WorkType
     * @param bool       $expected Are results possible? (ie not guaranteed empty set)
     * @throws Filter\BrokenFilterException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_doNonContextGuaranteeEmptySet')]
    public function test_areResultsPossible_nonContextFilters($a, $tc, $td, $wt, $wk, $expected): void
    {
        $qbt             = $this->getTester();
        $filters         = $this->buildNonContextFilterArray($a, $tc, $td, $wt, $wk);
        $completeFilters = $qbt->insertMissingFilters($filters);
        $actual          = $qbt->getDoNonContextFiltersGuaranteeEmptySet($completeFilters);

        $message =
            AssessmentFilters\AssessmentType\Definition::class . ':  ' . $this->toStr($a) .
            AssessmentFilters\Term\Close\Definition::class     . ': ' . $this->toStr($tc) .
            AssessmentFilters\Term\Due\Definition::class       . ': ' . $this->toStr($td) .
            AssessmentFilters\WeightingType\Definition::class  . ': ' . $this->toStr($wt) .
            AssessmentFilters\WorkType\Definition::class       . ': ' . $this->toStr($wk) .
            $this->toStr($completeFilters) . "\n";

        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @param null|int[] $aType      AssessmentType
     * @param array|null $capInd     Capabilities/Indicators Usage
     * @param array|null $markPerCap MarkPerCap
     * @param bool       $expected Are results possible? (ie not guaranteed empty set)
     * @throws Filter\BrokenFilterException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_doRubricsFiltersGuaranteeEmptySet')]
    public function test_areResultsPossible_RubricsFilters(?array $aType, ?array $capInd, ?array $markPerCap, bool $expected): void
    {
        $qbt             = $this->getTester();
        $filters         = $this->buildRubricUsageFilterArray($aType, $capInd, $markPerCap);
        $completeFilters = $qbt->insertMissingFilters($filters);
        $actual          = $qbt->getDoNonContextFiltersGuaranteeEmptySet($completeFilters);

        $message =
            AssessmentFilters\AssessmentType\Definition::class . ':  ' . $this->toStr($aType) .
            AssessmentFilters\RubricUsage\Definition::class . ':  ' . $this->toStr($capInd) .
            AssessmentFilters\MarkPerCap\Definition::class . ':  ' . $this->toStr($markPerCap) .
            $this->toStr($completeFilters) . "\n";

        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @param null|int[] $uis      UnitImplSpec
     * @param null|int[] $f        Folder
     * @param null|int[] $p        Project
     * @param null|int[] $ui       UnitImpl
     * @param null|int[] $us       UnitSpec
     * @param bool       $expected The sub-queries that are required for this combination of filters
     * @throws Filter\BrokenFilterException
     * @throws InvalidColumnException
     * @throws AssertionFailedError
     * @throws UnderflowException
     * @throws UnexpectedValueException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_subQueriesRequired')]
    public function test_requiredSubQueries($uis, $f, $p, $ui, $us, $expected): void
    {
        $qbt             = $this->getTester();
        $filters         = $this->buildContextFilterArray($uis, $f, $p, $ui, $us);
        $completeFilters = $qbt->insertMissingFilters($filters);

        [$query, $params] = $qbt->getSqlStringForFilters($completeFilters);

        $message = $this->toStr($uis) . $this->toStr($f) . $this->toStr($p) . $this->toStr($ui) . $this->toStr($us) .
            "Query: '$query'\n                    Params: {$this->paramsString($params)}\n";

        // Ensure we got at least 1 sub-query back from builder
        $found = (bool)preg_match_all('/(\d) AS \'case\'/', $query, $matches);
        $this->assertTrue($found, "No sub-queries recognised: $message");
        $this->assertEquals($expected, $matches[1], $message);
    }


    /**
     * Test that the simplification logic produces expected results
     *
     * @param array|null $aTypeIn
     * @param array|null $capIndIn
     * @param array|null $markPerCapIn
     * @param array|null $aTypeOut
     * @param array|null $capIndOut
     * @param array|null $markPerCapOut
     * @throws Filter\BrokenFilterException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_simplificationLogic')]
    public function test_simplificationLogic(
        ?array $aTypeIn,
        ?array $capIndIn,
        ?array $markPerCapIn,
        ?array $aTypeOut,
        ?array $capIndOut,
        ?array $markPerCapOut
    ): void {
        $qbt             = $this->getTester();
        $filters         = $this->buildRubricUsageFilterArray($aTypeIn, $capIndIn, $markPerCapIn);
        $completeFilters = $qbt->insertMissingFilters($filters);
        $simplified      = $qbt->simplifyFilters($completeFilters);

        $expectedAType = $aTypeOut === null ? null : new Collection($aTypeOut);
        $expectedCapInd = $capIndOut === null ? null : new Collection($capIndOut);
        $expectedMarkPerCap = $markPerCapOut === null ? null : new Collection($markPerCapOut);

        $this->assertEquals($expectedAType, $simplified[AssessmentFilters\AssessmentType\Definition::class]);
        $this->assertEquals($expectedCapInd, $simplified[AssessmentFilters\RubricUsage\Definition::class]);
        $this->assertEquals($expectedMarkPerCap, $simplified[AssessmentFilters\MarkPerCap\Definition::class]);
    }


    // ---------------- helpers --------------


    /**
     * Return a compact string representation of NULL or an int[] for debugging
     *
     * @param Collection|array|int[]|null[]|null $var
     * @param int                                $level
     * @return string
     */
    private function toStr($var, $level = 0): string
    {
        if (null === $var) {
            return 'NULL' . (!$level ? "\n" : '');
        }
        if (!is_iterable($var)) {
            return (string)$var;
        }
        $string = '[';
        $first = true;
        foreach ($var as $key => $val) {
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
    private function paramsString(array $params): string
    {
        $string = '[';
        $first = true;
        foreach ($params as $key => $val) {
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
    private function buildContextFilterArray($uis, $f, $p, $ui, $us): array
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
    private function buildNonContextFilterArray($a, $tc, $td, $wt, $wk): array
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
     * Build a filter array where null filters are included but their value is null
     *
     * @param null|int[] $a AssessmentType
     * @param array|null $capInd
     * @param array|null $markPerCap
     * @return mixed[]
     */
    private function buildRubricUsageFilterArray(?array $a, ?array $capInd, ?array $markPerCap): array
    {
        return [
            AssessmentFilters\AssessmentType\Definition::class => null === $a          ? null : new Collection($a),
            AssessmentFilters\RubricUsage\Definition::class    => null === $capInd     ? null : new Collection($capInd),
            AssessmentFilters\MarkPerCap\Definition::class     => null === $markPerCap ? null : new Collection($markPerCap),
        ];
    }


    /**
     * @return TestableIdFinder
     */
    private function getTester(): TestableIdFinder
    {
        $mockConnector = new MockConnector($this);
        return TestableIdFinder::getInstance(
            Filter\Mapper::getInstance()->setDatabase($mockConnector),
            Assessment\Mapper::getInstance()->setDatabase($mockConnector),
        );
    }

    // ---------------- data providers --------------

    /**
     * Returns all 3^5 = 243 permutations and whether results are possible
     * @return array
     */
    public static function provider_doContextFiltersGuaranteeEmptySet(): array
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
    public static function provider_doNonContextGuaranteeEmptySet(): array
    {

        $nll = null;
        $emt = [];
        $ntE = [1 => 1];

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
     * Truth table for whether some combination of non-context filters guarantees an empty set
     * (and hence we can short-circuit without hitting the DB)
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public static function provider_doRubricsFiltersGuaranteeEmptySet(): array
    {
        // Activity Type keys
        $dueWorkKey = AssessmentFilters\AssessmentType\Definition::KEY_DUE_WORK;
        $projectKey = AssessmentFilters\AssessmentType\Definition::KEY_PROJECT;

        // RubricUsage keys
        $noRubricUsageKey    = AssessmentFilters\RubricUsage\Definition::KEY_NONE;
        $codec      = new AssessmentFilters\RubricUsage\Codec();
        $aRubricUsageKey = $codec->encodeCapability((new Model())->setId(1));

        // MarkPerCap keys
        $noMarkPerCapKey = AssessmentFilters\MarkPerCap\Definition::KEY_NONE;
        $aMarkPerCapKey  = AssessmentFilters\MarkPerCap\Definition::KEY_ON;

        // Various filter sets possible
        $null  = null;
        $emty = [];

        // Activity filter sets
        $duWk = [$dueWorkKey => $dueWorkKey];               // Just DueWorks
        $ntDW = [$projectKey => $projectKey];               // Just something not a dueWork
        $mixd = array_merge($duWk, $ntDW);                  // Mixed DueWorks and non-DueWorks

        // Rubric Usage filter sets
        $noCI = [$noRubricUsageKey => $noRubricUsageKey];   // No capabilities or indicators used
        $aCpI = [$aRubricUsageKey => $aRubricUsageKey];     // One specific capability/indicator usage
        $mxCI = array_merge($noCI, $aCpI);                  // Mixed capability/indicator usage

        // MarkPerCap filter sets
        $nMPC = [$noMarkPerCapKey => $noMarkPerCapKey];     // No MarkPerCap attribute
        $aMPC = [$aMarkPerCapKey => $aMarkPerCapKey];       // MarkPerCap ON
        $mMPC = array_merge($nMPC, $aMPC);                  // Mixed of no MarkPerCap, and some specific value

        // [ActivityType, RubricUsage, MarkPerCap, alwaysEmpty]?
        return [
            [$null, $null, $null, false],
            [$null, $emty, $null, true],
            [$null, $noCI, $null, false],
            [$null, $aCpI, $null, false],
            [$null, $mxCI, $null, false],

            [$emty, $null, $null, true],
            [$emty, $emty, $null, true],
            [$emty, $noCI, $null, true],
            [$emty, $aCpI, $null, true],
            [$emty, $mxCI, $null, true],

            [$duWk, $null, $null, false],
            [$duWk, $emty, $null, true],
            [$duWk, $noCI, $null, false],
            [$duWk, $aCpI, $null, false],
            [$duWk, $mxCI, $null, false],

            [$ntDW, $null, $null, false],
            [$ntDW, $emty, $null, true],
            [$ntDW, $noCI, $null, false],
            [$ntDW, $aCpI, $null, true],
            [$ntDW, $mxCI, $null, false],

            [$mixd, $null, $null, false],
            [$mixd, $emty, $null, true],
            [$mixd, $noCI, $null, false],
            [$mixd, $aCpI, $null, false],
            [$mixd, $mxCI, $null, false],
            //
            [$null, $null, $emty, true],
            [$null, $emty, $emty, true],
            [$null, $noCI, $emty, true],
            [$null, $aCpI, $emty, true],
            [$null, $mxCI, $emty, true],

            [$emty, $null, $emty, true],
            [$emty, $emty, $emty, true],
            [$emty, $noCI, $emty, true],
            [$emty, $aCpI, $emty, true],
            [$emty, $mxCI, $emty, true],

            [$duWk, $null, $emty, true],
            [$duWk, $emty, $emty, true],
            [$duWk, $noCI, $emty, true],
            [$duWk, $aCpI, $emty, true],
            [$duWk, $mxCI, $emty, true],

            [$ntDW, $null, $emty, true],
            [$ntDW, $emty, $emty, true],
            [$ntDW, $noCI, $emty, true],
            [$ntDW, $aCpI, $emty, true],
            [$ntDW, $mxCI, $emty, true],

            [$mixd, $null, $emty, true],
            [$mixd, $emty, $emty, true],
            [$mixd, $noCI, $emty, true],
            [$mixd, $aCpI, $emty, true],
            [$mixd, $mxCI, $emty, true],

            [$null, $null, $nMPC, false],
            [$null, $emty, $nMPC, true],
            [$null, $noCI, $nMPC, false],
            [$null, $aCpI, $nMPC, true],
            [$null, $mxCI, $nMPC, false],

            [$emty, $null, $nMPC, true],
            [$emty, $emty, $nMPC, true],
            [$emty, $noCI, $nMPC, true],
            [$emty, $aCpI, $nMPC, true],
            [$emty, $mxCI, $nMPC, true],

            [$duWk, $null, $nMPC, false],
            [$duWk, $emty, $nMPC, true],
            [$duWk, $noCI, $nMPC, false],
            [$duWk, $aCpI, $nMPC, true],
            [$duWk, $mxCI, $nMPC, false],

            [$ntDW, $null, $nMPC, false],
            [$ntDW, $emty, $nMPC, true],
            [$ntDW, $noCI, $nMPC, false],
            [$ntDW, $aCpI, $nMPC, true],
            [$ntDW, $mxCI, $nMPC, false],

            [$mixd, $null, $nMPC, false],
            [$mixd, $emty, $nMPC, true],
            [$mixd, $noCI, $nMPC, false],
            [$mixd, $aCpI, $nMPC, true],
            [$mixd, $mxCI, $nMPC, false],
            //
            [$null, $null, $aMPC, false],
            [$null, $emty, $aMPC, true],
            [$null, $noCI, $aMPC, true],
            [$null, $aCpI, $aMPC, false],
            [$null, $mxCI, $aMPC, false],

            [$emty, $null, $aMPC, true],
            [$emty, $emty, $aMPC, true],
            [$emty, $noCI, $aMPC, true],
            [$emty, $aCpI, $aMPC, true],
            [$emty, $mxCI, $aMPC, true],

            [$duWk, $null, $aMPC, false],
            [$duWk, $emty, $aMPC, true],
            [$duWk, $noCI, $aMPC, true],
            [$duWk, $aCpI, $aMPC, false],
            [$duWk, $mxCI, $aMPC, false],

            [$ntDW, $null, $aMPC, true],
            [$ntDW, $emty, $aMPC, true],
            [$ntDW, $noCI, $aMPC, true],
            [$ntDW, $aCpI, $aMPC, true],
            [$ntDW, $mxCI, $aMPC, true],

            [$mixd, $null, $aMPC, false],
            [$mixd, $emty, $aMPC, true],
            [$mixd, $noCI, $aMPC, true],
            [$mixd, $aCpI, $aMPC, false],
            [$mixd, $mxCI, $aMPC, false],
            //
            [$null, $null, $mMPC, false],
            [$null, $emty, $mMPC, true],
            [$null, $noCI, $mMPC, false],
            [$null, $aCpI, $mMPC, false],
            [$null, $mxCI, $mMPC, false],

            [$emty, $null, $mMPC, true],
            [$emty, $emty, $mMPC, true],
            [$emty, $noCI, $mMPC, true],
            [$emty, $aCpI, $mMPC, true],
            [$emty, $mxCI, $mMPC, true],

            [$duWk, $null, $mMPC, false],
            [$duWk, $emty, $mMPC, true],
            [$duWk, $noCI, $mMPC, false],
            [$duWk, $aCpI, $mMPC, false],
            [$duWk, $mxCI, $mMPC, false],

            [$ntDW, $null, $mMPC, false],
            [$ntDW, $emty, $mMPC, true],
            [$ntDW, $noCI, $mMPC, false],
            [$ntDW, $aCpI, $mMPC, true],
            [$ntDW, $mxCI, $mMPC, false],

            [$mixd, $null, $mMPC, false],
            [$mixd, $emty, $mMPC, true],
            [$mixd, $noCI, $mMPC, false],
            [$mixd, $aCpI, $mMPC, false],
            [$mixd, $mxCI, $mMPC, false],
        ];
    }

    /**
     * For all 108 permutations that are not guaranteed to return empty sets, which subQueries are required?
     * @return array
     */
    public static function provider_subQueriesRequired(): array
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


    /**
     * @return array
     * @throws InvalidArgumentException
     */
    public static function provider_simplificationLogic(): array
    {
        // Activity Type keys
        $dueWorkKey = AssessmentFilters\AssessmentType\Definition::KEY_DUE_WORK;
        $projectKey = AssessmentFilters\AssessmentType\Definition::KEY_PROJECT;

        // RubricUsage keys
        $noRubricUsageKey    = AssessmentFilters\RubricUsage\Definition::KEY_NONE;
        $codec      = new AssessmentFilters\RubricUsage\Codec();
        $aRubricUsageKey = $codec->encodeCapability((new Model())->setId(1));

        // MarkPerCap keys
        $noMarkPerCapKey = AssessmentFilters\MarkPerCap\Definition::KEY_NONE;
        $aMarkPerCapKey  = AssessmentFilters\MarkPerCap\Definition::KEY_ON;

        // Various filter sets possible

        // Activity filter sets
        $duWk = [$dueWorkKey => $dueWorkKey];               // Just DueWorks
        $ntDW = [$projectKey => $projectKey];               // Just something not a dueWork
        $mixd = array_merge($duWk, $ntDW);                  // Mixed DueWorks and non-DueWorks

        // Rubric Usage filter sets
        $noCI = [$noRubricUsageKey => $noRubricUsageKey];   // No capabilities or indicators used
        $aCpI = [$aRubricUsageKey => $aRubricUsageKey];     // One specific capability/indicator usage
        $mxCI = array_merge($noCI, $aCpI);                  // Mixed capability/indicator usage

        // MarkPerCap filter sets
        $nMPC = [$noMarkPerCapKey => $noMarkPerCapKey];     // No MarkPerCap attribute
        $aMPC = [$aMarkPerCapKey => $aMarkPerCapKey];       // MarkPerCap ON
        $mMPC = array_merge($nMPC, $aMPC);                  // Mixed of no MarkPerCap, and some specific value

        // [           IN             ] [           OUT              ]
        // aType, rubricUse, markPerCap, aType, rubricUse, markPerCap,
        return [
             0 => [$duWk, $noCI, $nMPC, $duWk,  null, $nMPC, ],
             1 => [$duWk, $noCI, $mMPC, $duWk, $noCI,  null, ],
             2 => [$duWk, $aCpI, $aMPC,  null, $aCpI, $aMPC, ],
             3 => [$duWk, $aCpI, $mMPC,  null, $aCpI, $mMPC, ],
             4 => [$duWk, $mxCI, $nMPC, $duWk,  null, $nMPC, ],
             5 => [$duWk, $mxCI, $aMPC,  null, $mxCI, $aMPC, ],
             6 => [$duWk, $mxCI, $mMPC, $duWk, $mxCI, $mMPC, ],
             7 => [$ntDW, $noCI, $nMPC, $ntDW,  null,  null, ],
             8 => [$ntDW, $noCI, $mMPC, $ntDW,  null,  null, ],
             9 => [$ntDW, $mxCI, $nMPC, $ntDW,  null,  null, ],
            10 => [$ntDW, $mxCI, $mMPC, $ntDW,  null,  null, ],
            11 => [$mixd, $noCI, $nMPC, $mixd,  null, $nMPC, ],
            12 => [$mixd, $noCI, $mMPC, $mixd, $noCI,  null, ],
            13 => [$mixd, $aCpI, $aMPC,  null, $aCpI, $aMPC, ],
            14 => [$mixd, $aCpI, $mMPC,  null, $aCpI, $mMPC, ],
            15 => [$mixd, $mxCI, $nMPC, $mixd,  null, $nMPC, ],
            16 => [$mixd, $mxCI, $aMPC,  null, $mxCI, $aMPC, ],
            17 => [$mixd, $mxCI, $mMPC, $mixd, $mxCI, $mMPC, ],
        ];
    }
}
