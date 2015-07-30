<?php
/**
 * Section creation.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */
use Schoolbox\Breadcrumbs;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

$assessHandler = new Survey_Main();
$groupHandler = new Group();

if (!empty($_POST)) {
    $assess = $assessHandler->getByID(Scrub::text($_POST['assess_id']));

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
        error(130);
    }

    if ($assessHandler->isDeleted(Scrub::text($_POST['assess_id']))) {
        error(112);
    }

    /* Create new Section
     */
    $sectionHandler = new Survey_Section();
    $section_id = $sectionHandler->create($_POST);

    /* Redirect to question creation for this section
     */
    SitePage::localRedir('/survey/question/create/' . $section_id);
} else {
    if (empty($this->arg[0])) {
        error(51);
    }

    if ($assessHandler->isDeleted($this->arg[0])) {
        error(112);
    }

    /* Find out whether the test exists to which you are attempting to add a
     * new Section
     */
    $assess = $assessHandler->getByID($this->arg[0]);

    if (empty($assess)) {
        error(112);
    }

    if (!$assessHandler->isOwner($assess['assess_id']) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
        error(130);
    }

    try {
        $folderMapper = new FolderMapper();
        $folder = $folderMapper->findById($assess['category_id']);
    }
    catch (\Exception $e) {
        error(117);
    }

    $assessStartDate  = new Alaress_Date_Format(strtotime($assess['start']), 'U');
    $assessFinishDate = new Alaress_Date_Format(strtotime($assess['finish']), 'U');

    /* Render create page
     */
    $this->setFile(TEMPLATE_content, 'survey/section/create.html');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setTitle('Create Section');

    $sectionHandler = new Survey_Section();

    $this->setVar(array(
        'ASSESS_ASSESS_ID'     => $this->arg[0],
        'ASSESS_KIND_LC'       => 'survey',
        'ASSESS_KIND'          => 'Survey',
        'ASSESS_TITLE'         => Scrub::text($assess['title']),
        'ASSESS_START_DATE'    => $assessStartDate->getLocalShort(),
        'ASSESS_FINISH_DATE'   => $assessFinishDate->getLocalShort(),
        'ASSESS_CATEGORY_ID'   => $folder->getId(),
        'ASSESS_CATEGORY_NAME' => $folder->getName(),
        'SECTION_ID'           => '',
        'SEQUENCE'             => $sectionHandler->getNextSequence($this->arg[0])
    ));

    /* Show this Assess' contents
     */
    $this->setFile('assessContentsFragment', 'assess/contents.html');

    $assessHandler->renderAssessContents($assess['assess_id'], $this);

    $this->parse('allQuestions_block', 'assessContentsFragment', true);

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
            'href' => "/assess/summary/{$assess['assess_id']}",
            'class' => '',
        ],
        [
            'text' => 'New Section',
            'href' => "",
            'class' => 'active',
        ]
    ]);
}
