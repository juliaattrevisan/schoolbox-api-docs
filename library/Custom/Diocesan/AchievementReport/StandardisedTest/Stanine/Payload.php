<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\Stanine;

/**
 * Class Payload
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\Stanine
 */
class Payload
{

    /**
     * Payload struct
     *
     * @var array
     */
    private $stack = [
        'graph' => [
            'title' => '',
            'y' => '% of Children'
        ],
        'series' => [
            'student' => [],
            'diocesan' => [],
            'national' => [],
        ]
    ];


    /**
     * Dirty bit
     *
     * @var bool
     */
    private $hasData = false;


    /**
     * Stanine constructor.
     *
     * @param string      $title
     * @param int         $year
     * @param string|null $yAxis
     */
    public function __construct(string $title, int $year, ?string $yAxis = null)
    {
        $this->stack['graph']['title'] = $title;

        /* Custom y-axis label
         */
        if ($yAxis) {
            $this->stack['graph']['yAxis'] = $yAxis;
        }
    }


    /**
     * Aggregate Student series data
     *
     * If they aren't meant to have a value here, use the string 'null' so
     * that highcharts can understand it (it won't understand empty string)
     *
     * @param bool   $condition
     * @param string $x
     * @param string $y
     *
     * @return Payload
     */
    public function aggregateStudent(bool $condition, string $x, string $y): self
    {

        /* An assumption.
         *
         * ThisStudentStanine looks like it equates to Seq, which is the x-axis.
         * ThisStudentStatnine an either equate to Seq, or be zero.
         * If ThisStudentStatnine is zero, I don't render anything.
         *
         * Otherwise, I render the value of IndividualMathsPATStanineMarker in the column
         * corresponding to ThisStudentStanine.
         */
        if ($condition) {
            $this->stack['series']['student'][$x] = $y;

            /* If a value in IndividualMathsPATStanineMarker != 0
             * then this student has data
             */
            if ($y) {
                $this->hasData = true;
            }
        } else {
            $this->stack['series']['student'][$x] = 'null';
        }

        return $this;
    }


    /**
     * Aggregate Diocesan series data
     *
     * @param string $x
     * @param string $y
     *
     * @return Payload
     */
    public function aggregateDiocesan(string $x, string $y): self
    {
        $this->stack['series']['diocesan'][$x] = $y;

        return $this;
    }


    /**
     * Aggregate National series data
     *
     * @param string $x
     * @param string $y
     *
     * @return Payload
     */
    public function aggregateNational(string $x, string $y): self
    {
        $this->stack['series']['national'][$x] = $y;

        return $this;
    }


    /**
     * Get the currently-aggregated data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->stack;
    }


    /**
     * @return bool
     */
    public function hasData(): bool
    {
        return $this->hasData;
    }
}
