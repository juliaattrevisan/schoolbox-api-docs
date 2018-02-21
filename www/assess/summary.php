<?php
/**
 * A Summary page for assessment creation methods to return to.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\Learning\Assessment\Redirect;

$assessHandler = new Assess_Main();
$sectionHandler = new Assess_Section();
$attemptHandler = new Assess_Attempt();
$groupHandler = new Group();

$assess = $assessHandler->getIntro($this->arg[0]);
$allSummary = $assessHandler->getIntroSummary($this->arg[0]);
$allSections = $sectionHandler->getList($assess['assess_id']);

if (!$assessHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

if ($assessHandler->isDeleted($this->arg[0])) {
    error(112);
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
    error(125);
}
$folder = null;
try {
    $folderMapper = new FolderMapper();
    $folder = $folderMapper->findById($assess['category_id']);
}
catch (\Exception $e) {
    error(117);
}

/** @var \Schoolbox\Learning\Assessment\DataModel\MapperProxy $dataSource */
$dataSource = $this->getDi()->get('assessmentDataSource');

/** @var Alaress\Authorizer\Authorizer $authorizer */
$authorizer = $this->getDi()->get('authorizer');
$params     =  [
    'assessmentId' => $assess['assessment_id'],
    'assessment'   => $dataSource->findById($assess['assessment_id']),
];
if (!$authorizer->isPathAuthorizedForUser('learningQuiz.getQuizSummary', $params, $_SESSION['user'])) {
    error(Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW);
}

if($assess['start']) {
    $assessStartDate = new Alaress_Date_Format(strtotime($assess['start']), 'U');
}
else {
    $assessStartDate = new Alaress_Date_NullDate();
}
if($assess['finish']) {
    $assessFinishDate = new Alaress_Date_Format(strtotime($assess['finish']), 'U');
}
else {
    $assessFinishDate = new Alaress_Date_NullDate();

}

$returnUrl = $_GET[Redirect::RETURN_URL]
    ? "?".Redirect::RETURN_URL."={$_GET[Redirect::RETURN_URL]}"
    : '';

$modifyRouteArgs = [ 'assessmentId' => $assess['assessment_id']];
$modifyUrl = $this->getDi()->get('router')->generate('learningAssessment.getUpdateAssessmentForm', $modifyRouteArgs);

$this->setVar(array(
    'RETURN_URL'           => $returnUrl ? : Redirect::queryString("/homepage/{$folder->getId()}"),
    'RETURN_URL_DECODED'   => $returnUrl == '' ? "/homepage/{$folder->getId()}" : Redirect::decode($_GET[Redirect::RETURN_URL]),
    'ASSESS_ASSESS_ID'     => $this->arg[0],
    'ASSESS_KIND_LC'       => 'assess',
    'ASSESS_KIND'          => 'Quiz',
    'ASSESS_TITLE'         => Scrub::text($assess['title']),
    'ASSESS_DESCRIPTION'   => Scrub::html($assess['description']),
    'ASSESS_START'         => $assessStartDate->getShort(),
    'ASSESS_FINISH'        => $assessFinishDate->getShort(),
    'FIRST_SECTION'        => $allSections[0]['section_id'],
    'ASSESS_CATEGORY_ID'   => $folder->getId(),
    'ASSESS_CATEGORY_NAME' => Scrub::text($folder->getName()),
    'ASSESSMENT_ID'        => $assess['assessment_id'],
    'MODIFY_URL'           => $modifyUrl,
    'RETURN_URL_MODIFY'    => $returnUrl ?: '?'.Redirect::RETURN_URL.'='.Redirect::encode('/assess/summary/'.$assess['assess_id']),
));


$this->setFile(TEMPLATE_content, 'assess/summary.html');
$this->setFile('assessContentsFragment', 'assess/contents.html');
$this->setBlock('assessContentsFragment', 'has_section_description', 'has_section_description_block');
$this->setBlock(TEMPLATE_content, 'has_description', 'has_description_block');
$this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
$this->setBlock(TEMPLATE_content, 'section', 'section_block');
$this->setBlock(TEMPLATE_content, 'delete_quiz', 'delete_quiz_block');
$this->setBlock(TEMPLATE_content, 'delete_survey', 'delete_survey_block');
$this->setTitle('Assessment: ' . Scrub::text($assess['title']));

$assess['description'] = trim($assess['description']);

if (empty($assess['description'])) {
    $this->setVar('has_description_block', '');
} else {
    $this->parse('has_description_block', 'has_description', true);
}

$assessHandler->renderAssessContents($assess['assess_id'], $this);
$this->parse('allQuestions_block', 'assessContentsFragment', true);

$this->setVar(array(
    'ASSESS_DURATION' => $assess['duration'] == Assess_Main::UNLIMITED_DURATION ? 'Unlimited' : $assessHandler->sec2hms($assess['duration']),
));

$this->parse('delete_quiz_block', 'delete_quiz');
$this->setVar('delete_survey_block', '');

/* Render out breadcrumbs
 */
$trail = new \Schoolbox\Breadcrumbs();

// get a crunchy biscuit base
$crumbs = $trail->getFolderCrumbs($folder, $_GET[Redirect::RETURN_URL]);

// sprinkle some crumbs on top
$crumbs = array_merge($crumbs, [
    [
        'text' => $assess['title'],
        'href' => "/assess/summary/{$assess['assess_id']}{$returnUrl}",
        'class' => 'active',
    ]
]);

// bake in a moderate oven for 40 minutes
$trail->renderIntoSitePage($this, 'BREADCRUMB', $crumbs);
