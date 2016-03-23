<?php

use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Marksheet;

if (!isset($this)) {
    require_once dirname(__FILE__) . '/../../include/Bootstrap.php';
    SitePage::localRedir('/submission/markSheet');
}

$groupHandler = new Group();

$performExport = false;
if (is_numeric($this->arg[0])) {
    $categoryID = $this->arg[0];
    if ($this->arg[2] == 'export') {
        $performExport = true;
    }
    if ($groupHandler->getPermission($categoryID) < WRITE_ACCESS) {
        error(19);
    }
} else {
    error(2); // not enough info submitted ...is there a better error?
    exit;
}


if (isset($_GET['year']) || isset($_GET['semester'])) {
    $this->localRedir("/submission/markSheet/{$categoryID}/{$_GET['year']}/{$_GET['semester']}");
}

//$currentYear = date('Y');
//$currentSemester = date('m') > 6 ? 2 : 1;
//
//if (isset($this->arg[1])) {
//    if (!(is_numeric($this->arg[1]) && is_numeric($this->arg[2]))) {
//        error(2); // TODO find a more appropriate error
//    }
//
//    if ($this->arg[2] < 1 || $this->arg[2] > 2) {
//        error(2); // TODO find a more appropriate error
//    }
//
//    $year = (int)$this->arg[1];
//    $semester = (int)$this->arg[2];
//} else {
//    $year = $currentYear;
//    $semester = $currentSemester;
//}

$termMapper = Schoolbox\Assessment\Term\Mapper::getInstance();
$termSlug   = $this->arg[1];
if (isset($termSlug)) {
    try {
        $currentTerm = $termMapper->findOneByColumn('code', $termSlug);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(2); // TODO find a more appropriate error
        exit;
    }
} else {
    $currentTerm  = $termMapper->findMostRecent();
}

$m = new Marksheet($categoryID, $_SESSION['id'], array(
    // inject handler dependencies
    'user' => new User(),
    'group' => $groupHandler,
    'synergy' => new External_Class(),

    'submission' => new Submission(),
    'return' => new SubmissionReturn(),
    'submissionStudent' => new SubmissionStudent(),
    'marktypeFactory' => new Marksheet_Factory(),

    'assessFactory' => new Assess_Factory(),
    'assess' => new Assess_Main(),
    'attempt' => new Assess_Attempt(),
    'section' => new Assess_Section(),
    'response' => new Assess_Response(),

), array(
    // inject mapper dependencies
    'user' => \Schoolbox\UserMapper::getInstance(),
    'term' => $termMapper,
    'category' => \Schoolbox\Resource\CategoryMapper::getInstance(),
    'sb' => SubmissionBoxMapper::getInstance(),
    'oa' => OnlineAssessmentMapper::getInstance(),
    'error' => DueWork_ErrorMapper::getInstance(),
), new \Schoolbox\Config\Loader());


$m->generateData($currentTerm);
if ($performExport) {
    $m->renderCsv();
    exit;
} else {
    $m->renderSitepage($this);
}
