<?php

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Criterion;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Descriptor;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Folder\Mapper as CategoryMapper;

$this->setFile(TEMPLATE_content, 'submission/createSubmission.html');
$this->setTab('submission');

$this->setBlock(TEMPLATE_content, 'marktype', 'marktype_block');

// Instead of all this block BS I'll create a sub-template
$this->setFile('rubricTpl', 'submission/_submissionRubrics.html');
$this->setBlock('rubricTpl', 'rubric_type_label', 'rubric_type_label_block');
$this->setBlock('rubricTpl', 'criteria_allowed', 'criteria_allowed_block');
$this->setBlock('rubricTpl', 'no_criteria_allowed', 'no_criteria_allowed_block');

$this->setBlock('criteria_allowed', 'no_crit_group', 'no_crit_group_block');
$this->setBlock('criteria_allowed', 'crit_group', 'crit_group_block');
$this->setBlock('crit_group', 'criterion', 'criterion_block');
$this->setBlock('criterion', 'crit_input', 'crit_input_block');

$this->setBlock('marktype', 'resultset', 'resultset_block');
$this->setBlock('resultset', 'result', 'result_block');
$this->setBlock('marktype', 'des_count', 'des_count_block');
$this->setBlock('marktype', 'existing_marks_warning', 'existing_marks_warning_block');
$this->setBlock('marktype', 'gradegroup_explanation', 'gradegroup_explanation_block');
$this->setBlock('gradegroup_explanation', 'gradegroup_explanation_level', 'gradegroup_explanation_level_block');
$this->setBlock(TEMPLATE_content, 'mark_weight', 'mark_weight_block');
$this->setBlock(TEMPLATE_content, 'duework_timespan', 'duework_timespan_block');
$this->setBlock(TEMPLATE_content, 'curriculum_code_input', 'curriculum_code_input_block');

$this->setVar('category_select_block', '');
$this->setVar('existing_marks_warning_block', '');
$this->setVar('COURSE_CRITERIA', '');
$this->setVar('CLASS_CRITERIA', '');

if (Config::get('curriculum_tagging_enabled')) {
    $this->parse('curriculum_code_input_block', 'curriculum_code_input');
} else {
    $this->setVar('curriculum_code_input_block', '');
}

$this->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');


$mapper      = new SubmissionBoxMapper();
$cat_mapper  = new CategoryMapper();
$type_mapper = new SubmissionBox\WorkTypeMapper();
$mark_mapper = new SubmissionBox\MarkTypeMapper();
$groupMapper = new SubmissionBox\GradeLevel\Group\Mapper();
$conf_mapper = new ConfigMapper();

$groupHandler  = new Group;
$rubricHandler = new DueWork_Rubric_Handler();

$zone = new Zone('duework-attachment');
$zone
    ->setMimeFilter('*/*')
    ->setMulti(true)
    ->setModalTitle('Select files')
    ->setAttachPrompt('Attach files')
    ->setAttachmentsLabel('')
    ->setThumbnailSize(64);

//
// Should work types include or not include online assessments?
// They do for pre-existing items, they don't for new items.
$work_types  = $type_mapper->getAllExcept(SubmissionBox\WorkType::TYPE_ONLINE_ASSESSMENT);
$mark_types  = $mark_mapper->getAll();
$gradeGroups = $groupMapper->findAllWithGradeLevels();

$title            = '';
$heading          = '';
$submission       = $mapper->createModel();
$category         = $cat_mapper->createModel();
$is_task          = false;
$unit_id          = null;
$task_instance_id = null;
$has_file_preset  = false;

