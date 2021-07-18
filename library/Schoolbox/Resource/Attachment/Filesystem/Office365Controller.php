<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Filesystem;

use OutOfRangeException;
use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Resource\Attachment\NavigatorController;

/**
 * Class Office365Controller
 *
 * @package Schoolbox\Resource\Attachment\Filesystem
 */
class Office365Controller extends NavigatorController
{

    /**
     * @param bool               $multi
     * @param string             $mimeFilter
     * @param IConfigLoader|null $conf
     * @param RosettaStone|null  $rosetta
     * @param bool               $useFolder
     * @throws OutOfRangeException
     */
    public function __construct($multi, $mimeFilter = null, $conf = null, $rosetta = null, $useFolder = true)
    {
        parent::__construct($multi, $mimeFilter, $conf, $rosetta, $useFolder);

        if (!$conf->get('office365_tenant')) {
            throw new OutOfRangeException("Office 365 tenancy not specified");
        }
    }


    /**
     * @return string
     */
    public function getLabel()
    {
        return 'Office 365';
    }


    /**
     * @return string
     */
    public function getRenderMethod()
    {
        return self::RENDER_INLINE;
    }


    /**
     * @return string
     */
    public function getIcon()
    {
        return 'office-365';
    }
}
