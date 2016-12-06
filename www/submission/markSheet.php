<?php

/*
 *
 * redirect to Redirection to 16.5 url
 *
 */

if (isset($this->arg[0]) && is_numeric($this->arg[0])){
    $url = '/learning/markbook/';
    $url .= $this->arg[0]; //folder
    if (isset($this->arg[1])) {
        $url .= '/term/'.$this->arg[1]; //term
//        if ($this->arg[2] == 'export') {
//            $url .= '/'.$this->$arg[2]; //export, not ready yet
//        }
    }
    SitePage::localRedir($url, null, 301);
}

SitePage::pageNotFound();

/*
*Old code
*/
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Marksheet;

if (!isset($this)) {
    require_once dirname(__FILE__) . '/../../library/Bootstrap.php';
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
    $currentTerm  = $termMapper->findForToday();
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
    'category' => \Schoolbox\Resource\Folder\Mapper::getInstance(),
    'sb' => SubmissionBoxMapper::getInstance(),
    'oa' => QuizMapper::getInstance(),
    'error' => DueWork_ErrorMapper::getInstance(),
), new \Schoolbox\Config\Loader());


$m->generateData($currentTerm);
if ($performExport) {
    $m->renderCsv();
    exit;
} else {
    $m->renderSitepage($this);
}
