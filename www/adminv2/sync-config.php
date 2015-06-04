<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


use Alaress\Rules\Condition;
use Alaress\Rules\Lang\Map;
use Schoolbox\Sync\Rules\ExternalUserCondition;
use Schoolbox\Sync\Rules\UserRuleset;
use Schoolbox\User;
use Schoolbox\UserMapper;


if (empty($this)) {
    require_once __DIR__ . '/../../include/Bootstrap.php';
    SitePage::localRedir('/adminv2/sync-config');
    exit;
}

if (!$_SESSION['user']->isSuperUser()) {
    error(19);
    exit;
}

/* DATA SETUP */

/* Mirroring the Schoolbox Sync */
$studentSet = new UserRuleset(UserMapper::getInstance(), 'Student');

$studentSet->newRule([
    // IF :input.StudentBoardingHouse NOT BLANK
    new ExternalUserCondition('StudentBoarder', null, true)
], [
    'residentialHouse' => 1
]);

$studentSet->newRule([
    // ALWAYS
], [
    'username'         => '{:NetworkLogin}',
    'title'            => null,
    'firstname'        => '{:StudentPreferred}',
    'lastname'         => '{:StudentSurname}',
    'altEmail'         => '{:StudentEmail}',
    'localEmail'       => true,
    'eportfolioEnable' => true,
    'year'             => '{:StudentYearLevel}',
    'externalId'       => '{:StudentID}',
    'superuser'        => false,
    'enabled'          => true,
    'password'         => 'password',
    'defaultCampus'    => new Map('{:StudentCampus}', [
        'S' => 1,
        'J' => 2,
    ]),
    'schoolHouse'      => new Map('{:StudentHouse}', [
        'Da' => 1,
        'Fo' => 2,
        'Oa' => 3,
        'Th' => 4,
    ]),
]);

$studentSet->newRule([
    // IF :input.StudentYearLevel IN (0, 1, 2, 3, 4, 5, 6)
    new ExternalUserCondition('StudentYearLevel', [0,1,2,3,4,5,6])
], [
    'role' => 5
]);

$studentSet->newRule([
    // IF :input.StudentYearLevel IN (7, 8, 9, 10, 11, 12)
    new ExternalUserCondition('StudentYearLevel', [7,8,9,10,11,12])
],[
    'role' => 3
]);

$studentSet->newRule([
    // IF :input.NetworkLogin IS BLANK
    new ExternalUserCondition('NetworkLogin', null)
], [
    'username' => '{:StudentPreferred|lc|first}{:StudentSurname|alpha|lc}'
]);

$euh = new External_User();

// students
$studentUserAccountInfo = $euh->getStudentUserAccountInfo();
$studentData = [];

// collate the student data, because there be lotsa dupes
foreach ($studentUserAccountInfo as $datum) {
    $studentData[$datum['StudentID']] = $datum;
}


/** @var SitePage $this */

if ($this->arg[0] == 'example' && $this->arg[1] == 'rule') {

    if (!isset($this->arg[2])) {
        error(2);
    }

    $input  = $studentData[array_rand($studentData)];

    $result = $studentSet->applyPartial([ $this->arg[2] ], $input);

    new Alaress_Probe($input, $result);

    exit;
}


$x = microtime(true);
foreach ($studentData as $externalStudent) {

    if (!$externalStudent['StudentID']) {
        continue;
    }

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

    $result = $studentSet->applyTo($externalStudent, $internalStudent);

    $output = [ $externalStudent, $result ];
}

//new Alaress_Probe($output[0], $output[1]);
new Alaress_Probe(count($studentData), (microtime(true) - $x));


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
