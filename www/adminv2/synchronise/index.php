<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\External\Normalise\Admin\Factory;

if (!$_SESSION['user']->isSuperuser()) {
    error(19);
}

$factory = new Factory($this->arg[0]);
$processor = $factory->produce();
$processor->execute();

/* Power to the phplib
 */
$this->setFile(TEMPLATE_content, 'adminv2/synchronise/view.html');

$nav = new AdminV2_Nav($this->getDi()->get('config'));
$nav->parse($this, 'synchronise');

$this->setVar(array(
    'CONTENT' => $this->getDi()->get('templateEngine')->render("adminv2/synchronise/{$factory->getSection()}.twig", array(
        'nav' => array(
            'section' => $factory->getSection(),
        ),
        'data' => array(
            'allPeople' => $processor->getAllPeople(),
            'campus' => array(
                'external' => $processor->getExternalCampus(),
                'schoolbox' => $processor->getSchoolboxCampus()
            ),
            'year' => array(
                'external' => $processor->getExternalYear(),
                'schoolbox' => $processor->getSchoolboxYear()
            ),
            'house' => array(
                'external' => $processor->getExternalHouse(),
                'schoolbox' => $processor->getSchoolboxHouse()
            ),
            'role' => array(
                'external' => $processor->getExternalRoles(),
                'schoolbox' => $processor->getSchoolboxRoles(),
            ),
        ),
    ))
));


