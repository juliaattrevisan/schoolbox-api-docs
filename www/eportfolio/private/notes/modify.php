<?php
$privateNotes = new Eportfolio_Private_Notes($this, $this->arg[1]);
$privateNotes->setUserId($this->arg[0]);
$privateNotes->renderModify();

