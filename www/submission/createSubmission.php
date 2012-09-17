<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';

$this->setFile(TEMPLATE_content, 'submission/createSubmission.html');
$this->setTab('submission');
$this->setBlock(TEMPLATE_content, 'browse_row', 'browse_block');
$this->setBlock('browse_row', 'file_overwrite', 'file_overwrite_block');
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

$subBoxHandler = new Submission();

// IF PRE-EXISTING
if($this->arg[0] == 'modify' && is_numeric($this->arg[1])) {
    //Modify submission box
    $this->setTitle('Modify Submission Box');

    $subbox = $subBoxHandler->getByID($this->arg[1]);

    /* Output category information */
    if($subbox['code'])
        $catOutput = "<b>".$subbox['categoryName']." (".$subbox['code'].")</b>";
    else
        $catOutput = "<b>".$subbox['categoryName']."</b>";

    /* Output submission type drop down */
    $this->dropdown('type_drop', $subBoxHandler->getTypes(), $subbox['type'], TEMPLATE_content, array(
        'name' => 'TYPE_TEXT',
        'id'   => 'TYPE_VALUE'
    ));

    $resultSetID = $subbox['rubric_result_set'];
    $criteriaArray = array();
    $descriptorArray = array();

    // legacy support
    if($subbox['mark_type_slug'] == 'free') {
        $this->setVar('marktype_block', '');
    }
    else {
        // Output marktype drop down
        $this->dropdown('marktype_drop', $subBoxHandler->getMarkTypes(), $subbox['mark_type'], 'marktype', array(
            'hr_name'       => 'MARKTYPE_TEXT',
            'mark_type_id'  => 'MARKTYPE_VALUE',
            'slug'          => 'MARKTYPE_SLUG'
        ));

        if($subbox['mark_type_slug'] == 'rubric') {
            $rubric = $subBoxHandler->getRubric($subbox['id']);

            $criteriaArray = $rubric->getCriteriaIndices();

            if($rubric instanceof DueWork_Rubric_DescriptorRubric) {
                $descriptorArray = $rubric->getResultIndices();
            }
        }
    }

    // TODO <refactor>

    // ascertain whether or not any marks have already been given
    $stmt = Database::getInstance()->prepare("SELECT COUNT(DISTINCT student_id) FROM `rubric_mark` WHERE `submission_box_id` = :sbid");
    $stmt->execute(array('sbid' => $subbox['id']));
    if($stmt->fetchColumn()) {
        $this->parse('existing_marks_warning_block', 'existing_marks_warning');
    }
    else {
        $this->setVar('existing_marks_warning_block', '');
    }

    $stmt = Database::getInstance()->prepare("SELECT criterion_id FROM rubric_usage WHERE submission_box_id = :sbid");
    $stmt->execute(array('sbid' => $subbox['id']));
    while($cID = $stmt->fetchColumn()) {
        $criteriaArray[] = $cID;
    }

    outputResultSets($this, $resultSetID, outputCriteria($this, $subbox['category'], $criteriaArray, $descriptorArray));
    // TODO </refactor>

    $this->parse('marktype_block', 'marktype');


    /* Convert times */
    $open_date = strtotime($subbox['open_date']);
    $due_date  = strtotime($subbox['date']);
    $late_date = strtotime($subbox['late_date']);

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
        'ID'                => $subbox['id'],
        'CATEGORY'          => $subbox['category'],
        'CURRENT_CAT'       => $catOutput,
        'NAME'              => $subbox['name'],
        'DESCRIPTION'       => $subbox['description'],
        'FILEID'            => $subbox['fid'],
        'ACCEPT_SUBMISSION' => $subbox['accept_submission'] ? ' checked="checked"' : '',
        'MULTI_SUBMISSION'  => $subbox['multi_submission'] ? ' checked="checked"' : '',
        'TOTAL_MARK'        => $subbox['max_mark'],

        'OPEN_DATE'         => date('d/m/Y', $open_date),
        'OPEN_HOUR'         => $open_hour,
        'OPEN_MIN'          => $open_min,
        'OPEN_PM'           => date('a', $open_date) == 'pm' ? 'selected' : '',

        'DUE_DATE'          => date('d/m/Y', $due_date),
        'DUE_HOUR'          => $due_hour,
        'DUE_MIN'           => $due_min,
        'DUE_PM'            => date('a', $due_date) == 'pm' ? 'selected' : '',

        'LATE_DATE'         => date('d/m/Y', $late_date),
        'LATE_HOUR'         => $late_hour,
        'LATE_MIN'          => $late_min,
        'LATE_PM'           => date('a', $late_date) == 'pm' ? 'selected' : '',

        'NOTIF_ENABLED'     => $subbox['notif_enabled'] ? ' checked="checked"' : '',

    );

    $this->setVar($output);

    // check for an associated file
    if($subbox['fid']) {

        $fileHandler = new File;
        $file = $fileHandler->getByID($subbox['fid']);

        $this->setVar('CURRENT_FILE_NAME', $file['realname']);
        $this->setVar('CURRENT_FILE_ICON', $file['iconFile']);
        $this->setVar('CURRENT_FILE_DATE', date('jS M Y, g:ia', $file['submitted']));

        $this->parse('existing_file_block','existing_file');
        $this->parse('file_overwrite_block','file_overwrite');
    }
    // no file found, clear the blocks
    else {
        $this->setVar(array(
            'file_overwrite_block' => '',
            'existing_file_block' => ''
        ));
    }

    $this->parse('browse_block', 'browse_row');

}

