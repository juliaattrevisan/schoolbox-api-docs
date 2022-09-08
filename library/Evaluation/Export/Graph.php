<?php

use Schoolbox\User\Formatter\Factory;

/**
 * Evaluation_Export_Graph
 *
 * Manages results export to various formats
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Evaluation_Export_Graph
{
    /**
     * @var array
     */
    protected $sectionValues = array();

    /**
     * @var bool
     */
    protected $filterByQuestion = false;

    /**
     * @var bool
     */
    protected $legendHTML = false;

    /**
     * @var string[]
     */
    protected $columnHeadings = array("Synergy ID", "Respondent", "Response", "Teacher's Comment");

    /**
     * @var int
     */
    private $assess_id;

    /**
     * @var Evaluation_Question
     */
    private $questionHandler;

    /**
     * @var Evaluation_Factory
     */
    private $factory;

    /**
     * @var Evaluation_Section
     */
    private $sectionHandler;

    /**
     * @var Evaluation_Attempt
     */
    private $attemptHandler;

    /**
     * Constructor
     *
     * @param array $assess
     * @param Evaluation_Factory $factory
     * @param Evaluation_Section $sectionHandler
     * @param Evaluation_Attempt $attemptHandler
     * @param Evaluation_Question $questionHandler
     */
    public function __construct($assess, $factory, $sectionHandler, $attemptHandler, $questionHandler)
    {
        $this->setAssessID($assess['assess_id']);
        $this->setFactory($factory);
        $this->setSectionHandler($sectionHandler);
        $this->setAttemptHandler($attemptHandler);
        $this->setQuestionHandler($questionHandler);
    }

    /**
     * Get needed fields for a given list of questions. This can be used
     * to create columns for CSV export, or as headings when graphing.
     *
     * @param array $allQuestions
     * @return array
     */
    protected function getQuestionData($allQuestions)
    {
        $allData = array();

        for ($i = 0; $i < count($allQuestions); $i++) {
            $currentQuestion = $allQuestions[$i];

            /* Store some extra details about the question. question_id is used by
            * the templating engine for uniqueness; type_id is for loading an
            * appropriate answerHandler, which contains the graphing method
            */
            $allData[] = array(
                'question' => $currentQuestion['question'],
                'question_id' => $currentQuestion['question_id'],
                'type_id' => $currentQuestion['type_id'],
            );
        }

        return $allData;
    }

    /**
     * Updates the summary tally of responses for the given question
     *
     * @param array $currentQuestion
     * @param array $currentAttempt
     * @param int $sectionIndex
     * @param int $questionIndex
     * @return void
     */
    protected function updateResponseSummary($currentQuestion, $currentAttempt, $sectionIndex, $questionIndex)
    {
        if ($currentQuestion['type_id']) {
            $answerHandler = $this->getFactory()->createAnswerModel($currentQuestion['type_id']);

            /* Load answer template into answerOption block
             */
            /** @var array|string $currentResponse */
            $currentResponse = $answerHandler->exportResponseToArray($currentAttempt['attempt_id'], $currentQuestion['question_id']);

            if (is_array($currentResponse)) {
                unset($currentResponse['other']);
                foreach ($currentResponse as $individualResponse) {
                    /* Find out if you need to extract the response text for the
                     * options that were available
                     */
                    if (!isset($this->sectionValues[$sectionIndex]['questions'][$questionIndex]['summary'][$individualResponse]['text'])) {
                        $answerText = $answerHandler->getAnswerText($currentQuestion['question_id'], $individualResponse);

                        $this->sectionValues[$sectionIndex]['questions'][$questionIndex]['summary'][$individualResponse]['text'] = $answerText;
                    }

                    $this->sectionValues[$sectionIndex]['questions'][$questionIndex]['summary'][$individualResponse]['values'] ??= 0;
                    $this->sectionValues[$sectionIndex]['questions'][$questionIndex]['summary'][$individualResponse]['values']++;
                }
            } elseif ($currentResponse) {
            /* Inconcatenatable non-summarisable data (short answer) which only
             * need to be collated into a giant list
             *
             * Also prevent non-responses from being legendised
             */
                $this->sectionValues[$sectionIndex]['questions'][$questionIndex]['summary'][$currentResponse]['values'] ??= 0;
                $this->sectionValues[$sectionIndex]['questions'][$questionIndex]['summary'][$currentResponse]['values']++;
            }
        }
    }

    /**
     * Set all the possible answers that could be given in each question
     * for a section
     *
     * @param array $allQuestions
     * @param int $sectionIndex
     * @return void
     */
    private function accumulatePossibleQuestions($allQuestions, $sectionIndex)
    {
        for ($i = 0; $i < count($allQuestions); $i++) {
            if ($allQuestions[$i]['type_id']) {
                $answerHandler = $this->getFactory()->createAnswerModel($allQuestions[$i]['type_id']);

                $answerRecord = $answerHandler->getForQuestion($allQuestions[$i]['question_id']);
                $allAnswers = unserialize($answerRecord['answer']);

                /* If this was a multi-choice or radio variant, answers
                 * are stored in a serialized array format
                 *
                 * Short answers are not serialized
                 */
                if (is_array($allAnswers) && count($allAnswers)) {
                    foreach ($allAnswers as $currentAnswer) {
                        $this->sectionValues[$sectionIndex]['questions'][$i]['summary'][$currentAnswer->value]['values'] = 0;
                        $this->sectionValues[$sectionIndex]['questions'][$i]['summary'][$currentAnswer->value]['text'] = $currentAnswer->description ?? '';
                    }
                }
            }
        }
    }

    /**
     * Gets the questions and summary of results for each section
     *
     * @return array
     */
    public function getSectionValues()
    {
        $formatter = (new Factory())->buildFromNothing();

        /* Determine whether to get all sections, or just the one belonging to
         * the question by which to filter
         */
        if ($this->getFilterByQuestion()) {
            $question = $this->getQuestionHandler()->getByID($this->getFilterByQuestion());

            /* Need to be in array format, for iteration
             */
            $allSections = array($this->getSectionHandler()->getByID($question['section_id']));
        } else {
            $allSections = $this->getSectionHandler()->getList($this->getAssessID());
        }

        /* Process
         */
        for ($k = 0; $k < count($allSections); $k++) {
            $currentSection = $allSections[$k];

            /* Initialise array data
             */
            $this->sectionValues[$k]['section'] = $currentSection['title'];

            /* Get a list of questions to use as heading data
            */
            $allQuestions = $this->getSectionHandler()->getContents($this->getAssessID(), $currentSection['section_id'], false, $this->getFilterByQuestion());

            /* If there are questions for this survey
             */
            if ($allQuestions) {
                /* Get the question text, id and type, store it
                 * with its summary of results and attempt list
                 */
                $this->sectionValues[$k]['questions'] = $this->getQuestionData($allQuestions);

                /* Initialise all possible responses
                 */
                $this->accumulatePossibleQuestions($allQuestions, $k);

                /* Get attempts for the assessment
                 */
                $allAttempts = $this->getAttemptHandler()->getListByAssessAndSection($this->getAssessID(), $currentSection['section_id']);

                if ($allAttempts) {
                    for ($j = 0; $j < count($allAttempts); $j++) {
                        $currentAttempt = $allAttempts[$j];

                        if ($allQuestions) {
                            /* Accumulate attempt data
                             */
                            for ($i = 0; $i < count($allQuestions); $i++) {
                                $this->updateResponseSummary($allQuestions[$i], $allAttempts[$j], $k, $i);

                                /* This if() clause allows for sections containing no questions.
                                 * because they come out with getContents()
                                 */
                                if ($allQuestions[$i]['type_id']) {
                                    $answerHandler = $this->getFactory()->createAnswerModel($allQuestions[$i]['type_id']);

                                    /* Specify whether to include html formatting in
                                     * this output
                                     */
                                    $answerHandler->setLegendHTML($this->getLegendHTML());

                                    /* Load answer template into answerOption block
                                     */
                                    $data = $answerHandler->resultsForQuestion($currentAttempt['attempt_id'], $allQuestions[$i]['question_id']);

                                    $commentValues = $data['comment'];

                                    $this->sectionValues[$k]['questions'][$i]['attempts'][$j] = array(
                                        'synergy_id' => $currentAttempt['synergy_id'],
                                        'name' => $formatter->formatArray($currentAttempt),
                                        'responses' => $data['responses'],
                                        'responsesData' => $data['responsesData'],
                                        'comment' => html_entity_decode(str_replace("\n", '', strip_tags($commentValues)), ENT_QUOTES),
                                        'start' => $currentAttempt['start'],
                                        'finish' => $currentAttempt['finish'],
                                    );
                                }
                            }
                        }
                    }
                }
            } else {
                /* With unmatched style and elan, gracefully remove sections which have no questions
                 * so they don't appear in the graph screen
                 */
                unset($this->sectionValues[$k]);
            }
        }


        return $this->sectionValues;
    }

    /**
     * Export responses from all attempts for a particular assess and question
     *
     * @param int $assess_id
     * @param int $question_id
     * @param int $questionIndex
     * @return void
     */
    public function exportResponsesToCSV($assess_id, $question_id, $questionIndex)
    {
        $sectionValues = $this->getSectionValues();

        /* Format output
         */
        $filename_id = $assess_id;

        if (is_numeric($question_id)) {
            $filename_id .= "_{$question_id}";
        }

        header('Content-type: text/ascii');
        header('Content-disposition: attachment; filename="exported_response_' . $filename_id . '.csv"');

        if (is_array($sectionValues) && count($sectionValues)) {
            foreach ($sectionValues as $currentSection) {
                echo 'Section: ' . $currentSection['section'] . "\n";

                if (is_array($currentSection['questions']) && count($currentSection['questions'])) {
                    /* Question index
                     */
                    $i = $questionIndex;

                    foreach ($currentSection['questions'] as $currentQuestion) {
                        $this->outputCSV2(array(
                            array("Q$i. " . strip_tags($currentQuestion['question']) . "\n"),
                            $this->getColumnHeadings(),
                        ));

                        /* Generate attempts for this question
                         */
                        if (is_array($currentQuestion['attempts'])) {
                            foreach ($currentQuestion['attempts'] as $currentAttempt) {
                                $attemptList = array();

                                $attemptList[] = $currentAttempt['synergy_id'];
                                $attemptList[] = $currentAttempt['name'];
                                // if freetext responses have html entities, convert them to plaintext
                                $attemptList[] = html_entity_decode($currentAttempt['responses'], ENT_COMPAT, 'UTF-8');
                                $attemptList[] = $currentAttempt['comment'];

                                $this->outputCSV2(array($attemptList));
                            }
                        }

                        echo "\n";

                        $i++;
                    }
                }

                echo "\n";
            }
        }

        /* Prevent other template crap from rendering ;)
         */
        exit(1);
    }

    /**
     * Output a csv row, using the __outputCSV method
     *
     * @param array $data
     * @return void
     */
    public function outputCSV2($data)
    {
        $outstream = fopen("php://output", 'rectum');

        array_walk($data, array($this, '__outputCSV'), $outstream);

        fclose($outstream);
    }

    /**
     * Output a csv row.
     *
     * @param array $vals
     * @param string $key
     * @param resource $filehandler
     * @return void
     */
    public function __outputCSV(&$vals, $key, $filehandler)
    {
        fputcsv($filehandler, $vals, ',', '"');
    }

    /* Getters/Setters
     */

    /**
     * Get column headings in export file
     *
     * @return array
     */
    public function getColumnHeadings()
    {
        return $this->columnHeadings;
    }

    /**
     * Sets legendHTML
     *
     * @param bool $val
     * @return void
     */
    public function setLegendHTML($val)
    {
        $this->legendHTML = $val;
    }

    /**
     * Gets legendHTML
     *
     * @return bool
     */
    public function getLegendHTML()
    {
        return $this->legendHTML;
    }

    /**
     * Sets assess_id
     *
     * @param int $assess_id
     * @return void
     */
    protected function setAssessID($assess_id)
    {
        $this->assess_id = $assess_id;
    }

    /**
     * Gets assess_id
     *
     * @return int
     */
    protected function getAssessID()
    {
        return $this->assess_id;
    }

    /**
     * Sets a factory
     *
     * @param Evaluation_Factory $factory
     * @return void
     */
    protected function setFactory($factory)
    {
        $this->factory = $factory;
    }

    /**
     * Gets a factory
     *
     * @return Evaluation_Factory
     */
    protected function getFactory()
    {
        return $this->factory;
    }

    /**
     * Sets a sectionHandler
     *
     * @param Evaluation_Section $sectionHandler
     * @return void
     */
    protected function setSectionHandler($sectionHandler)
    {
        $this->sectionHandler = $sectionHandler;
    }

    /**
     * Gets a sectionHandler
     *
     * @return Evaluation_Section
     */
    protected function getSectionHandler()
    {
        return $this->sectionHandler;
    }

    /**
     * Sets a attemptHandler
     *
     * @param Evaluation_Attempt $attemptHandler
     * @return void
     */
    protected function setAttemptHandler($attemptHandler)
    {
        $this->attemptHandler = $attemptHandler;
    }

    /**
     * Gets a attemptHandler
     *
     * @return Evaluation_Attempt
     */
    protected function getAttemptHandler()
    {
        return $this->attemptHandler;
    }

    /**
     * Sets a questionHandler
     *
     * @param Evaluation_Question $questionHandler
     * @return void
     */
    protected function setQuestionHandler($questionHandler)
    {
        $this->questionHandler = $questionHandler;
    }

    /**
     * Gets a questionHandler
     *
     * @return Evaluation_Question
     */
    protected function getQuestionHandler()
    {
        return $this->questionHandler;
    }

    /**
     * Set a question_id to filter by
     *
     * @param int $question_id
     * @return void
     */
    public function setFilterByQuestion($question_id)
    {
        $this->filterByQuestion = $question_id;
    }

    /**
     * Get a question_id to filter by
     *
     * @return bool
     */
    public function getFilterByQuestion()
    {
        return $this->filterByQuestion;
    }
}
