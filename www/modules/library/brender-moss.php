<?php

$library = new Library_Splash('brender-moss', 1656);

$library->setVars(array(
    'HOME'       => 95,
    'CATALOGUE'  => 96,
    'CLICKVIEW'  => 97,
    'TURNITIN'   => 98,
    'DATABASES'  => 99,
    'RESEARCH'   => 100,
    'READING'    => 101,
    'STUDY'      => 102,
    'JUDAISM'    => 103,
    'SERVICES'   => 104,
    'BLOGSWIKIS' => 105,
    'TEACHERS'   => 106,
    'NEWS'       => 48,
));

$library->render($this);
