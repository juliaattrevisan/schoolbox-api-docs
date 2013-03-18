<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';

$this->setFile(TEMPLATE_content, 'submission/createSubmission.html');
$this->setTab('submission');
$this->setBlock(TEMPLATE_content, 'browse_row', 'browse_block');
$this->setBlock('browse_row', 'existing_file', 'existing_file_block');
$this->setBlock(TEMPLATE_content, 'marktype', 'marktype_block');
$this->setBlock('marktype', 'criterion', 'criterion_block');
$this->setBlock('criterion', 'crit_input', 'crit_input_block');
$this->setBlock('marktype', 'resultset', 'resultset_block');
$this->setBlock('resultset', 'result', 'result_block');
$this->setBlock('marktype', 'des_count', 'des_count_block');
$this->setBlock('marktype', 'existing_marks_warning', 'existing_marks_warning_block');

$this->setBlock(TEMPLATE_content, 'notification_enable', 'notification_enable_block');
if(Cfg::get('due_work_notifications_enabled')) {
    $this->parse('notification_enable_block', 'notification_enable');
}
else {
    $this->setVar('notification_enable_block', '');
}

$this->setVar('category_select_block', '');
$this->setVar('existing_marks_warning_block', '');

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;

$mapper      = new SubmissionBoxMapper();
$type_mapper = new SubmissionBox\WorkTypeMapper();
$mark_mapper = new SubmissionBox\MarkTypeMapper();

$rubricHandler = new DueWork_Rubric_Handler();

//
// Should work types include or not include online assessments?
// They do for pre-existing items, they don't for new items.
$work_types = $type_mapper->getAllExcept(SubmissionBox\WorkType::TYPE_ONLINE_ASSESSMENT);
$mark_types = $mark_mapper->getAll();

