<?php
/**
 * A Summary page for assessment creation methods to return to.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
use Schoolbox\Breadcrumbs;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

$assessHandler = new Survey_Main();
$sectionHandler = new Survey_Section();
$attemptHandler = new Survey_Attempt();
$groupHandler = new Group();

$assess = $assessHandler->getIntro($this->arg[0]);
$allSummary = $assessHandler->getIntroSummary($this->arg[0]);
$allSections = $sectionHandler->getList($assess['assess_id']);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
    error(112);
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
    error(125);
}

try {
    $folderMapper = new FolderMapper();
    $folder = $folderMapper->findById($assess['category_id']);
}
catch (\Exception $e) {
    error(13);
}

$assessStartDate = new Alaress_Date_Format(strtotime($assess['start']), 'U');
$assessFinishDate = new Alaress_Date_Format(strtotime($assess['finish']), 'U');

$this->setVar(array(
    'ASSESS_ASSESS_ID'     => $this->arg[0],
    'ASSESS_KIND_LC'       => 'survey',
    'ASSESS_KIND'          => 'Survey',
    'ASSESS_TITLE'         => Scrub::text($assess['title']),
    'ASSESS_DESCRIPTION'   => Scrub::html($assess['description']),
    'ASSESS_START'         => $assessStartDate->getShort(),
    'ASSESS_FINISH'        => $assessFinishDate->getShort(),
    'FIRST_SECTION'        => $allSections[0]['section_id'],
    'ASSESS_CATEGORY_ID'   => $folder->getId(),
    'ASSESS_CATEGORY_NAME' => $folder->getName(),
));

$this->setFile(TEMPLATE_content, 'assess/summary.html');
$this->setFile('assessContentsFragment', 'assess/contents.html');
$this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
$this->setBlock(TEMPLATE_content, 'section', 'section_block');
$this->setTitle('Assessment: ' . $assess['title']);

$assessHandler->renderAssessContents($assess['assess_id'], $this);
$this->parse('allQuestions_block', 'assessContentsFragment', true);

$this->setVar(array(
    'ASSESS_DURATION' => $assess['duration'] == Survey_Main::UNLIMITED_DURATION ? 'Unlimited' : $assessHandler->sec2hms($assess['duration']),
));

/* Section list and question count
 */
if (is_array($allSummary) && count($allSummary)) {
    foreach ($allSummary as $currentSummary) {
        $this->setVar(array(
            'SECTION_SECTION_ID' => $currentSummary['section_id'],
            'SECTION_SEQUENCE' => $currentSummary['sequence'],
            'SECTION_TITLE' => Scrub::text($currentSummary['title']),
            'SECTION_NUMQUESTIONS' => $currentSummary['numQuestions'],
        ));

        $this->parse('section_block', 'section', 'true');
    }
} else {
    $this->setVar('section_block', '');
}

/* Render out breadcrumbs
 */
$trail = new Breadcrumbs(Schoolbox\Resource\Folder\Mapper::getInstance());
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text' => $folder->getName(),
        'href' => "/homepage/{$folder->getId()}",
        'class' => '',
    ],
    [
        'text' => $assess['title'],
        'href' => "/survey/summary/{$assess['assess_id']}",
        'class' => 'active',
    ]
]);