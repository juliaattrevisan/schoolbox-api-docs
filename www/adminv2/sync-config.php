<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


use Schoolbox\External\Normalise\User\Adapter;
use Schoolbox\Role;
use Schoolbox\Sync;
use Schoolbox\Sync\Rules\Condition;
use Schoolbox\User;
use Schoolbox\UserMapper;


if (empty($this)) {
    require_once __DIR__ . '/../../include/Bootstrap.php';
    SitePage::localRedir('/adminv2/sync-config');
    exit;
}

/** @var SitePage $this */

if (!$_SESSION['user']->isSuperUser()) {
    error(19);
    exit;
}

$stratFactory = new Sync\Ruleset\Factory();

$studentSet = $stratFactory->build(Role::STUDENT);


function testData(Sync\Rules\Ruleset $studentSet, SitePage $page)
{

    $euh = new External_User();

// students
    $studentUserAccountInfo = (array)$euh->getStudentUserAccountInfo();
    /** @var Schoolbox\External\Normalise\User\Student[] $studentData */
    $studentData            = [];

// collate the student data, because there be lotsa dupes
    $adapter = new Adapter($euh);

    foreach ($studentUserAccountInfo as $datum) {
        $extId = $datum['StudentID'];
        if (empty($studentData[$extId])) {
            $studentData[$extId] = $adapter->getStudent($extId);
        }
    }


    if ($page->arg[0] == 'example' && $page->arg[1] == 'rule') {

        if (!isset($page->arg[2])) {
            error(2);
        }

        $input = $studentData[array_rand($studentData)];

        $result = $studentSet->applyPartial([$page->arg[2]], $input);

        new Alaress_Probe($input, $result);

        exit;
    }


    $x = microtime(true);
    foreach ($studentData as $externalStudent) {

//        if (!$externalStudent['StudentID']) {
//            continue;
//        }

//    try {
        // has this student already been loaded into Schoolbox?
//        $internalStudent = UserMapper::getInstance()->findOneByColumnList([
//            'synergy_id' => $externalStudent['StudentID'],
//        ]);
//    } catch (Schoolbox\Mapper\Exception $e) {
        // no? that's ok
        $internalStudent = null;

//        continue; // for testing, only interested in existing users
//    }

        $results[$externalStudent->getId()] = $studentSet->applyTo($externalStudent, $internalStudent);
    }

    $duration = microtime(true) - $x;

    $a = array('s', 'ms', '&mu;s');

    for ($i = 0; $duration < 1 && $i < count($a); $i++) {
        $duration *= 1000;
    }

    $duration = round($duration, 2) . $a[$i];

//new Alaress_Probe($output[0], $output[1]);
    printf("<pre>%d users processed in %s</pre>", count($results), $duration);
    var_dump(UserMapper::getInstance()->dehydrate($results[135]));
}


if ($_GET['test'] || count($this->arg)) {
    testData($studentSet, $this);
}



$this->setFile(TEMPLATE_content, 'adminv2/sync-config.html');

$this->setBlock(TEMPLATE_content, 'rule', 'rule_block');
$this->setBlock('rule', 'condition', 'condition_block');
$this->setBlock('rule', 'effect',    'effect_block');
$this->setBlock(TEMPLATE_content, 'token', 'token_block');
$this->setBlock(TEMPLATE_content, 'filter', 'filter_block');

$nav = new AdminV2_Nav();
$nav->parse($this, 'synchronise');
$this->setTitle('Admin - External Database Sync Configuration');

$this->setVar('rule_block', '');
foreach ($studentSet->getRules() as $precedence => $rule) {

    $this->setVar(array(
        'SYNC_RULE_N'       => $precedence,
        'SYNC_RULE_SUMMARY' => $rule->getHtmlSummary()
    ));

    $this->parse('rule_block', 'rule', true);
}

foreach ($studentSet->getTokens() as $tok) {
    $this->setVar(array(
        'TOKEN'       => '{:'.$tok->getToken().'}',
        'TOKEN_DESCR' => $tok->getDescription()
    ));
    $this->parse('token_block', 'token', true);
}

foreach ($studentSet->getFilters() as $filter) {
    $this->setVar(array(
        'FILTER'       => '|'.$filter->getFilter(),
        'FILTER_DESCR' => $filter->getDescription()
    ));
    $this->parse('filter_block', 'filter', true);
}
