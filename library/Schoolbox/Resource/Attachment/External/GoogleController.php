<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment\External;


use OutOfRangeException;
use Schoolbox\Config\Loader;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Attachment\AbstractController;
use SitePage;

class GoogleController extends AbstractController implements ExternalInterface {

    const SCOPE_PREFIX   = 'https://www.googleapis.com/auth/';
    const VIEW_PREFIX    = 'google.picker.ViewId.';
    const FEATURE_PREFIX = 'google.picker.Feature.';

    /** @var array  */
    protected $scopes   = array();

    /** @var array  */
    protected $views    = array();

    /** @var array  */
    protected $features = array();

    /** @var string */
    protected $gAppsApiKey;

    /** @var string */
    protected $gAppsClientId;


    /**
     * @param bool          $multi
     * @param string        $mimeFilter
     * @param IConfigLoader $conf
     * @throws OutOfRangeException
     */
    public function __construct($multi, $mimeFilter = null, $conf = null)
    {
        parent::__construct($multi, $mimeFilter);
        $this->addScope('drive.readonly')
             ->addFeature('NAV_HIDDEN');

        $this->setViewsFromMimeFilter($mimeFilter);

        if ($conf) {
            $this->gAppsApiKey   = $conf->get('googleapps_api_key');
            $this->gAppsClientId = $conf->get('googleapps_client_id');
        }

        if(!($this->gAppsApiKey && $this->gAppsClientId)) {
            throw new OutOfRangeException("Google Apps credentials not specified");
        }
    }


    /**
     * @param string $mimeFilter
     */
    public function setViewsFromMimeFilter($mimeFilter)
    {
        $this->views = array();

        // no filter = all regular documents and files
        if (is_null($mimeFilter)) {
            $this->addView('DOCS');
            return;
        }

        // explode on common delimiters
        $xpl = preg_split('[ ,;]', $mimeFilter);

        $map = array(
            'application/pdf' => 'PDFS',
            'image/*'         => 'DOCS_IMAGES',
            'video/*'         => 'DOCS_VIDEOS',
        );

        foreach ($xpl as $mime) {
            if (isset($map[$mime])) {
                $this->addView($map[$mime]);
            }
        }

        // ensure we have *some* kind of view
        if(empty($this->views)) {
            $this->addView('DOCS');
        }
    }

    /**
     * @param SitePage $page
     * @return void
     */
    protected function pparse(SitePage $page)
    {
        if ($this->multi) {
            $this->addFeature('MULTISELECT_ENABLED');
        }

        $page->setVar(array(
            'API_KEY'   => $this->gAppsApiKey,
            'CLIENT_ID' => $this->gAppsClientId,
            'SCOPES'    => implode("','", array_keys($this->scopes)),
            'VIEWS'     => implode(',',   array_keys($this->views)),
            'FEATURES'  => implode(',',   array_keys($this->features)),
        ));
    }


    /**
     * @param string $scope
     * @return self
     */
    public function addScope($scope)
    {
        $this->scopes[self::SCOPE_PREFIX . $scope] = true;
        return $this;
    }

    /**
     * @param string $scope
     * @return self
     */
    public function removeScope($scope)
    {
        unset($this->scopes[self::SCOPE_PREFIX . $scope]);
        return $this;
    }


    /**
     * @param string $view
     * @return self
     */
    public function addView($view)
    {
        $this->views[self::VIEW_PREFIX . $view] = true;
        return $this;
    }

    /**
     * @param string $view
     * @return self
     */
    public function removeView($view)
    {
        unset($this->views[self::VIEW_PREFIX . $view]);
        return $this;
    }


    /**
     * @param string $feature
     * @return self
     */
    public function addFeature($feature)
    {
        $this->features[self::FEATURE_PREFIX . $feature] = true;
        return $this;
    }

    /**
     * @param string $feature
     * @return self
     */
    public function removeFeature($feature)
    {
        unset($this->features[self::FEATURE_PREFIX . $feature]);
        return $this;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return 'Google Drive';
    }


    /**
     * @return string
     */
    public function getRenderMethod()
    {
        return self::RENDER_IFRAME;
    }

}