<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Filesystem;

use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Resource\Attachment\NavigatorController;

/**
 * Class BrowseController
 *
 * @package Schoolbox\Resource\Attachment\Filesystem
 */
class BrowseController extends NavigatorController
{

    /** @var RosettaStone */
    protected $rosetta;

    /**
     * @param bool          $multi
     * @param string        $mimeFilter
     * @param IConfigLoader $conf
     * @param RosettaStone  $rosetta
     * @param bool          $useFolder
     * @param int           $defaultFolderId
     */
    public function __construct($multi, $mimeFilter = null, $conf = null, $rosetta = null, $useFolder = true, $defaultFolderId = 0)
    {
        parent::__construct($multi, $mimeFilter, $conf, $rosetta, $useFolder, $defaultFolderId);
        $this->rosetta = $rosetta;
    }


    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->rosetta->translate('resource', true, true);
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
        return 'files';
    }
}
