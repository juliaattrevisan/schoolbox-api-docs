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
use Schoolbox\Breadcrumbs;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

$assessHandler   = new Survey_Main();
$factory         = new Survey_Factory();
$questionHandler = new Survey_Question();
$groupHandler    = new Group();

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

if (!$assessHandler->isOwner($assess['assess_id'])
    && ($groupHandler->getPermission() < WRITE_ACCESS)
) {
    error(125);
}

try {
    $folderMapper = new FolderMapper();
    $folder = $folderMapper->findById($assess['category_id']);
}
catch (\Exception $e) {
    error(13);
}

$surveyExporter = new Survey_Export_Graph(
    $assess,
    $factory,
    new Survey_Section(),
    new Survey_Attempt(),
    $questionHandler
);
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
$this->setBlock(TEMPLATE_content, 'respondent_heading', 'respondent_heading_block');
$this->setBlock('respondent', 'respondent_name', 'respondent_name_block');

/* If not using Anonymous Result, set respondent details
 */
if ($assess['anonymous_result']) {
    $this->setVar('respondent_heading_block', '');
} else {
    $this->parse('respondent_heading_block', 'respondent_heading');
}

$this->setVar('respondent_block', '');

if (is_array($sectionValues) && count($sectionValues)) {
    // manual array flip
    if (isset($allLegendItems)) {
        foreach ($allLegendItems as $key => $item) {
            $legendItems[$item['text']] = $key;
        }
    }

    foreach ($sectionValues as $currentSection) {
        $questionList = array();

        /* Get a list of questions going
           */
        if (is_array($currentSection['questions']) && count($currentSection['questions'])) {
            foreach ($currentSection['questions'] as $currentQuestion) {
                $questionList = Scrub::html($currentQuestion['question']);
            }
        }

        $answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);

        $answerHandler->graphResults($this, $currentQuestion);

        /* A list of attempts
           */
        if (count($currentQuestion['attempts'])) {
            foreach ($currentQuestion['attempts'] as $currentAttempt) {
                $start  = new Alaress_Date_Format($currentAttempt['start'], 'Y-m-d H:i:s');
                $finish = new Alaress_Date_Format($currentAttempt['finish'], 'Y-m-d H:i:s');

                $this->setVar(
                    array(
                        'RESPONDENT_NAME'            => Scrub::text($currentAttempt['name']),
                        'RESPONDENT_RESPONSE'        => $currentAttempt['responses'],
                        'RESPONDENT_START_DATETIME'  => $start->getShort(),
                        'RESPONDENT_FINISH_DATETIME' => $finish->getShort(),
                    )
                );

                /* If not using Anonymous Result, set respondent details
                 */
                if ($assess['anonymous_result']) {
                    $this->setVar('respondent_name_block', '');
                } else {
                    $this->parse('respondent_name_block', 'respondent_name', true);
                }

                $this->parse('respondent_block', 'respondent', true);

                /* Clear children after parsing out the parent
                 */
                $this->setVar('respondent_name_block', '');
            }
        }
    }
}

$this->setVar(
    array(
        'SURVEY_NAME'       => Scrub::text($assess['title']),
        'SURVEY_ID'         => $assess['assess_id'],
        'QUESTION_QUESTION' => $questionList
    )
);

$trail = new Breadcrumbs();
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text'  => $folder->getName(),
        'href'  => '/homepage/' . $folder->getId(),
        'class' => ''
    ],
    [
        'text'  => Scrub::text($assess['title']),
        'href'  => '/survey/summary/' . $assess['assess_id'],
        'class' => ''
    ],
    [
        'text'  => 'Results',
        'href'  => '#',
        'class' => 'active'
    ],
]);


/**
 * Custom comparison function as used on php.net
 *
 * @param  $a
 * @param  $b
 *
 * @return int
 */
function cmp($a, $b)
{
    if ($a == $b) {
        return 0;
    }
    return ($a < $b) ? -1 : 1;
}

