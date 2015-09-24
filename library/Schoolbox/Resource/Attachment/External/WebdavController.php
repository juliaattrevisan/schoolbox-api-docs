<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\External;


use DomainException;
use OutOfRangeException;
use Schoolbox\Resource\Attachment\NavigatorController;

class WebdavController extends NavigatorController implements ExternalInterface {


    /**
     * @param bool   $multi
     * @param string $mimeFilter
     * @throws OutOfRangeException
     */
    public function __construct($multi, $mimeFilter = null)
    {
        try {
            \PortfolioWebDAV::factory();
        } catch (DomainException $e) {
            throw new OutOfRangeException("No WebDAV support available", 0, $e);
        }

        // disallow null: cast to '*/*'
        parent::__construct($multi, is_null($mimeFilter) ? '*/*' : $mimeFilter);
    }


    /**
     * @return string
     */
    public function getLabel()
    {
        return 'Drives';
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
        return 'network';
    }

}
