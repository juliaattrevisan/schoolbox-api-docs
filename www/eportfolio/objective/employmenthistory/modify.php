<?php
$skill = new Eportfolio_Objective_EmploymentHistory($this, $this->arg[1]);
$skill->setUserId($this->arg[0]);
$skill->renderModify();

