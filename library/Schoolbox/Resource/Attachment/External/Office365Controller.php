<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\External;


use OutOfRangeException;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Attachment\NavigatorController;

class Office365Controller extends NavigatorController implements ExternalInterface
{

    /**
     * @param bool          $multi
     * @param string        $mimeFilter
     * @param IConfigLoader $conf
     */
    public function __construct($multi, $mimeFilter = null, $conf = null)
    {
        parent::__construct($multi, $mimeFilter);

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