// IF PRE-EXISTING
if($this->arg[0] == 'modify' && is_numeric($this->arg[1])) {
    //Modify submission box
    $this->setTitle('Modify Submission Box');

    $submission = $mapper->findById($this->arg[1]);

    /* Output category information */
    $cat_name = $submission->getCategoryName();
    $codes    = $submission->getCategoryCodes();
    if (!empty($codes)) {
        $catOutput = "<b>".$cat_name." (".implode(', ', $codes).")</b>";
    }
    else {
        $catOutput = "<b>".$cat_name."</b>";
    }

    /* Output submission work_type drop down */
    $this->dropdown('type_drop', $work_types, $submission->getWorkType(), TEMPLATE_content, array(
        'name' => 'TYPE_TEXT',
        'id'   => 'TYPE_VALUE'
    ));

    // legacy support
    $mark_type = $submission->getMarkType();
    if ($mark_type == SubmissionBox\MarkType::TYPE_FREE) {
        $this->setVar('marktype_block', '');
    }
    else {
        // Output marktype drop down
        $this->dropdown('marktype_drop', $mark_types, $mark_type, 'marktype', array(
            'name' => 'MARKTYPE_TEXT',
            'id'   => 'MARKTYPE_VALUE',
            'slug' => 'MARKTYPE_SLUG'
        ));

        // if the rubric creation fails it will throw an exception, in which case just output the empty sets
        try {
            if($mark_type == SubmissionBox\MarkType::TYPE_RUBRIC) {
                $rubric = $rubricHandler->getRubric($submission->getId());
                $resultSetID = $submission->getRubricResultSet();

                if($rubricHandler->marksAlreadyGiven($submission->getId())) {
                    $this->parse('existing_marks_warning_block', 'existing_marks_warning');
                }

                $criteria = $rubric->getCriteriaIndices();
                $results = $rubric->getResults();
                $resultCount = $rubric->getResultCount();

                outputCriteria($this, $submission->getCategory(), $criteria, $results, $resultCount);
                outputResultSets($this, $resultSetID, $resultCount);
            }
            else {
                outputResultSets($this);
                outputCriteria($this, $submission->getCategory(), array(), array());
            }
        } catch(Exception $e) {
            outputResultSets($this);
            outputCriteria($this, $submission->getCategory(), array(), array());
        }


    }

    $this->parse('marktype_block', 'marktype');

    /* Convert times */
    $open_date = strtotime($submission->getOpenDate());
    $due_date  = strtotime($submission->getDueDate());
    $late_date = strtotime($submission->getLateDate());

    if(date('H:i:s', $open_date) == '00:00:00') {
        $open_hour = '';
        $open_min = '';
    } else {
        $open_hour = date('g',$open_date);
        $open_min = date('i',$open_date);
    }

    if(date('H:i:s', $due_date) == '23:59:59') {
        $due_hour = '';
        $due_min = '';
    } else {
        $due_hour = date('g',$due_date);
        $due_min = date('i',$due_date);
    }

    if(date('H:i:s', $late_date) == '23:59:59') {
        $late_hour = '';
        $late_min = '';
    } else {
        $late_hour = date('g',$late_date);
        $late_min = date('i',$late_date);
    }


    $output = array(
        'HEADING'           => 'Modify',
        'ID'                => $submission->getId(),
        'CATEGORY'          => $submission->getCategory(),
        'CURRENT_CAT'       => $catOutput,
        'NAME'              => $submission->getName(),
        'DESCRIPTION'       => $submission->getDescription(),
        'FILEID'            => $submission->getLegacyFileId(),
        'ACCEPT_SUBMISSION' => $submission->getAcceptOnline() ? ' checked="checked"' : '',
        'MULTI_SUBMISSION'  => $submission->getAcceptMultiple() ? ' checked="checked"' : '',
        'TOTAL_MARK'        => $submission->getMaxMark(),

        'OPEN_DATE' => date('d/m/Y', $open_date),
        'OPEN_HOUR' => $open_hour,
        'OPEN_MIN'  => $open_min,
        'OPEN_PM'   => date('a', $open_date) == 'pm' ? 'selected' : '',

        'DUE_DATE' => date('d/m/Y', $due_date),
        'DUE_HOUR' => $due_hour,
        'DUE_MIN'  => $due_min,
        'DUE_PM'   => date('a', $due_date) == 'pm' ? 'selected' : '',

        'LATE_DATE' => date('d/m/Y', $late_date),
        'LATE_HOUR' => $late_hour,
        'LATE_MIN'  => $late_min,
        'LATE_PM'   => date('a', $late_date) == 'pm' ? 'selected' : '',

        'FILE_MAX_UPLOAD' => Cfg::get('file_upload_count'),
        'MAX_FILE_SIZE'   => Cfg::get('max_file_size'),
        'SESSION_NAME'    => session_name(),
        'SESSION_ID'      => session_id(),

    );

    foreach(array('UPCOMING' => '1 day', 'OVERDUE' => '1 hour', 'RETURNED' => '1 hour') as $i => $j) {
        $notification = $submission->getNotifications()->getOfType(strtolower($i));
        $is_enabled   = $notification instanceof SubmissionBox\Notification && $notification->getEnabled();
        $output["NOTIF_{$i}_ENABLED"] = $is_enabled ? ' checked="checked"' : '';
        $output["{$i}_THRESHHOLD"] = $j;
    }


    $this->setVar($output);

    // check for an associated file
    $file_links = $submission->getFileLinks();
    if ($file_links->count() > 0) {
        foreach ($file_links as $file_link) {
            $file = $file_link->getFile();
            $this->setVar('CURRENT_FILE_NAME', $file->getFilename());
            $this->setVar('CURRENT_FILE_ID',   $file->getId());
            $this->setVar('CURRENT_FILE_ICON', $file->getIconFromFilename());
            $this->setVar('CURRENT_FILE_DATE', date('jS M Y, g:ia', strtotime($file->getUploadedOn())));
            $this->parse('existing_file_block','existing_file', true);
        }
    }
    elseif ($submission->getLegacyFileId()) {

        $fileHandler = new File;
        $file = $fileHandler->getByID($submission->getLegacyFileId());

        $this->setVar('CURRENT_FILE_NAME', $file['realname']);
        $this->setVar('CURRENT_FILE_ICON', $file['iconFile']);
        $this->setVar('CURRENT_FILE_ID',   'old');
        $this->setVar('CURRENT_FILE_DATE', date('jS M Y, g:ia', $file['submitted']));

        $this->parse('existing_file_block','existing_file');
    }
    else {
        $this->setVar('existing_file_block', '');
    }

    $this->parse('browse_block', 'browse_row');

}

