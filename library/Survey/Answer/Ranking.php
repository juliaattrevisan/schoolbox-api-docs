<?php

/**
 * Survey_Answer_Ranking
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Survey_Answer_Ranking extends Evaluation_Answer_Ranking
{

    /**
     * Template file containing the graph
     *
     * @var string
     */
    protected $graphTemplateFile = 'survey/answer/ranking/graph.html';


    /**
     * Number of ticks on the x axis
     *
     * @var int
     */
    private $ticks = 0;


    /**
     * Graph the results for this answer type
     *
     * @param SitePage $page
     * @param array $currentQuestion
     * @return void
     */
    public function graphResults($page, $currentQuestion)
    {
        $componentHandle = 'temp' . $currentQuestion['question_id'];

        $page->setFile($componentHandle, $this->getGraphTemplateFile());
        $page->setBlock($componentHandle, 'series', 'series_block');
        $page->setBlock('series', 'series_separator', 'series_separator_block');

        $dataset = $this->process($this->generateDataSet((int)$currentQuestion['question_id']));
        $ticks   = $this->generateTicks($this->ticks);

        $i = 0;

        foreach ($dataset as $key => $data) {
            $page->setVar(
                array(
                    'SERIES_NAME' => $key,
                    'SERIES_DATA' => $data,
                )
            );

            if ($i < count($dataset) - 1) {
                $page->parse('series_separator_block', 'series_separator');
            }

            $i++;

            $page->parse('series_block', 'series', true);

            /* Clear children after parsing out the parent
             */
            $page->setVar(
                array(
                    'series_separator_block' => ''
                )
            );
        }

        $page->setVar(
            array(
                'TICKS'    => $ticks,
                'GRAPH_ID' => 'surveyQuestion_'
                    . intval($currentQuestion['question_id'])
                    . '_'
                    . md5($currentQuestion['question']),
            )
        );

        $page->parse('graph_block', $componentHandle);
    }


    /**
     * Ordinal
     *
     * From php.net
     *
     * @param int $num
     * @return string
     */
    private function ordinal($num)
    {

        // Special case "teenth"
        if (($num / 10) % 10 != 1) {
            // Handle 1st, 2nd, 3rd
            switch ($num % 10) {
                case 1:
                    return $num . 'st';
                case 2:
                    return $num . 'nd';
                case 3:
                    return $num . 'rd';
            }
        }
        // Everything else is "nth"
        return $num . 'th';
    }


    /**
     * Generate ticks
     *
     * @param int $numTicks
     * @return string
     */
    private function generateTicks($numTicks)
    {
        $ticks = array();

        for ($i = 1; $i <= $numTicks; $i++) {
            $ticks[] = "$i:'{$this->ordinal($i)}'";
        }

        return '{' . implode(', ', $ticks) . '}';
    }


    /**
     * Manufacture a flot stacked barchart
     *
     * @param array $dataset
     * @return array
     */
    private function process($dataset)
    {
        $newDataset   = array();
        $finalDataset = array();

        /* Goddamn you phplib
         */
        for ($i = 0; $i < count($dataset); $i++) {
            $keys   = array_keys($dataset[$i]);
            $values = array_values($dataset[$i]);

            /* Generate a dataset for flot
             */
            for ($j = 0; $j < count($keys); $j++) {
                $graphIndex = $i + 1;

                /* Note that flot is buggy:
                 * https://code.google.com/p/flot/issues/detail?id=247
                 */
                if (!$values[$j]) {
                    $val = 0;
                } else {
                    $val = $values[$j];
                }

                $newDataset[$keys[$j]][] = "[{$graphIndex}, {$val}]";
            }
        }

        foreach ($newDataset as $key => $value) {
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
     * Array
     * (
     * [0] => Array
     * (
     * [dog] => 2
     * [cat] => 1
     * [rhino] => 0
     * )
     *
     * [1] => Array
     * (
     * [rhino] => 1
     * [dog] => 1
     * [cat] => 1
     * )
     *
     * [2] => Array
     * (
     * [rhino] => 2
     * [cat] => 1
     * [dog] => 0
     * )
     * )
     *
     * Only the maximum values are used; anything less than the maximum
     * is discarded, which results in $finalScoreboard
     *
     * Array
     * (
     * [0] => Array
     * (
     * [dog] => 2
     * )
     *
     * [1] => Array
     * (
     * [rhino] => 1
     * [dog] => 1
     * [cat] => 1
     * )
     *
     * [2] => Array
     * (
     * [rhino] => 2
     * )
     * )
     *
     * @param int $questionId
     * @return array
     */
    private function generateDataSet($questionId)
    {
        $possibilities   = array();
        $scoreboard      = array();

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
            $this->ticks = count($possibilities);
        }

        /* Keep this for zerofilling
         */
        $possibilityKeys = $possibilities;
        $possibilities   = array_fill_keys($possibilityKeys, 0);

        for ($i = 0; $i < count($possibilities); $i++) {
            $scoreboard[$i] = $possibilities;
        }

        /* Find all the responses that people made
         */
        $allResults = $this->getResponseHandler()->getResponsesToQuestion($questionId);

        /* Collate each response into $allValues
         */
        if (is_array($allResults)) {
            foreach ($allResults as $currentResult) {
                $response = unserialize($currentResult['response']);
                if (!is_array($response)) {
                    continue;
                }

                for ($i = 0; $i < count($response); $i++) {
                    $scoreboard[$i][$response[$i]]++;
                }
            }
        }

        /* James prefers to see all the values, not just the maximum
         * in each position in the stack
         *
         * This is going to get ugly for more than ~10-20 items in the stack.
         */
        $finalScoreboard = $scoreboard;

        return $finalScoreboard;
    }


    /**
     * Show the response someone submitted and also show the correct answer.
     * Provide an option for the teacher to review the mark and adjust.
     *
     * @param SitePage $page
     * @param int $attemptId
     * @param int $question_id
     * @return void
     */
    public function reviewResponse($page, $attemptId, $question_id)
    {
        $right = array();
        $existing = $this->getResponseHandler()->isResponseExists($attemptId, $question_id);

        if ($existing['response']) {
            $right = unserialize($existing['response']);
        }

        /* Define special blocks to handle this special answerOptions scenario,
         * because we have three columns to parse per iteration.
         */
        $page->setBlock('answerFormFragment', 'answerOptionRight', 'answerOptionRight_block');

        $answerGroup = $this->getByQuestionID($question_id);

        if ($answerGroup) {
            $page->setVar(
                array(
                    'ANSWER_ID'   => $answerGroup['answer_id'],
                    'RESPONSE_ID' => $existing['response_id'],
                )
            );

            $allAnswers = unserialize($answerGroup['answer']);

            for ($i = 0; $i < count($allAnswers); $i++) {
                $page->setVar(
                    array(
                        'ANSWER_LEFT_VALUE' => ($i + 1) . ')',
                        'ANSWER_RIGHT_VALUE' => $right[$i],
                    )
                );

                $page->parse('answerOptionRight_block', 'answerOptionRight', true);
            }
        }
    }


    /**
     * Configure mandatoryness and other settings for this response
     *
     * @param SitePage $page
     * @param int $question_id
     */
    protected function configureResponse($page, $question_id)
    {
        $question = $this->getQuestionHandler()->getByID($question_id);
        $config = json_decode($question['config']);

        $page->setVar(array(
            'CONFIGURATION_MANDATORY' => $config->mandatory ? 'required' : '',
        ));
    }
}
