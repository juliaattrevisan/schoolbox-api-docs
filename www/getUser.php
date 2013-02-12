<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

$userHandler = new \Schoolbox\UserMapper;

$user = $userHandler->findByID(160);


print_R($user);