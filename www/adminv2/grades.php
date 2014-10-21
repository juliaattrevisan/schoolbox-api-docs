<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

$group = new Group();

if (!$group->isSuperuser()) {
    error(19);
}

/** @var SitePage $this */
$this->setFile(TEMPLATE_content, 'adminv2/grades.html');
$this->setTitle('Admin - Grades');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'grades');

$this->setBlock(TEMPLATE_content, 'submission_grade_group', 'submission_grade_group_block');
$this->setBlock('submission_grade_group', 'submission_grade', 'submission_grade_block');
