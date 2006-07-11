<?php

$this->t_body->set_var("TITLE", HEADING." - Enquiry Point");
$this->t_body->set_var("DROPMENU_TITLE", "$type");		
$this->t_body->set_var("DROPMENU_VALUE", "$enqum");
$this->t_body->set_var("TBDA", "$enqu");
$this->t_body->set_var("FNAME", "$fname");
$this->t_body->set_var("FEMAIL", "$femail");

if($rid1 =="mn") {
//drop menu
	$this->t_body->set_var("REDO", "$red1");
	$this->t_body->set_var("REDC", "$red2");
}
else {
	$this->t_body->set_var("REDO", "");
	$this->t_body->set_var("REDC", "");
}

if($rid2 =="fnn") {
//no user name
	$this->t_body->set_var("REDON", "$red1");
	$this->t_body->set_var("REDCN", "$red2");
}
else {
	$this->t_body->set_var("REDON", "");
	$this->t_body->set_var("REDCN", "");
}

if($rid3 =="fen") {
//mo user email
	$this->t_body->set_var("REDOE", "$red1");
	$this->t_body->set_var("REDCE", "$red2");
}
else {
	$this->t_body->set_var("REDOE", "");
	$this->t_body->set_var("REDCE", "");
}

if($rid4 =="tn") {
//text
	$this->t_body->set_var("REDOEN", "$red1");
	$this->t_body->set_var("REDCEN", "$red2");
}
else {
	$this->t_body->set_var("REDOEN", "");
	$this->t_body->set_var("REDCEN", "");
}

if($rid =="tmn") {
//drop/text

	$this->t_body->set_var("REDO", "$red1");
	$this->t_body->set_var("REDC", "$red2");
	$this->t_body->set_var("REDOEN", "$red1");
	$this->t_body->set_var("REDCEN", "$red2");
}


?>