if ($this->arg[0] == 'modify' && is_numeric($this->arg[1])) {
    // IF PRE-EXISTING
    // Args:
    // 1: submission box ID
    $submission_id = $this->arg[1];
    $title         = 'Modify Due Work';
    $heading       = 'Modify';

    try {
        $submission = $mapper->findById($submission_id);
    } catch (\Exception $e) {
        error(17);
    }

    $category         = $cat_mapper->findById($submission->getCategory()->getId());
    $task_id          = $submission->getTask()->getId();
    $is_task          = (bool)$task_id;
    $task_instance_id = $submission->getTaskInstance()->getId();

    /* Allow deletion of the submission box from modify screen
     */
    $this->parse('delete_button_block', 'delete_button');

    $zone->attachFiles($submission->getAttachments()->getColumnValues('hash'));

} elseif ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
    $this->setVar(
        array(
            'delete_button_block' => ''
        )
    );

    // IF NEW
    // Args:
    // 1: category ID
    // 2: "task", "unit:$unit_id" or an integer (in which case it's a file ID)
    // 3: work type, if provided
    $category_id = $this->arg[1];
    $title       = 'Post Due Work';
    $heading     = 'Post';
    $category    = CategoryMapper::getInstance()->findById($category_id);
    $submission
        ->setCategory($category)
        ->setPublishDate(new Alaress_Date_Format(new DateTime('00:00:00')))
        ->setOpenDate(new Alaress_Date_Format(new DateTime('00:00:00')))
        ->setDueDate(new Alaress_Date_Format(new DateTime('+1 day 23:59:59')))
        ->setLateDate(new Alaress_Date_Format(new DateTime('+2 day 23:59:59')))
        ->setWorkType(new SubmissionBox\WorkType())
        ->setMarkType(new SubmissionBox\MarkType())
        ->setMarkVisibility($conf_mapper->get('submission_access'))
        ->setMarkVisibility(Config::get('submission_access'))
        ->setCurriculumNodes($category->getCurriculumNodes())
    ;
    $gradeGroup = new SubmissionBox\GradeLevel\Group\Model();
    $gradeGroup->setId($conf_mapper->get('default_grade_group'));
    $submission->setGradeGroup($gradeGroup);

    // Is this a task?
    $is_task = ($this->arg[2] === 'task');

    // Is this being created for a unit?
    // If so, pre-fill start and end times
    $is_unit = preg_match('/^unit:(\d+)$/', $this->arg[2], $matches);
    if ($is_unit) {
        $unit_id = $matches[1];

        /** @var \Schoolbox\Assessment\Course\UnitInstance $unit */
        try {
            $unit = UnitInstanceMapper::getInstance()->findByIdAndCategoryId($unit_id, $category_id);
        } catch (Schoolbox\Mapper\Exception $e) {
            error(2);
        }

        // Adjust due date to end of unit day
        $due_date = $unit->getDueDate()->getDateTimeObject();
        $due_date->modify('23:59:59');
        $due_date_object = new Alaress_Date_Format($due_date);
        $submission
            ->setOpenDate($unit->getOpenDate())
            ->setDueDate($due_date_object)
            ->setLateDate($due_date_object);
    }

} else {
    error(2);
}

// Authorisation: user has write access to group.
if ($groupHandler->getPermission($category->getId()) < WRITE_ACCESS) {
    error(19);
}

// Start outputting the submission and its stupid blocks
$this->setTitle($title);
$this->setVar(
    array(
        'existing_file_block'          => '',
        'existing_marks_warning_block' => '',
        'duework_timespan_block'       => '',
        'marktype_block'               => '',
    )
);

$zone->renderIntoSitePage($this, 'ATTACH_ZONE');

// Output submission work_type drop down.
$work_type = $submission->getWorkType()->getId();
$this->dropDown(
    'type_drop',
    $work_types,
    $work_type,
    TEMPLATE_content,
    array(
        'name' => 'TYPE_TEXT',
        'id'   => 'TYPE_VALUE'
    )
);

// Output mark visibility
$widgets = new \Schoolbox\Assessment\Widgets();
$widgets->outputVisibilityDropdown($this, $submission->getMarkVisibility());

