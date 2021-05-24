<?php

/**
 * Survey export. Uses the handlers to export their question types into a
 * Moodle format.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

$surveyHandler = new Survey_Main();
$groupHandler = new Group();
if (!is_numeric($this->arg[0])) {
    error(112);
}

if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$survey = $surveyHandler->getByID($this->arg[0]);
try {
    $folderMapper = new FolderMapper();
    $folder = $folderMapper->findById($survey['category_id']);
} catch (\Exception $e) {
    error(13);
}

if ($surveyHandler->isDeleted($this->arg[0])) {
    error(112);
}

if (!$surveyHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($survey['category_id']) < UserAccess::ACCESS_WRITE)) {
    error(125);
}

$surveyExporter = new Survey_Export_Graph($survey, new Survey_Factory(), new Survey_Section(), new Survey_Attempt(), new Survey_Question());
$sectionValues = $surveyExporter->getSectionValues();
/* Data collected, now we can show it.
 *
 * For each section, for each question, summarise all attempts' results
 */
$this->setFile(TEMPLATE_content, 'survey/results.html');
$this->setBlock(TEMPLATE_content, 'section', 'section_block');
$this->setBlock('section', 'question', 'question_block');
$this->setBlock('question', 'graph', 'graph_block');
$this->setVar(array(
    'SURVEY_NAME' => Scrub::text($survey['title']),
    'ASSESS_ID' => $survey['assess_id'],
    'CID' => $survey['category_id'],
));
$factory = new Survey_Factory();
if (is_array($sectionValues) && count($sectionValues)) {
    foreach ($sectionValues as $currentSection) {
        $i = 1;
        if (is_array($currentSection['questions']) && count($currentSection['questions'])) {
            foreach ($currentSection['questions'] as $currentQuestion) {
                /* Question data
                 */
                $this->setVar(array(
                    'ASSESS_ID' => $survey['assess_id'],
                    'CID' => $survey['category_id'],
                    'QUESTION_QUESTION' => Scrub::html($currentQuestion['question']),
                    'QUESTION_QUESTION_ID' => $currentQuestion['question_id'],
                    'QUESTION_INDEX' => $i++,
                ));
                /* Answer and Response data
                         */
                        $answerHandler = $factory->createAnswerModel($currentQuestion['type_id']);
                $answerHandler->graphResults($this, $currentQuestion);
                $this->parse('question_block', 'question', true);
                /* Clear children after parsing out the parent
                         */
                        $this->setVar('series_block', '');
            }
        }

        $this->setVar(array(
            'SECTION_NAME' => Scrub::text($currentSection['section'])
        ));
        $this->parse('section_block', 'section', true);
    /* Clear children after parsing out the parent
             */
            $this->setVar('question_block', '');
    }
} else {
    $this->setVar('section_block', '');
}

/* Render out breadcrumbs
 */
$this->setBlock(TEMPLATE_content, 'breadcrumb', 'breadcrumb_block');
$allBreadcrumbs = $folderMapper->breadcrumb($folder, true);
$i = 1;
foreach ($allBreadcrumbs as $currentBreadcrumb) {
    if ($i == $allBreadcrumbs->count()) {
        $active = 'active';
    } else {
        $active = '';
    }

    $this->setVar(array(
        'BREADCRUMB_FOLDER_ID' => $currentBreadcrumb->getId(),
        'BREADCRUMB_FOLDER_NAME' => $currentBreadcrumb->getName(),
        'BREADCRUMB_ACTIVE' => $active,
    ));
    $this->parse('breadcrumb_block', 'breadcrumb', true);
    $i++;
}
