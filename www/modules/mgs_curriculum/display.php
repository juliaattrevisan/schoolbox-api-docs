<?php

require_once 'synergetic-curriculum.php';
$synCurric = new SynergeticCurriculum;

$fields = array('','1','2','3','4','5','6','7','8');

if($this->arg[0]) {
    $subject = $synCurric->get($this->arg[0]);

    if(is_array($subject)) {
        $this->setFile(TEMPLATE_content, 'modules/mgs_curriculum/result.html');

        $output = array('SUBJECT_NAME'=> scrubTEXT($subject['Heading']),
                        'UNIT_NAME'   => scrubTEXT($subject['UnitName'.$this->arg[1]]),
                        'HOURS'       => nl2br(scrubTEXT($subject['NoOfHours'.$this->arg[1]])),
                        'UNDEROUT'    => nl2br(scrubTEXT($subject['UnderOut'.$this->arg[1]])),
                        'UNITCONT'    => nl2br(scrubTEXT($subject['UnitCont'.$this->arg[1]])),
                        'SKILLS'      => nl2br(scrubTEXT($subject['Skills'.$this->arg[1]])),
                        'ASSESSMENT'  => nl2br(scrubTEXT($subject['Assessment'.$this->arg[1]])),
                        'RESOURCES'   => nl2br(scrubTEXT($subject['Resources'.$this->arg[1]])),
                        );
        $this->setVar($output);

    }
    else
        print "unable to find";
}
else
    print "error";
?>