// Output marktype drop down
// (If it's an old style free mark, then output nothing)
$mark_type = $submission->getMarkType()->getId();
if ($mark_type == SubmissionBox\MarkType::TYPE_FREE) {
    $this->setVar('marktype_block', '');
} else {
    $this->dropdown(
        'marktype_drop',
        $mark_types,
        $mark_type,
        'marktype',
        array(
            'name' => 'MARKTYPE_TEXT',
            'id'   => 'MARKTYPE_VALUE',
            'slug' => 'MARKTYPE_SLUG'
        )
    );

    // and the group types
    $this->dropdown(
        'gradegroup_drop',
        $gradeGroups,
        $submission->getGradeGroup()->getId(),
        'marktype',
        array(
            'name' => 'GRADEGROUP_TEXT',
            'id'   => 'GRADEGROUP_VALUE',
        )
    );
    // Maybe output the marks for each grade group?
    $this->setVar('gradegroup_explanation_block', '');
    foreach ($gradeGroups as $group) {
        $this->setVar(
            array(
                'gradegroup_explanation_level_block' => '',
                'GRADEGROUP_ID'                      => $group->getId(),
                'GRADEGROUP_NAME'                    => $group->getName(),
            )
        );
        foreach ($group->getGradeLevels() as $level) {
            $this->setVar(
                array(
                    'GRADEGROUP_LEVEL_LOWER_BOUND' => $level->getLowerBound(),
                    'GRADEGROUP_LEVEL_RAW_GRADE'   => $level->getRawGrade(),
                    'GRADEGROUP_LEVEL_CAPTION'     => $level->getCaption(),
                )
            );
            $this->parse('gradegroup_explanation_level_block', 'gradegroup_explanation_level', true);
        }
        $this->parse('gradegroup_explanation_block', 'gradegroup_explanation', true);
    }


    // if the rubric creation fails it will throw an exception, in which case just output the empty sets
    // Default values for criteria
    $criteria    = array();
    $results     = array();
    $resultCount = 2;
    $resultSetID = -1;
    try {
        if ($mark_type == SubmissionBox\MarkType::TYPE_RUBRIC) {
            $rubric      = $rubricHandler->getRubric($submission->getId());
            $resultSetID = $submission->getRubricResultSet();

            if ($rubricHandler->marksAlreadyGiven($submission->getId())) {
                $this->parse('existing_marks_warning_block', 'existing_marks_warning');
            }

            $criteria    = $rubric->getCriteriaIndices();
            $results     = $rubric->getResults();
            $resultCount = $rubric->getResultCount();
        }
    } catch (Exception $e) {
    }
    outputResultSets($this, $resultSetID, $resultCount);
    outputCriteria($this, $submission->getCategory()->getId(), $criteria, $results, $resultCount, false, $is_task);
    if ($is_task) {
        outputCriteria($this, $category->getParent()->getId(), $criteria, $results, $resultCount, true, $is_task);
    }
    $this->parse('marktype_block', 'marktype');
}

// this block exists so that if a config variable needs to be introduced, this can be blanked out
$this->parse('mark_weight_block', 'mark_weight');

// Is this a task? If so, output the timespan block.
if (!$is_task) {
    $this->parse('duework_timespan_block', 'duework_timespan');
}

// Set date values
$publish_date = $submission->getPublishDate();
$open_date    = $submission->getOpenDate();
$due_date     = $submission->getDueDate();
$late_date    = $submission->getLateDate();

$publish_hour = '';
$publish_min  = '';
if (!$publish_date->isAtStartOfDay()) {
    $publish_hour = $publish_date->get12Hour();
    $publish_min  = $publish_date->getMinute(true);
}

if ($open_date->isAtStartOfDay()) {
    $open_hour = '';
    $open_min  = '';
} else {
    $open_hour = $open_date->get12Hour();
    $open_min  = $open_date->getMinute(true);
}

if ($due_date->isAtEndOfDay()) {
    $due_hour = '';
    $due_min  = '';
} else {
    $due_hour = $due_date->get12Hour();
    $due_min  = $due_date->getMinute(true);
}

if ($late_date->isAtEndOfDay()) {
    $late_hour = '';
    $late_min  = '';
} else {
    $late_hour = $late_date->get12Hour();
    $late_min  = $late_date->getMinute(true);
}

