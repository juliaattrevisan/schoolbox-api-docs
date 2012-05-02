<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';

$this->setFile(TEMPLATE_content, 'submission/createSubmission.html');
$this->setTab('submission');
$this->setBlock(TEMPLATE_content, 'browse_row', 'browse_block');
$this->setBlock(TEMPLATE_content, 'marktype', 'marktype_block');

$this->setVar('category_select_block', '');

$subBoxHandler = new Submission();

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

    // legacy support
    if($subbox['mark_type_slug'] == 'free') {
        $this->setVar('marktype_block', '');
    } else {
        /* Output marktype drop down */
        $this->dropdown('marktype_drop', $subBoxHandler->getMarkTypes(), $subbox['mark_type'], 'marktype', array(
            'hr_name' => 'MARKTYPE_TEXT',
            'mark_type_id'   => 'MARKTYPE_VALUE'
        ));
        $this->parse('marktype_block', 'marktype');
    }

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
        'FILEID'            => '',
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

    );

    $this->setVar($output);
    $this->setVar('browse_block', '');

}
else if($this->arg[0] == 'new') {
    //Create submission box
    $this->setTitle('Create Submission Box');

    $subbox = $subBoxHandler->getByID($this->arg[1]);

    require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

	$groupHandler = new Group;

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

    /* Output submission type drop down */

	/* Exclude online assessment type
	 */

	$types = $subBoxHandler->getTypesExcept(array(Assess_Main::ONLINE_ASSESSMENT_TYPE_ID));

	$this->dropDown('type_drop', $types, $this->arg[3],TEMPLATE_content, array(
        'name' => 'TYPE_TEXT',
        'id'   => 'TYPE_VALUE'
    ));


    /* Output marktype drop down */
    $this->dropdown('marktype_drop', $subBoxHandler->getMarkTypes(), $subbox['mark_type'], 'marktype', array(
        'hr_name'      => 'MARKTYPE_TEXT',
        'mark_type_id' => 'MARKTYPE_VALUE'
    ));

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
    );

    $this->setVar($output);

}
else {
    error(2);
}

?>
