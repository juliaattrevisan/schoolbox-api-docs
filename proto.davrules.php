<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
require_once __DIR__ . '/include/Bootstrap.php';

// inputs:

// (default)
// role
// campus
// year


// outputs:

// baseUri
// userName
// password
// paths


use Alaress\Rules\Condition;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Schoolbox\WebDav\Rules\Ruleset;
use Schoolbox\WebDav\Rules\UserInCondition;


/* OAKBURN */
$set = new Ruleset('http://davusers.soc.tas.edu.au/', 'soc/{:username}');

$set->newRule(array(
    // IF :input.roletype IN ('staff')
    new UserInCondition('roletype', array(Role::STAFF))
    // THEN
), array('paths' => array(
    '{:base_uri}Academic/',
    '{:base_uri}BIT/',
    '{:base_uri}CommunityRelations/',
    '{:base_uri}DramaAndDance/',
    '{:base_uri}EdOutdoors/',
    '{:base_uri}English/',
    '{:base_uri}HPE/',
    '{:base_uri}Humanities/',
    '{:base_uri}LOTE/',
    '{:base_uri}Maths/',
    '{:base_uri}Music/',
    '{:base_uri}PhotoStore/',
    '{:base_uri}Science/',
    '{:base_uri}Technology/',
    '{:base_uri}VisualArts/',
    '{:base_uri}RoundSquare/',
    '{:base_uri}AdminDocs/'
)));

$set->newRule(array(
    // IF :input.roletype IN ('staff')
    new UserInCondition('roletype', array(Role::STAFF)),
    // AND :input.defaultCampus IN (1)
    new UserInCondition('defaultCampus', array(1))
    // THEN
),  array('paths' => array('{:base_uri}penquitestaff/{:username}/Documents/')));

$set->newRule(array(
    // IF :input.roletype IN ('staff')
    new UserInCondition('roletype', array(Role::STAFF)),
    // AND :input.defaultCampus NOT IN (1)
    new UserInCondition('defaultCampus', array(1), true)
    // THEN
),  array('paths' => array('{:base_uri}elphinstaff/{:username}/Documents/')));

$set->newRule(array(
    // IF :input.roletype IN ('student')
    new UserInCondition('roletype', array(Role::STUDENT)),
    // AND :input.year IN (6 .. 12)
    new UserInCondition('year', array(6, 7, 8, 9, 10, 11, 12))
),  array('paths' => array('{:base_uri}penquitestudents/Year12in{:yr12_YYYY}/{:username}/Documents/')));

$set->newRule(array(
    // IF :input.roletype IN ('student')
    new UserInCondition('roletype', array(Role::STUDENT)),
    // AND :input.year NOT IN (6 .. 12)
    new UserInCondition('year', array(6, 7, 8, 9, 10, 11, 12), true)
),  array('paths' => array('{:base_uri}elphinstudents/Year12in{:yr12_YYYY}/{:username}/Documents/')));




foreach (RoleMapper::getInstance()->findAll() as $role) {
    /** @var Role $role */
    $userId = UserMapper::getInstance()->findByColumn('group_id', $role->getId())->first()->getId();
    $user   = UserMapper::getInstance()->findById($userId);

    $x = microtime(true);
    $settings = $set->applyTo($user);
    $y = microtime(true);

    printf("[%dus] %-26s ? %s\n", ($y - $x) * 1000000, sprintf("%s (%s)", $role->getName(), $user->getUsername()), print_r($settings, true));
}