// IF NEW
else if($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
    //Create submission box
    $this->setTitle('Create Submission Box');

    require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

    $groupHandler = new Group;

    // clear blocks that aren't used in this context
    $this->setVar(array(
        'existing_file_block' => '',
        'existing_marks_warning_block'
    ));

    if(is_numeric($this->arg[2]) && $this->arg[2] > 0) {
        /* File ID submitted, associate box with file */
        require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/file.php';

        $fileHandler = new File;
        $file = $fileHandler->getByID($this->arg[2]);
        if(is_array($file))
            $this->setVar('browse_block', '');
        else
            $this->parse('browse_block', 'browse_row');

    }
    else {
        $this->parse('browse_block', 'browse_row');
    }

    /* Output category information */
    $this->setVar('CURRENT_CAT', '');

    // Output submission work_type drop down (exclude online assessment work_type)

    $this->dropDown('type_drop', $work_types, $this->arg[3], TEMPLATE_content, array(
        'name' => 'TYPE_TEXT',
        'id'   => 'TYPE_VALUE'
    ));

    // Output marktype drop down
    $this->dropdown('marktype_drop', $mark_types, null, 'marktype', array(
        'name' => 'MARKTYPE_TEXT',
        'id'   => 'MARKTYPE_VALUE',
        'slug' => 'MARKTYPE_SLUG'
    ));

    outputResultSets($this);
    outputCriteria($this, $this->arg[1], array(), array());

    $this->parse('marktype_block','marktype');


    /* Convert times */
    $open_date = strtotime('now');
    $due_date  = strtotime('+1 hours');
    $late_date = strtotime('+1 day');

    $output = array(
        'HEADING'           => 'Create',
        'CATEGORY'          => $this->arg[1],
        'ID'                => '',
        'NAME'              => $file['name'],
        'DESCRIPTION'       => $file['description'],
        'FILEID'            => $file['id'],
        'CATLIST'           => $catOutput,
        'ACCEPT_SUBMISSION' => '', // checked no longer default
        'MULTI_SUBMISSION'  => '',
        'TOTAL_MARK'        => '',

        'OPEN_DATE' => date('d/m/Y', $open_date),
        'OPEN_HOUR' => '',
        'OPEN_MIN'  => '',
        'OPEN_PM'   => '',

        'DUE_DATE' => date('d/m/Y', $due_date),
        'DUE_HOUR' => '',
        'DUE_MIN'  => '',
        'DUE_PM'   => 'selected',

        'LATE_DATE' => date('d/m/Y', $late_date),
        'LATE_HOUR' => '',
        'LATE_MIN'  => '',
        'LATE_PM'   => 'selected',

        'FILE_MAX_UPLOAD' => Cfg::get('file_upload_count'),
        'MAX_FILE_SIZE'   => Cfg::get('max_file_size'),
        'SESSION_NAME'    => session_name(),
        'SESSION_ID'      => session_id(),
    );

    // notifications on by default
    foreach(array('UPCOMING' => '1 day', 'OVERDUE' => '1 hour', 'RETURNED' => '1 hour') as $i => $j) {
        $output["NOTIF_{$i}_ENABLED"] = ' checked="checked"';
        $output["{$i}_THRESHHOLD"] = $j;
    }

    $this->setVar($output);

}
else {
    error(2);
}








function outputResultSets(SitePage $page, $checkedIndex = -1, $descriptorCount = 2) {
    $rubricHandler = new DueWork_Rubric_Handler();
    $rs = $rubricHandler->getResultSets();

    $page->setVar('resultset_block', '');

    if(count($rs)) {
        foreach($rs as $i => $set) {
            $page->setVar('result_block', '');
            foreach($set as $item) {
                $page->setVar(array(
                    'RS_ID'           => $i,
                    'RS_RESULT_LABEL' => $item,
                    'RS_CHECKED'      => $i == $checkedIndex ? 'checked="checked"' : ''
                ));
                $page->parse('result_block', 'result', true);
            }
            $page->parse('resultset_block', 'resultset', true);
        }
    }

    // Output descriptor count drop down from 2-6
    $desCounts = array();
    for($i = 2; $i < 7; ++$i) {
        $desCounts[] = array('value' => $i);
    }
    $page->dropdown('des_count', $desCounts, $descriptorCount, 'marktype', array(
        'value'  => 'DES_COUNT_VALUE',
    ));

    $page->setVar('RS_0_CHECKED', $checkedIndex == 0 ? 'checked="checked"' : '');
}


function outputCriteria(SitePage $page, $catID, $checkedIDs, $descriptors, $descriptorCount = 2) {

    $rubricHandler = new DueWork_Rubric_Handler();

    $crits = $rubricHandler->getAvailableCriteria($catID);

    $page->setVar('criterion_block','');
    foreach($crits as $group => $elements) {
        foreach($elements as $id => $elem) {
            $page->setVar(array(
                'CRIT_ID'      => $id,
                'CRIT_SLUG'    => "crit[$id]",
                'CRIT_LABEL'   => "$group - {$elem['name']}",
                'CRIT_CHECKED' => in_array($id, $checkedIDs) ? 'checked="checked"' : ''
            ));

            $page->setVar('crit_input_block', '');

            if(is_array($descriptors[$id])) {
                foreach($descriptors[$id] as $descriptor) {
                    $page->setVar(array(
                        'CRIT_INPUT_VALUE' => $descriptor->getDescription()
                    ));
                    $page->parse('crit_input_block','crit_input', true);
                }
            }
            // disply blank inputs, they weren't used
            else {
                for($i = 0; $i < $descriptorCount; ++$i) {
                    $page->setVar('CRIT_INPUT_VALUE');
                    $page->parse('crit_input_block','crit_input', true);

                }
            }

            $page->parse('criterion_block','criterion', true);
        }
    }
}
