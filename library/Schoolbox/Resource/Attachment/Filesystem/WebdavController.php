<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Resource\Attachment\External;

use DomainException;
use OutOfRangeException;
use Schoolbox\Resource\Attachment\NavigatorController;

/**
 * Class WebdavController
 *
 * @package Schoolbox\Resource\Attachment\External
 */
class WebdavController extends NavigatorController
{

    /**
     * @param bool   $multi
     * @param string $mimeFilter
     * @throws OutOfRangeException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
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
        return self::RENDER_IFRAME;
    }


    /**
     * @return string
     */
    public function getIcon()
    {
        return 'network';
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return sprintf(
            '/drives/picker/webdav?mime=%s',
            urlencode($this->mimeFilter)
        );
    }

}
