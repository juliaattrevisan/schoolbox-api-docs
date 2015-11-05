<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\Internal;


use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Resource\Attachment\NavigatorController;

class BrowseController extends NavigatorController implements InternalInterface {

    /** @var RosettaStone */
    protected $rosetta;

    /**
     * @param bool          $multi
     * @param string        $mimeFilter
     * @param IConfigLoader $conf
     * @param RosettaStone  $rosetta
     */
    public function __construct($multi, $mimeFilter = null, $conf = null, $rosetta = null)
    {
        parent::__construct($multi, $mimeFilter);
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