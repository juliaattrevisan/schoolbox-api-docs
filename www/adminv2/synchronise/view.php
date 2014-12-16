<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
$groupHandler = new Group();

if (!$groupHandler->isSuperuser()) {
    error(19);
}

/* Section - sanitise template loading
 */
$section = strtolower($this->arg[0]);

switch($section) {
    case 'staff':
    case 'student':
    case 'parent':
        break;
    default:
        $section = 'intro';
        break;
}

/* Power to the phplib
 */
$this->setFile(TEMPLATE_content, 'adminv2/synchronise/view.html');

$nav = new AdminV2_Nav();
$nav->parse($this, 'synchronise');

$this->setVar(array(
    'CONTENT' => $this->getDi()->get('templateEngine')->render("adminv2/synchronise/{$section}.twig", array(
        'nav' => array(
            'section' => $section,
        ),
        'bucketface' => 'test'
    ))
));


