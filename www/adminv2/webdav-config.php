<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


use Alaress\Rules\Condition;
use Schoolbox\Role;
use Schoolbox\WebDav\Rules\Ruleset;
use Schoolbox\WebDav\Rules\UserCondition;


if (empty($this)) {
    require_once __DIR__ . '/../../include/Bootstrap.php';
    SitePage::localRedir('/adminv2/webdav-config');
    exit;
}

if (!$_SESSION['user']->isSuperUser()) {
    error(19);
    exit;
}

/* DATA SETUP */

/* OAKBURN */
$set = new Ruleset('http://davusers.soc.tas.edu.au/', 'soc/{:username}');

$set->newRule(array(
    // IF :input.roletype IN ('staff')
    new UserCondition('role.type', array(Role::STAFF))
    // THEN
), array('paths' => array(
    '{:uri}Academic/',
    '{:uri}BIT/',
    '{:uri}CommunityRelations/',
    '{:uri}DramaAndDance/',
    '{:uri}EdOutdoors/',
    '{:uri}English/',
    '{:uri}HPE/',
    '{:uri}Humanities/',
    '{:uri}LOTE/',
    '{:uri}Maths/',
    '{:uri}Music/',
    '{:uri}PhotoStore/',
    '{:uri}Science/',
    '{:uri}Technology/',
    '{:uri}VisualArts/',
    '{:uri}RoundSquare/',
    '{:uri}AdminDocs/'
)));

$set->newRule(array(
    // IF :input.role.type = 'staff'
    new UserCondition('role.type', Role::STAFF),
    // AND :input.defaultCampus.id = 1
    new UserCondition('defaultCampus.id', 1)
    // THEN
),  array('paths' => array('{:uri}penquitestaff/{:username}/Documents/')));

$set->newRule(array(
    // IF :input.role.type = 'staff'
    new UserCondition('role.type', Role::STAFF),
    // AND :input.defaultCampus.id NOT = 1
    new UserCondition('defaultCampus.id', 1, true)
    // THEN
),  array('paths' => array('{:uri}elphinstaff/{:username}/Documents/')));

$set->newRule(array(
    // IF :input.role.type = 'student'
    new UserCondition('role.type', Role::STUDENT),
    // AND :input.year IN (6 .. 12)
    new UserCondition('year', array(6, 7, 8, 9, 10, 11, 12))
    // THEN
),  array(
        'paths'    => array('{:uri}penquitestudents/Year12in{:yr12_YYYY}/{:username}/Documents/'),
        'username' => 'socs/{:lc_username}'
    )
);

$set->newRule(array(
    // IF :input.role.type = 'student'
    new UserCondition('role.type', array(Role::STUDENT)),
    // AND :input.year NOT IN (6 .. 12)
    new UserCondition('year', array(6, 7, 8, 9, 10, 11, 12), true)
    // THEN
),  array('paths' => array('{:uri}elphinstudents/Year12in{:yr12_YYYY}/{:username}/Documents/')));





/** @var SitePage $this */

$this->setFile(TEMPLATE_content, 'adminv2/webdav-config.html');

$this->setBlock(TEMPLATE_content, 'rule', 'rule_block');
$this->setBlock('rule', 'condition', 'condition_block');
$this->setBlock('rule', 'effect',    'effect_block');
$this->setBlock(TEMPLATE_content, 'token', 'token_block');

$nav = new AdminV2_Nav();
$nav->parse($this, 'setting');
$this->setTitle('Admin - WebDAV Configuration');


$this->setVar('rule_block', '');
foreach ($set->getRules() as $i => $rule) {

    if ($rule->getSpecificity() == 0) {
        $effect = $rule->getEffect();
        $this->setVar(array(
            'WD_DEF_BASE_URI' => $effect['baseUri'],
            'WD_DEF_USERNAME' => $effect['userName'],
            'WD_DEF_PASSWORD' => $effect['password'],
        ));

        continue;
    }

    $this->setVar(array(
        'WD_RULE_N' => $i + 1,
        'WD_RULE_SUMMARY' => $rule->getHtmlSummary()
    ));

    $this->setVar('effect_block', '');
    foreach ($rule->getEffect() as $k => $v) {
        $this->setVar(array(
            'WD_EFFECT_KEY' => ucfirst($k),
            'WD_EFFECT'     => is_array($v) ? implode("\n", $v) : $v,
        ));
        $this->parse('effect_block', 'effect', true);
    }

    $this->setVar('condition_block', '');
    foreach ($rule->getConditions() as $condition) {
        $this->setVar('WD_CONDITION', $condition->getSummary());
        $this->parse('condition_block', 'condition', true);
    }

    $this->parse('rule_block', 'rule', true);
}

foreach ($set->getTokens() as $tok) {
    $this->setVar(array(
        'TOKEN'       => '{:'.$tok->getToken().'}',
        'TOKEN_DESCR' => $tok->getDescription()
    ));
    $this->parse('token_block', 'token', true);
}