<?php
/**
 * Assessment export. Uses the handlers to export their question types into a
 * Moodle format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$assessHandler = new Survey_Main();
$factory = new Survey_Factory();
$questionHandler = new Survey_Question();
$groupHandler = new Group();

if (!is_numeric($this->arg[0])) {
    error(112);
}

$assess = $assessHandler->getByID($this->arg[0]);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
	error(112);
}

if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
    error(125);
}

$surveyExporter = new Survey_Export_Graph($assess, $factory, new Survey_Section(), new Survey_Attempt(), $questionHandler);
$surveyExporter->setLegendHTML(false);

/* Optionally filter by question
 */
if (is_numeric($this->arg[1])) {
	$surveyExporter->setFilterByQuestion($this->arg[1]);
}

$sectionValues = $surveyExporter->getSectionValues();

/* Format output
 */
$this->setFile(TEMPLATE_content, 'survey/resultsForQuestion.html');
$this->setBlock(TEMPLATE_content, 'respondent', 'respondent_block');
$this->setBlock(TEMPLATE_content, 'graph', 'graph_block');



/* Render legend. There should only be one section and one question when using
 * this script, but for polymorphism it appears in an array structure
 */
/*
if (is_array($sectionValues) && count($sectionValues)) {
    if (is_array($sectionValues[0]['questions']) && count($sectionValues[0]['questions'])) {
        if ($questionHandler->hasLegend($sectionValues[0]['questions'][0]['type_id'])) {
            $allLegendItems = $sectionValues[0]['questions'][0]['summary'];

            // Sort legend items explicitly, otherwise they will appear in the
                 order they were submitted

            uksort($allLegendItems, 'cmp');

            foreach($allLegendItems as $key => $value) {
                $this->setVar(array(
                    'LEGEND_ITEM_LETTER' => $key,
                    'LEGEND_ITEM_TEXT' => $value['text'],
                ));

                $this->parse('legend_item_block', 'legend_item', true);
            }

            $this->parse('has_legend_block', 'has_legend');
        }
        else {
            $this->setVar('has_legend_block', '');
        }
    }
}
*/


if (is_array($sectionValues) && count($sectionValues)) {

    // manual array flip
    if(isset($allLegendItems)) {
        foreach($allLegendItems as $key => $item) {
            $legendItems[$item['text']] = $key;
        }
    }

	foreach($sectionValues as $currentSection) {
		$questionList = array();

		/* Get a list of questions going
		 */
		if (is_array($currentSection['questions']) && count($currentSection['questions'])) {
			foreach($currentSection['questions'] as $currentQuestion) {
				$questionList = $currentQuestion['question'];
			}
		}

        $answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);

        $answerHandler->graphResults($this, $currentQuestion);

		/* A list of attempts
		 */
		if (count($currentQuestion['attempts'])) {
			foreach($currentQuestion['attempts'] as $currentAttempt) {
				$start = new Alaress_Date_Format($currentAttempt['start'], 'y-m-d h:i:s');
				$finish = new Alaress_Date_Format($currentAttempt['finish'], 'y-m-d h:i:s');

				$this->setVar(array(
					'RESPONDENT_NAME' => $currentAttempt['name'],
					'RESPONDENT_RESPONSE' =>  $currentAttempt['responses'],
					'RESPONDENT_START_DATETIME' => $start->getShort(),
					'RESPONDENT_FINISH_DATETIME' => $finish->getShort(),
				));

				$this->parse('respondent_block', 'respondent', true);
			}
		}
	}
}

$this->setVar(array(
	'SURVEY_NAME' => $assess['title'],
	'SURVEY_ID' => $assess['assess_id'],
	'QUESTION_QUESTION' => $questionList
));


/**
 * Custom comparison function as used on php.net
 *
 * @param  $a
 * @param  $b
 * @return int
 */
function cmp($a, $b) {
    if ($a == $b) {
        return 0;
    }
    return ($a < $b) ? -1 : 1;
}