$output = array(
    'HEADING'           => $heading,
    'CATEGORY'          => $submission->getCategory()->getId(),
    'ID'                => $submission->getId(),
    'NAME'              => Scrub::text($submission->getName()),
    'DESCRIPTION'       => Scrub::text($submission->getDescription()),
    'ACCEPT_SUBMISSION' => $submission->getAcceptOnline() ? ' checked' : '',
    'MULTI_SUBMISSION'  => $submission->getAcceptMultiple() ? ' checked' : '',
    'TOTAL_MARK'        => $submission->getMaxMark(),
    'SUBMISSION_WEIGHT' => $submission->getWeight(),

    'OPEN_DATE'         => $open_date->getLocalShort(),
    'OPEN_HOUR'         => $open_hour,
    'OPEN_MIN'          => $open_min,
    'OPEN_PM'           => $open_date->isPM() ? 'selected' : '',
    'PUBLISH_DATE'      => $publish_date->getLocalShort(),
    'PUBLISH_HOUR'      => $publish_hour,
    'PUBLISH_MIN'       => $publish_min,
    'PUBLISH_PM'        => $publish_date->isPM() ? 'selected' : '',
    'DUE_DATE'          => $due_date->getLocalShort(),
    'DUE_HOUR'          => $due_hour,
    'DUE_MIN'           => $due_min,
    'DUE_PM'            => $due_date->isPM() ? 'selected' : '',
    'LATE_DATE'         => $late_date->getLocalShort(),
    'LATE_HOUR'         => $late_hour,
    'LATE_MIN'          => $late_min,
    'LATE_PM'           => $late_date->isPM() ? 'selected' : '',
    'FILE_MAX_UPLOAD'   => $conf_mapper->get('file_upload_count'),
    'MAX_FILE_SIZE'     => $conf_mapper->get('max_file_size'),
    'SESSION_NAME'      => session_name(),
    'SESSION_ID'        => session_id(),
    'IS_TASK'           => $is_task ? 1 : 0,
    'UNIT_ID'           => $unit_id,
    'TASK_INSTANCE_ID'  => $task_instance_id,
    'CURRICULUM_CODE_INPUT' => $this->getTwig()->render(
        'sub/curriculumCodeSelector.twig',
        ['codeList' => $submission->getCurriculumNodes()]
    ),
);

$this->setVar($output);

/**
 * @param SitePage  $page
 * @param int       $checkedIndex
 * @param int       $descriptorCount
 */
function outputResultSets(SitePage $page, $checkedIndex = -1, $descriptorCount = 2)
{
    $rubricHandler = new DueWork_Rubric_Handler();
    $rs            = $rubricHandler->getResultSets();

    $page->setVar('resultset_block', '');

    if (count($rs)) {
        foreach ($rs as $i => $set) {
            $page->setVar('result_block', '');
            foreach ($set as $item) {
                $page->setVar(
                    array(
                        'RS_ID'           => $i,
                        'RS_RESULT_LABEL' => $item,
                        'RS_CHECKED'      => $i == $checkedIndex ? 'checked="checked"' : ''
                    )
                );
                $page->parse('result_block', 'result', true);
            }
            $page->parse('resultset_block', 'resultset', true);
        }
    }

    // Output descriptor count drop down from 2-11.
    $desCounts = array();
    for ($i = 2; $i <= 11; ++$i) {
        $desCounts[] = array('value' => $i);
    }
    $page->dropdown(
        'des_count', $desCounts, [ $descriptorCount ], 'marktype', array(
            'value' => 'DES_COUNT_VALUE',
        )
    );

    $page->setVar('RS_0_CHECKED', $checkedIndex == 0 ? 'checked="checked"' : '');
}


/**
 * @param SitePage $page
 * @param int      $catID
 * @param array    $checkedIDs
 * @param array    $descriptors
 * @param int      $descriptorCount
 * @param bool     $is_course
 * @param bool     $is_for_task
 */
