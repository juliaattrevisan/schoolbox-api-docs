<?php

require_once 'synergetic-curriculum.php';
$synCurric = new SynergeticCurriculum;

$fields = array('','1','2','3','4','5','6','7','8');

if($this->arg[0]) {
    $subject = $synCurric->get($this->arg[0]);

    if(is_array($subject)) {
        $this->setFile(TEMPLATE_content, 'modules/mgs_curriculum/result.html');
        $this->setBlock(TEMPLATE_content, 'unit_tab','unit_tab_block');
        $this->setBlock(TEMPLATE_content, 'unit','unit_block');

        $this->setVar('SUBJECT_NAME', $this->arg[0]);

        for($x=0;$subject['UnitName'.$fields[$x]]; $x++) {
            $output = array('UNIT_NAME'   => nl2br(scrubTEXT($subject['UnitName'.$fields[$x]])),
                            'HOURS'       => nl2br(scrubTEXT($subject['NoOfHours'.$fields[$x]])),
                            'UNDEROUT'    => nl2br(scrubTEXT($subject['UnderOut'.$fields[$x]])),
                            'UNITCONT'    => nl2br(scrubTEXT($subject['UnitCont'.$fields[$x]])),
                            'SKILLS'      => nl2br(scrubTEXT($subject['Skills'.$fields[$x]])),
                            'ASSESSMENT'  => nl2br(scrubTEXT($subject['Assessment'.$fields[$x]])),
                            'RESOURCES'   => nl2br(scrubTEXT($subject['Resources'.$fields[$x]])),
                            );
            $this->setVar($output);
            $this->parse('unit_tab_block','unit_tab',true);
            $this->parse('unit_block','unit',true);
        }
    }
    else
        print "unable to find";
}
else
    print "error";
?>