// IF NEW
else if($this->arg[0] == 'new') {
    //Create submission box
    $this->setTitle('Create Submission Box');

    $subbox = $subBoxHandler->getByID($this->arg[1]);

    require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

	$groupHandler = new Group;

    // clear blocks that aren't used in this context
    $this->setVar(array(
        'file_overwrite_block' => '',
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

	$categoryList = $groupHandler->involvedInCategoryList($_SESSION['id'], true, WRITE_ACCESS);
	$length = count($categoryList);
	for ($i = 0; $i < $length; $i++) {
		if (!empty($categoryList[$i]['code']))
		$categoryList[$i]['name'] .= ' ('.$categoryList[$i]['code'].')';
	}

    // Output submission type drop down (exclude online assessment type)

	$types = $subBoxHandler->getTypesExcept(array(Assess_Main::ONLINE_ASSESSMENT_TYPE_ID));
	$this->dropDown('type_drop', $types, $this->arg[3],TEMPLATE_content, array(
        'name' => 'TYPE_TEXT',
        'id'   => 'TYPE_VALUE'
    ));

    // Output marktype drop down
    $this->dropdown('marktype_drop', $subBoxHandler->getMarkTypes(), $subbox['mark_type'], 'marktype', array(
        'hr_name'      => 'MARKTYPE_TEXT',
        'mark_type_id' => 'MARKTYPE_VALUE',
        'slug'         => 'MARKTYPE_SLUG'
    ));

    // TODO <refactor>
    outputResultSets($this, -1, outputCriteria($this, $this->arg[1], array(), array()));
    // TODO </refactor>

    $this->parse('marktype_block','marktype');



    /* Convert times */
    $open_date = strtotime('now');
    $due_date  = strtotime('+1 hours');
    $late_date = strtotime('+1 day');

    $output = array(
        'HEADING'           => 'Create',
        'CATEGORY'          => $this->arg[1],
		'ID'		        => '',
        'NAME'              => $file['name'],
        'DESCRIPTION'       => $file['description'],
        'FILEID'            => $file['id'],
        'CATLIST'           => $catOutput,
        'ACCEPT_SUBMISSION' => '', // checked no longer default
        'MULTI_SUBMISSION'  => '',
        'TOTAL_MARK'        => '',

        'OPEN_DATE'         => date('d/m/Y', $open_date),
        'OPEN_HOUR'         => '',
        'OPEN_MIN'          => '',
        'OPEN_PM'           => '',

        'DUE_DATE'          => date('d/m/Y', $due_date),
        'DUE_HOUR'          => '',
        'DUE_MIN'           => '',
        'DUE_PM'            => 'selected',

        'LATE_DATE'         => date('d/m/Y', $late_date),
        'LATE_HOUR'         => '',
        'LATE_MIN'          => '',
        'LATE_PM'           => 'selected',

        'NOTIF_ENABLED' => ' checked="checked"', // on by default
    );

    $this->setVar($output);

}
else {
    error(2);
}

// TODO <refactor>
function outputResultSets(SitePage $page, $checkedIndex, $descriptorSelected) {
    $rs = array();

    $sql = "
        SELECT `resultset_id`, `value`, `label`
        FROM `rubric_result`
        ORDER BY `resultset_id` ASC, `value` ASC";

    $result = Database::getInstance()->query($sql);
    while($row = $result->fetch(PDO::FETCH_NUM)) {
        $rs[$row[0]][$row[1]] = $row[2];
    }

    $page->setVar('resultset_block', '');

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

    // Output descriptor count drop down
    $desCounts = array();
    for($i = 2; $i < 7; ++$i) {
        $desCounts[] = array('value' => $i);
    }
    $page->dropdown('des_count', $desCounts, $descriptorSelected, 'marktype', array(
        'value'  => 'DES_COUNT_VALUE',
    ));

    $page->setVar('RS_0_CHECKED', $checkedIndex == 0 ? 'checked="checked"' : '');
}


function outputCriteria(SitePage $page, $catID, $checkedIDs, $descriptors) {
    $crits = array();

    $desCount = -1;

    $sql = "
        SELECT `grouping`, `id`, `name`
        FROM `rubric_criterion`
        WHERE `parent_id` = :pid
        AND `parent_type` = :ptype
        ORDER BY `grouping` ASC, `name` ASC";

    $stmt = Database::getInstance()->prepare($sql);
    $stmt->execute(array(
        'pid'   => $catID,
        'ptype' => 'category'
    ));

    while($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $crits[$row[0]][$row[1]] = $row[2];
        $desCount = max($desCount, count($crits[$row[0]]));
    }

    $page->setVar('criterion_block','');
    foreach($crits as $group => $elements) {
        foreach($elements as $id => $elem) {
            $page->setVar(array(
                'CRIT_ID'      => $id,
                'CRIT_SLUG'    => str_replace(' ','',strtolower("crit[$id]")),
                'CRIT_LABEL'   => "$group - $elem",
                'CRIT_CHECKED' => in_array($id, $checkedIDs) ? 'checked="checked"' : ''
            ));

            $page->setVar('crit_input_block', '');
            if(count($descriptors[$id])) {
                foreach($descriptors[$id] as $description) {
                    $page->setVar(array(
                        'CRIT_INPUT_VALUE' => $description
                    ));
                    $page->parse('crit_input_block','crit_input', true);
                }
            }
            // disply blank inputs, they weren't used
            else {
                for($i = 0; $i < $desCount; ++$i) {
                    $page->setVar('CRIT_INPUT_VALUE');
                    $page->parse('crit_input_block','crit_input', true);

                }
            }

            $page->parse('criterion_block','criterion', true);
        }
    }
    return $desCount;
}
// TODO </refactor>

?>
