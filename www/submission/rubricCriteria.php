<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Criterion;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\UsageMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

// not in SitePage context, redirect to equivalent
if (!isset($this)) {
    require_once __DIR__ . '/../../library/Bootstrap.php';
    SitePage::localRedir('/submission/rubricCriteria');
    die();
}

/** @var SitePage $this */

// retrieve the categoryID or die
$folderId = 0;
if (isset($this->arg[0]) && is_numeric($this->arg[0])) {
    $folderId = $this->arg[0];
} else {
    error(2);
}

$userAccess = new Access();
if (!($_SESSION['user']->isSuperuser()
    || $userAccess->getFolderAccess($_SESSION['user'], $folderId) >= UserAccess::ACCESS_WRITE
)) {
    error(6);
}

/** @var RosettaStone $l10n */
$l10n = $this->getDi()->get('l10n');

$this->setFile(TEMPLATE_content, 'submission/rubricCriteria.html');
$this->setTab('submission');
$this->setBlock(TEMPLATE_content, 'allow_cascade', 'allow_cascade_block');
$this->setBlock(TEMPLATE_content, 'criterion_group', 'criterion_group_block');
$this->setBlock('criterion_group', 'criterion', 'criterion_block');
$this->setBlock('criterion', 'criterion_disabled', 'criterion_disabled_block');
$this->setBlock('criterion', 'criterion_assessment', 'criterion_assessment_block');
$this->setBlock('criterion', 'criterion_enabled', 'criterion_enabled_block');
$this->setBlock('criterion_enabled', 'criterion_in_use', 'criterion_in_use_block');


// get category details
$folder = FolderMapper::getInstance()->findById($folderId);


$this->setVar([
    'CATEGORY_ID'   => $folder->getId(),
    'CATEGORY_NAME' => Scrub::text($folder->getName()),
]);

$criteriaName = $this->getDi()->get('l10n')->translate('rubricGroup', true, true);

$title = 'Manage Rubric ' . $criteriaName . ' for ' . Scrub::text($folder->getName());
$this->setTitle($title);
$this->setVar('PAGE_TITLE', $title);

// get rubric details
$criterionCollection = CriterionMapper::getInstance()->findByColumnList(
    [
        'parent_id'   => $folderId,
        'parent_type' => Criterion::PARENT_TYPE_CATEGORY,
    ],
    ['order' => ['grouping' => 'ASC', 'name' => 'ASC']]
);


// for now, we're not supporting inheritance
$this->setVar('allow_cascade_block', '');

$this->setVar('criterion_group_block', '');
$topID = 0;
$groupCount = 0;
if (count($criterionCollection)) {

    /* Create group structure */
    $criteriaList = [];
    foreach ($criterionCollection as $crit) {
        $criteriaList[$crit->getGrouping()][] = $crit;
    }

    foreach ($criteriaList as $grouping => $crits) {
        $this->setVar([
            'criterion_block'    => '',
            'CRITGROUP_COUNT'    => $groupCount++,
            'CRITGROUP_NAME'     => $grouping,
            'CRITGROUP_DISABLED' => '',
        ]);

        $usageTotal = 0;

        foreach ($crits as $crit) {
            /* @var $crit Criterion */
            $topID = max($topID, $crit->getId());
            $this->setVar([
                'CRITERION_ID'   => $crit->getId(),
                'CRITERION_NAME' => $crit->getName(),
            ]);

            $usage = UsageMapper::getInstance()->findUsage($crit->getId());
            $usageCount = count($usage);
            $usageTotal += $usageCount;
            if ($usageCount > 0) {
                $this->setVar([
                    'CRITERION_USAGE'                  => $usageCount,
                    'CRITERION_ASSESSMENT_COUNT_LABEL' => $l10n->translate('assessment', $usageCount > 1),
                ]);

                if (!($folder->hasCourseSpecification() || $folder->hasUnitSpecification())) {
                    $this->setVar('criterion_enabled_block', '');
                    $this->parse('criterion_disabled_block', 'criterion_disabled');
                } else {
                    $this->setVar('criterion_disabled_block', '');
                    $this->parse('criterion_in_use_block', 'criterion_in_use');
                    $this->parse('criterion_enabled_block', 'criterion_enabled');
                }

                // PHPLib the assessments
                $this->setVar('criterion_assessment_block', '');
                foreach ($usage as $assessment) {
                    $this->setVar(
                        'CRITERION_ASSESSMENT_HREF',
                        $this->routeLookup(
                            'learningAssessment.getUpdateAssessmentForm',
                            [
                                'assessmentId' => $assessment['id'],
                            ]
                        ) . "#rb[{$assessment['submission_box_id']}][criterion][{$crit->getId()}][on]"
                    );
                    $this->setVar('CRITERION_ASSESSMENT_TITLE', $assessment['title']);
                    $this->parse('criterion_assessment_block', 'criterion_assessment', true);
                }
            } else {
                $this->setVar('criterion_assessment_block', '');
                $this->setVar('criterion_disabled_block', '');
                $this->setVar('criterion_in_use_block', '');
                $this->parse('criterion_enabled_block', 'criterion_enabled');
            }

            $this->parse('criterion_block', 'criterion', true);
            if ($usageTotal > 0) {
                $this->setVar('CRITGROUP_DISABLED', 'readonly');
            }
        }
        $this->parse('criterion_group_block', 'criterion_group', true);
    }
}
$this->setVar('MAX_CRITGROUP_ID', $groupCount);
$this->setVar('MAX_CRIT_ID', $topID + 1);

$trail = new \Schoolbox\Breadcrumbs();
$trail->renderIntoSitePage(
    $this,
    'BREADCRUMB',
    [
        [
            'text'  => $folder->getName(),
            'href'  => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text'  => 'Manage ' . $criteriaName,
            'href'  => '',
            'class' => 'active',
        ],
    ]
);
