<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (empty($this)) {
    require_once __DIR__ . '/../../include/Bootstrap.php';
    SitePage::localRedir('/resources');
}

/** @var SitePage $this */

// /ajax/resource/internal/browse[/multi[/image[/jpeg]]]
//               /$0      /$1     /$?2   /$?3   /$?4

$mime  = null;
$multi = false;

switch(count($this->arg)) {

    /** @noinspection PhpMissingBreakStatementInspection */
    case 4:
        // cheating, but whatever
        $this->arg[4] = '*';

    /** @noinspection PhpMissingBreakStatementInspection */
    case 5:
        $mime     = $this->arg[3] . '/' . $this->arg[4];

    /** @noinspection PhpMissingBreakStatementInspection */
    case 3:
        $multi    = $this->arg[2] == 'multi';

    case 2:
        $source   = $this->arg[1];
        $location = $this->arg[0];
        break;

    default:
        error(2);
        exit;
}

$factory = new Schoolbox\Resource\Attachment\Factory();

$factory->setSource($location, $source)
        ->setMimeFilter($mime)
        ->allowMulti($multi)
        ->setConfig(new \Schoolbox\Config\Loader());

$factory->build()->parse($this);