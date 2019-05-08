<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 ************************************************************/

$code = urldecode(implode('/', $this->arg));
header('Location: /learning/class/' . $code);
die();
