<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio-private-notes.php';

$privateNotesHandler = new ePortfolioPrivateNotes;

$privateNotes = $privateNotesHandler->getByID($userID);

$this->setBlock(TEMPLATE_content, 'privateNotes', 'privateNotes_block');

//$user = $userHandler->getByID($userID);

$profile = $userHandler->getProfile($userID);

$this->setVar(array(
    'PRIVATE_NOTES' => $privateNotes['privateNotes'],
    'HOUSE' => $profile['house'],
    'CAMPUS' => $profile['campus'],
    'YEAR_LEVEL' => $profile['year'],
));

if ($eportfolioHandler->canEditPrivateNotes($userID)) {
    $this->parse('privateNotes_block', 'privateNotes');
}
else {
    $this->setVar('privateNotes_block', '');
}
?>