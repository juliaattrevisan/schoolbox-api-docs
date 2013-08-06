<?php


$this->setFile(TEMPLATE_content, 'submission/returnWork.html');
$this->setBlock(TEMPLATE_content, 'mark_type_percent', 'mark_type_percent_block');
$this->setBlock(TEMPLATE_content, 'mark_type_letter', 'mark_type_letter_block');
$this->setBlock(TEMPLATE_content, 'mark_type_raw', 'mark_type_raw_block');
$this->setBlock(TEMPLATE_content, 'mark_type_free', 'mark_type_free_block');
$this->setTitle('Return Student Work');
$this->setTab('submission');

$subHandler = new Submission;
$rubricHandler = new DueWork_Rubric_Handler();
$userHandler = new User;

if(is_numeric($this->arg[0]) && is_numeric($this->arg[1]) ) {

    $subbox = $subHandler->getByID($this->arg[0]);
    $user = $userHandler->getByID($this->arg[1]);
    // $subReturn = $subReturnHandler->getOneByUser($this->arg[1], $this->arg[0]);

    // Check user owners box they are returning to
    if($subbox['owner'] != $_SESSION['id']) {
        error(25);
    }

    $output = array(
        'CATEGORY'  => Scrub::text($subbox['categoryName']),
        'ASSNAME'   => Scrub::text($subbox['name']),
        'BOX_ID'    => $this->arg[0],
        'NAME'      => $userHandler->formatName($user),
        'SYN_ID'    => $user['synergy_id'],
        'UID'       => $this->arg[1],
    );

    $this->setVar($output);

    $marksheet = Marksheet_Factory::build($subbox['mark_type_slug']);
    $mark = $marksheet->retrieveMark($this->arg[1], $this->arg[0], false, false);

    $this->setVar('MARK_TYPE_COMMENT', $marksheet->getComment());

    // blockify and clear all the marktypes
    foreach($subHandler->getMarkTypes(true) as $type) {
        $this->setBlock(TEMPLATE_content, "mark_type_{$type['slug']}", "mark_type_{$type['slug']}_block");
        $this->setVar("mark_type_{$type['slug']}_block", '');
    }

    /* RUBRIC LOGICZ */

    $rubric = $rubricHandler->getRubric($subbox['id']);

    $rubricMark = new DueWork_Rubric_Mark($rubric);
    $rubricMark->populateMarks($user['uid']);

    $data = $rubricMark->toArray();

    $json = array();
    $legs = array();

    for($i = 0; $i < count($data['criteria']); ++$i) {
        $json[] = array($i, $data['criteria'][$i]['value'] < 0 ? 0 : $data['criteria'][$i]['value']);
        $legs[] = array('label' => $data['criteria'][$i]['label']);
    }

    $this->setVar(array(
        'GRAPH_DATA' => json_encode($json),
        'GRAPH_LEGS' => json_encode($legs),
        'GRAPH_MAX'  => count($data['criteria'][0]['results']) - 1
    ));

    $this->setBlock('mark_type_rubric', 'rubric_rating', 'rubric_rating_block');
    $this->setBlock('mark_type_rubric', 'rubric_crit', 'rubric_crit_block');
    $this->setBlock('rubric_crit', 'rubric_crit_rating', 'rubric_crit_rating_block');
    $this->setBlock('rubric_crit', 'rubric_grouping', 'rubric_grouping_block');

    $colCount = count($data['criteria'][0]['results']);

    for($i = 0; $i < $colCount; ++$i) {
        $this->parse('rubric_rating_block', 'rubric_rating', true);
    }

    for($i = 0; $i < count($data['criteria']); ++$i) {
        // display a grouping row
        if($i == 0 || $data['criteria'][$i]['group'] != $data['criteria'][$i - 1]['group']) {
            $this->setVar(array(
                'RUBRIC_GROUP_SPAN' => $colCount + 1,
                'RUBRIC_GROUP_LABEL' => $data['criteria'][$i]['group']
            ));
            $this->parse('rubric_grouping_block','rubric_grouping', false);
        }
        else {
            $this->setVar('rubric_grouping_block', '');
        }

        $this->setVar(array(
            'rubric_crit_rating_block' => '',
            'RUBRIC_CRIT_LABEL' => $data['criteria'][$i]['label'],
            'RUBRIC_CRIT_NAME' => preg_replace("#[^a-z0-9\-]#is", '', strtolower($data['criteria'][$i]['label'])),
            'RUBRIC_CRIT_INDEX' => $i,
            'RUBRIC_CRIT_ID' => $data['criteria'][$i]['id']
        ));

        for($j = 0; $j < count($data['criteria'][0]['results']); ++$j) {
            $this->setVar(array(
                'RUBRIC_CRIT_RATING_LABEL' => $data['criteria'][$i]['results'][$j],
                'RUBRIC_CRIT_RATING_VALUE' => $j,
                'RUBRIC_CRIT_RATING_CLASS' => 'lol',
                'RUBRIC_CRIT_RATING_SELECTED' => $data['criteria'][$i]['value'] == $j ? 'checked="checked"' : '',
            ));
            $this->parse('rubric_crit_rating_block', 'rubric_crit_rating', true);
        }

        $this->parse('rubric_crit_block', 'rubric_crit', true);
    }

    // calculate scores
    $sum = 0;
    $max = 0;
    for($i = 0; $i < count($data['criteria']); ++$i) {
        $sum += $data['criteria'][$i]['value'];
        $max += count($data['criteria'][0]['results']) - 1;
    }

    $this->setVar(array(
        'RUBRIC_GRADE_SUM' => $sum,
        'RUBRIC_GRADE_MAX' => $max
    ));


    $slug = $marksheet->getSlug();
    $this->parse("mark_type_{$slug}_block", "mark_type_{$slug}");

}
else
    error(2);


?>