function outputCriteria(
    SitePage $page,
    $catID,
    $checkedIDs,
    $descriptors,
    $descriptorCount = 2,
    $is_course = false,
    $is_for_task = false
) {

    // Output this all as part of a single template, and inject into the block.

    // get rubric details
    /** @var \Schoolbox\Collection|Criterion[] $criterionCollection */
    $criterionCollection = CriterionMapper::getInstance()->findByColumnList(
        array(
            'parent_id'   => $catID,
            'parent_type' => Criterion::PARENT_TYPE_CATEGORY,
        ),
        array('order' => array('grouping' => 'ASC', 'name' => 'ASC'))
    );

    $page->setVar(
        array(
            'RUBRIC_CAT_ID'         => $catID,
            'LABEL'                 => ($is_course ? 'Course-level criteria' : 'Unit-level criteria'),
            'MANAGE_CRITERIA_LABEL' => ($is_course ? 'Manage course-level criteria' : 'Manage unit-level criteria'),
        )
    );

    $page->setVar(
        array(
            'rubric_type_label_block'   => '',
            'criteria_allowed_block'    => '',
            'no_criteria_allowed_block' => '',
        )
    );
    if ($is_for_task) {
        $page->parse('rubric_type_label_block', 'rubric_type_label');
    } else {
        $page->setVar('MANAGE_CRITERIA_LABEL', 'Manage available criteria');
    }

    if ($catID) {
        // Display the criteria for this category, if there are any
        $page->setVar(
            array(
                'no_crit_group_block' => '',
                'crit_group_block'    => '',
                'criterion_block'     => ''
            )
        );

        $hasCritGroup = false;
        if ($criterionCollection->count()) {
            $hasCritGroup = true;
            $currentGrouping = $criterionCollection->first()->getGrouping();

            foreach ($criterionCollection as $criterion) {
                if ($criterion->getGrouping() != $currentGrouping) {
                    $page->parse('crit_group_block', 'crit_group', true);
                    $page->setVar('criterion_block', '');
                }

                $page->setVar('crit_input_block', '');
                $page->setVar(
                    array(
                        'CRIT_ID'      => $criterion->getId(),
                        'CRIT_SLUG'    => "crit[{$criterion->getId()}]",
                        'CRIT_GROUP'   => Scrub::text($criterion->getGrouping()),
                        'CRIT_NAME'    => Scrub::text($criterion->getName()),
                        'CRIT_CHECKED' => in_array($criterion->getId(), $checkedIDs) ? 'checked="checked"' : ''
                    )
                );

                if (is_array($descriptors[$criterion->getId()])) {
                    foreach ($descriptors[$criterion->getId()] as $descriptor) {
                        /** @var Descriptor $descriptor */
                        $page->setVar(
                            array(
                                'CRIT_INPUT_VALUE'  => $descriptor->getDescription(),
                                'CRIT_INPUT_WEIGHT' => $descriptor->getValue()
                            )
                        );
                        $page->parse('crit_input_block', 'crit_input', true);
                    }
                } else {
                    // disply blank inputs, they weren't used
                    for ($i = 0; $i < $descriptorCount; ++$i) {
                        $page->setVar('CRIT_INPUT_VALUE', '');
                        $page->setVar('CRIT_INPUT_WEIGHT', $i);
                        $page->parse('crit_input_block', 'crit_input', true);
                    }
                }
                $page->parse('criterion_block', 'criterion', true);

                $currentGrouping = $criterion->getGrouping();
            }
            $page->parse('crit_group_block', 'crit_group', true);
            $page->setVar('no_crit_group_block', '');
        }

        if (!$hasCritGroup) {
            $page->parse('no_crit_group_block', 'no_crit_group');
        }
        $page->parse('criteria_allowed_block', 'criteria_allowed');
    } else {
        // There is no category.
        // If you're seeing this then you created a course builder in a
        // category without a parent, and we're viewing the non-existent
        // parent category.
        $page->parse('no_criteria_allowed_block', 'no_criteria_allowed');
    }

    $handle = $is_course ? 'COURSE_CRITERIA' : 'CLASS_CRITERIA';
    $page->parse($handle, 'rubricTpl', true);
}
