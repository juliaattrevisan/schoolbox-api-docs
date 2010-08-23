<?php
require_once '../library/eportfolio.php';
require_once '../library/eportfolio-private-notes.php';

$eportfolioHandler = new ePortfolio($this->arg[0]);
$eportfolioPrivateNotesHandler = new ePortfolioPrivateNotes($this->arg[0]);

$this->setFile(TEMPLATE_content, 'eportfolio_privateNotes/modify.html');

if (!$eportfolioHandler->canEditPrivateNotes($this->arg[0])) {
    error(110);
}

$privateNotes = $eportfolioPrivateNotesHandler->getByID($this->arg[0]);

$this->setVar(array(
    'ID' => $this->arg[0],
    'PRIVATE_NOTES' => $privateNotes['privateNotes']
));

?>