<?php
/**
 * Test environment for question creation.
 * This is used to check for null values from being saved as N; in the db
 *
 * That problem happens in a hard-to-trace manner as it only occurs sometimes
 * under unknown circumstances.
 *
 * (Ghost bug)
 */

foreach(json_decode($_POST['allAnswers']) as $currentAnswer) {
	$answer['answer'][] = $currentAnswer;
	$answer['sequence']++;
}

$answer['answer'] = serialize($answer['answer']);

print_r($_POST);