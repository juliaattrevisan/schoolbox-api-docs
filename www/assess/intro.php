<?php
require_once '../library/assess/assess.php';
require_once '../library/assess/assess-section.php';

/**
 * Display seconds as hours, minutes and seconds. Yoinked from
 * http://www.laughing-buddha.net/php/lib/sec2hms/
 *
 * but slightly modified for formatting.
 * 
 * @param <type> $sec
 * @param <type> $padHours
 * @return <type> 
 */
function sec2hms($sec, $padHours = false)  {
    // start with a blank string
    $hms = "";

    // do the hours first: there are 3600 seconds in an hour, so if we divide
    // the total number of seconds by 3600 and throw away the remainder, we're
    // left with the number of hours in those seconds
    $hours = intval(intval($sec) / 3600);

    // add hours to $hms (with a leading 0 if asked for)
    $hms .= ($padHours)
          ? str_pad($hours, 2, "0", STR_PAD_LEFT). ":"
          : $hours. "hrs ";

    // dividing the total seconds by 60 will give us the number of minutes
    // in total, but we're interested in *minutes past the hour* and to get
    // this, we have to divide by 60 again and then use the remainder
    $minutes = intval(($sec / 60) % 60);

    // add minutes to $hms (with a leading 0 if needed)
    $hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT). "min ";

    // seconds past the minute are found by dividing the total number of seconds
    // by 60 and using the remainder
    $seconds = intval($sec % 60);

    // add seconds to $hms (with a leading 0 if needed)
    $hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);

    $hms .= 'sec';

    // done!
    return $hms;
}

$assessHandler = new Assess();
$sectionHandler = new AssessSection();

$assess = $assessHandler->getIntro($this->arg[0]);
$allSummary = $assessHandler->getIntroSummary($this->arg[0]);
$allSections = $sectionHandler->getList($assess['assess_id']);

$this->setVar(array(
    'ASSESS_ASSESS_ID' => $this->arg[0],
    'ASSESS_TITLE' => $assess['title'],
    'ASSESS_DESCRIPTION' => $assess['description'],
    'FIRST_SECTION' => $allSections[0]['section_id'],
));

$this->setFile(TEMPLATE_content, 'assess/intro.html');
$this->setBlock(TEMPLATE_content, 'section', 'section_block');
$this->setTitle('Assessment: ' . $assess['title']);

$this->setVar(array(
    'DURATION' => sec2hms($assess['duration'])
));

/* Section list and question count
 */
foreach($allSummary as $currentSummary) {
    $this->setVar(array(
        'SECTION_SEQUENCE' => $currentSummary['sequence'],
        'SECTION_TITLE' => $currentSummary['title'],
        'SECTION_NUMQUESTIONS' => $currentSummary['numQuestions'],
    ));

    $this->parse('section_block', 'section', 'true');
}

?>