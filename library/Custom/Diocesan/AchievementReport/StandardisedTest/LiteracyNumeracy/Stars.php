<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy;

/**
 * Class Stars
 *
 * Assemble series data for the stars
 *
 * @package Custom\Diocesan\AchievementReport\StandardisedTest\LiteracyNumeracy
 */
class Stars
{

    /**
     * Whether there was any data found
     *
     * @var bool
     */
    private $hasData = false;


    /**
     * Mine out the stars as individual series
     *
     * @param array $groupData
     * @param array $areaSeqMapping
     * @param array $template
     * @param int   $numSeries
     * @return array
     */
    public function generate(array $groupData, array $areaSeqMapping, array $template, int $numSeries): array
    {
        $index = 0;

        $payload = [];

        foreach ($groupData as $category => $vals) {
            for ($i = 0; $i < $numSeries; $i++) {

                /* Initialise with string 'null' for each x category
                 */
                if (!isset($payload[$i])) {
                    $payload[$i] = $this->initialisePlaceholder($template);
                }

                /* Insert values algorithmically
                 */
                $payload[$i][$index+$i] = $vals[$areaSeqMapping[$i]];

                /* Might as well do data detection here
                 * If the student has a row with maskseq != 0, they have data
                 */
                if ($vals[$areaSeqMapping[$i]]) {
                    $this->hasData = true;
                }
            }

            /* Move to the next group of data points
             */
            $index += $numSeries;
        }

        return $payload;
    }


    /**
     * @return bool
     */
    public function hasData(): bool
    {
        return $this->hasData;
    }


    /**
     * Initialise a stack of placeholder values for x categories
     *
     * @param array $template
     * @return array
     */
    private function initialisePlaceholder(array $template): array
    {
        return array_fill(0, \count($template), 'null');
    }
}
