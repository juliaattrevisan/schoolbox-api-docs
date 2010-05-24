<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/submission.php';

$this->setFile(TEMPLATE_content, 'submission/createSubmission.html');
$this->setTab('submission');
$this->setBlock(TEMPLATE_content, 'browse_row', 'browse_block');
$this->setBlock(TEMPLATE_content, 'category_select', 'category_select_block');

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
    $this->dropdown('type_drop', $subBoxHandler->getTypes(), $subbox['type'], TEMPLATE_content, array('name'=>'TYPE_TEXT','id'=>'TYPE_VALUE'));

    /* Convert times */
    $open_date = strtotime($subbox['open_date']);
    $due_date  = strtotime($subbox['date']);
    $late_date = strtotime($subbox['late_date']);

    $open_hour = date('g',$open_date);
    $open_min = date('i',$open_date);
    $due_hour = date('g',$due_date);
    $due_min = date('i',$due_date);
    $late_hour = date('g',$late_date);
    $late_min = date('i',$late_date);

    $output = array('HEADING'   => 'Modify',
                    'ID'       => $subbox['id'],
					'CATEGORY'	=> $subbox['category'],
                    'CURRENT_CAT'   => $catOutput,
                    'DESC'      => $subbox['description'],
                    'FILEID'    => '',
                    'OPEN_DATE' => date('d/m/Y', $open_date),
                    'OPEN_HOUR' => $open_hour,
                    'OPEN_MIN'  => $open_min,
                    'OPEN_PM'   => date('a', $open_date) == 'pm'?'selected':'',
                    'DUE_DATE' => date('d/m/Y', $due_date),
                    'DUE_HOUR' => $due_hour,
                    'DUE_MIN'  => $due_min,
                    'DUE_PM'   => date('a', $due_date) == 'pm'?'selected':'',
                    'LATE_DATE' => date('d/m/Y', $late_date),
                    'LATE_HOUR' => $late_hour,
                    'LATE_MIN'  => $late_min,
                    'LATE_PM'   => date('a', $late_date) == 'pm'?'selected':'',
                    );
						
	
    $this->setVar($output);


    $this->setVar('browse_block', '');


}
else if($this->arg[0] == 'new'  ) {
    //Create submission box
    $this->setTitle('Create Submission Box');

    require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/category.php';
	require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/group.php';

	$groupHandler = new Group;

    if(is_numeric($this->arg[2])) {
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
	$subjectList = false;
	if (SYNERGY_enable) {
		require_once SITE_root . '/library/synergetic-user.php';
		$synUser = new SynergeticUser;
		$subjectList = $synUser->getSubjectList($_SESSION['sid'], $_SESSION['id']);
	}
	
	$categoryList = $groupHandler->involvedCategoryList($_SESSION['id'], $subjectList, 1);
	$length = count($categoryList);
	for ($i = 0; $i < $length; $i++) {
		if (!empty($categoryList[$i]['code']))
		$categoryList[$i]['name'] .= ' ('.$categoryList[$i]['code'].')'; 
	}
	
	$this->dropDown('category', $categoryList, $this->arg[1], 'category_select', array('id' => 'CATEGORY_ID', 'name' => 'CATEGORY_NAME'));
	$this->parse('category_select_block', 'category_select');

    /* Output submission type drop down */
	$this->dropDown('type_drop', $subBoxHandler->getTypes(), $this->arg[3],TEMPLATE_content, array('name'=>'TYPE_TEXT','id'=>'TYPE_VALUE'));

    /* Convert times */
    $open_date = strtotime('now');;
    $due_date  = strtotime('+1 hours');
    $late_date = strtotime('+1 day');

    $open_hour = date('g',$open_date);
    $due_hour = date('g',$due_date);
    $late_hour = date('g',$late_date);

    $output = array('HEADING'   => 'Create',
                    'CATEGORY'  => $this->arg[1],
					'ID'		=> '',
                    'DESC'      => $file['description'],
                    'FILEID'    => $file['id'],
                    'CATLIST'   => $catOutput,
                    'OPEN_DATE' => date('d/m/Y', $open_date),
                    'OPEN_HOUR' => $open_hour,
                    'OPEN_MIN'  => '00',
                    'OPEN_PM'   => date('a', $open_date) == 'pm'?'selected':'',
                    'DUE_DATE' => date('d/m/Y', $due_date),
                    'DUE_HOUR' => $due_hour,
                    'DUE_MIN'  => '00',
                    'DUE_PM'   => date('a', $due_date) == 'pm'?'selected':'',
                    'LATE_DATE' => date('d/m/Y', $late_date),
                    'LATE_HOUR' => $late_hour,
                    'LATE_MIN'  => '00',
                    'LATE_PM'   => date('a', $late_date) == 'pm'?'selected':'',
                    );


    $this->setVar($output);

}
else {
    error(2);
}

?>
