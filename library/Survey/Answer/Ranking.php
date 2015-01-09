<?php
/**
 * Survey_Answer_Ranking
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Survey_Answer_Ranking extends Evaluation_Answer_Ranking {
    protected $graphTemplateFile = 'survey/answer/ranking/graph.html';

    /**
     * Setup
     */
    protected function setup() {
        $this->setResponseHandler(new Survey_Response());
    }

    /**
     * Graph the results for this answer type
     *
     * @param  $page
     * @param  $currentQuestion
     * @return void
     */
    public function graphResults($page, $currentQuestion) {
        $componentHandle = 'temp' . $currentQuestion['question_id'];

        $page->setFile($componentHandle, $this->getGraphTemplateFile());

        $dataset = $this->process($this->generateDataSet((int)$currentQuestion['question_id']));

        new \Alaress_Probe($dataset);

        $page->setVar(array(
            'SERIES_NAME' => $key,
            'SERIES_DATA' => $seriesData,
        ));

        $page->parse('graph_block', $componentHandle);
    }

    /**
     * Manufacture a flot stacked barchart
     *
     * @param $dataset
     * @return array
     */
    private function process($dataset) {
        $newDataset = array();
        $finalDataset = array();

        /* Goddamn you phplib
         */
        for ($i = 0; $i < count($dataset); $i++) {
            $keys = array_keys($dataset[$i]);
            $values = array_values($dataset[$i]);

            /* Generate a dataset for flot
             */
            for ($j = 0; $j < count($keys); $j++) {
                $graphIndex = $i+1;
                $newDataset[$keys[$j]][] = "[{$graphIndex}, {$values[$j]}]";
            }
        }

        foreach($newDataset as $key => $value) {
            $finalDataset[$key] = implode(',', $value);
        }

        return $finalDataset;
    }

    /**
     * Generate a "scoreboard" of items.
     *
     * This lists them in order of precedence from the top of the stack
     * to the bottom.
     *
     * Each element in the zero-indexed scoreboard will contain the item
     * (or items) most frequently chosen for that position.
     *
     * e.g.
     * a:3:{i:0;s:3:"dog";i:1;s:3:"cat";i:2;s:5:"rhino";}
     * a:3:{i:0;s:3:"dog";i:1;s:5:"rhino";i:2;s:3:"cat";}
     * a:3:{i:0;s:3:"cat";i:1;s:3:"dog";i:2;s:5:"rhino";}
     *
     * Produces the interim scoreboard
     *
    Array
    (
        [0] => Array
            (
                [dog] => 2
                [cat] => 1
                [rhino] => 0
            )

        [1] => Array
            (
                [rhino] => 1
                [dog] => 1
                [cat] => 1
            )

        [2] => Array
            (
                [rhino] => 2
                [cat] => 1
                [dog] => 0
            )
    )
     *
     * Only the maximum values are used; anything less than the maximum
     * is discarded, which results in $finalScoreboard
     *
    Array
    (
        [0] => Array
            (
                [dog] => 2
            )

        [1] => Array
            (
                [rhino] => 1
                [dog] => 1
                [cat] => 1
            )

        [2] => Array
            (
                [rhino] => 2
            )
    )
     *
     * @param $questionId
     * @return array
     */
    private function generateDataSet($questionId) {
        $possibilities = array();
        $scoreboard = array();
        $finalScoreboard = array();

        /* Find all the possible things to chooze from
         */
        $answer = $this->getByField('question_id', $questionId);

        $values = unserialize($answer['answer']);

        /* Prefill a "scoreboard" of zeroes for each individual response item
         * This represents each individual response item's position in the stack
         * and how many times it is chosen to be in that position in the stack
         */
        for ($i = 0; $i < count($values); $i++) {
            $possibilities[] = $values[$i]->answerRight;
        }

        /* Keep this for zerofilling
         */
        $possibilityKeys = $possibilities;
        $possibilities = array_fill_keys($possibilityKeys, 0);

        for ($i = 0; $i < count($possibilities); $i++) {
            $scoreboard[$i] = $possibilities;
        }

        /* Find all the responses that people made
         */
        $allResults = $this->getResponseHandler()->getAllByField('question_id', $questionId);

        /* Collate each response into $allValues
         */
        foreach ($allResults as $currentResult) {
            $response = unserialize($currentResult['response']);

            for ($i = 0; $i < count($response); $i++) {
                $scoreboard[$i][$response[$i]]++;
            }
        }

        /* NOTE: Gigantic stupid problem encountered
         * https://bugs.php.net/bug.php?id=29992
         *
         * Be uber careful with these foreaches as they corrupt data.
         * Stupid php
         *
         * This is the reason behind the unset()
         */
        foreach ($scoreboard as &$currentPosition) {
            arsort($currentPosition);
        }

        unset($currentPosition);

        /* Find the maximum value and unset anything that isn't the same
         */
        for ($i = 0; $i < count($scoreboard); $i++) {
            $currentPosition = $scoreboard[$i];

            $max = false;

            foreach ($currentPosition as $key => $value) {
                if ($max === false) {
                    $max = $value;
                }

                /* Transfer to a different var
                 */
                if ($value == $max) {
                    $finalScoreboard[$i][$key] = $value;
                }
                /* For continuity in flot
                 */
                else {
                    $finalScoreboard[$i][$key] = 0;
                }
            }
        }

        return $finalScoreboard;
    }
}