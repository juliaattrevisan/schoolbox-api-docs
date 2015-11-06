<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment;


use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use SitePage;

abstract class NavigatorController extends AbstractController {

    /**
     * @param bool          $multi
     * @param string        $mimeFilter
     * @param IConfigLoader $conf
     * @param RosettaStone  $rosetta
     */
    public function __construct($multi, $mimeFilter = null, $conf = null, $rosetta = null)
    {
        parent::__construct($multi, $mimeFilter);
        $this->template = 'navigator';
    }


    /**
     * @param SitePage $page
     * @return void
     */
    protected function pparse(SitePage $page)
    {
        $page->setVar(array(
            'MIME_FILTER' => is_null($this->mimeFilter) ? 'null' : "'{$this->mimeFilter}'",
            'SOURCE_SLUG' => substr($this->getSlug(), strpos($this->getSlug(), '/') + 1)
        ));
    }

}