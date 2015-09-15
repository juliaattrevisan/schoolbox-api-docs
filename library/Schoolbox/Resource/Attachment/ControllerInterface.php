<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment;


use SitePage;

interface ControllerInterface
{

    const RENDER_INLINE = 'inline';
    const RENDER_IFRAME = 'iframe';


    /**
     * @param bool   $multi
     * @param string $mimeFilter
     */
    public function __construct($multi, $mimeFilter = null);


    /**
     * @param SitePage $page
     * @return void
     */
    public function parse(SitePage $page);


    /**
     * @return string
     */
    public function getSlug();

    /**
     * @return string
     */
    public function getIcon();

    /**
     * @return string
     */
    public function getLabel();


    /**
     * @return string
     */
    public function getUrl();


    /**
     * @return string
     */
    public function getRenderMethod();

}