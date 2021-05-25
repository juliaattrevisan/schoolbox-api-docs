<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Filesystem;

use OutOfRangeException;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Attachment\AbstractController;
use SitePage;

/**
 * Class GoogleController
 *
 * @package Schoolbox\Resource\Attachment\Filesystem
 */
class GoogleController extends AbstractController
{
    const SCOPE_PREFIX   = 'https://www.googleapis.com/auth/';
    const VIEW_PREFIX    = 'google.picker.ViewId.';
    const FEATURE_PREFIX = 'google.picker.Feature.';

    /** @var array */
    protected array $scopes = [];

    /** @var array */
    protected array $views = [];

    /** @var array */
    protected array $features = [];

    /** @var string */
    protected $gAppsApiKey;

    /** @var string */
    protected $gAppsClientId;

    /** @var string */
    protected $gDriveEnabled;


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
             ->addFeature('SUPPORT_TEAM_DRIVES');

        $this->setViewsFromMimeFilter($mimeFilter);

        if ($conf) {
            $this->gDriveEnabled = $conf->get('googledrive_enabled');
            $this->gAppsApiKey   = $conf->get('googleapps_api_key');
            $this->gAppsClientId = $conf->get('googleapps_client_id');
        }

        if (!$this->gDriveEnabled) {
            throw new OutOfRangeException("Google Drive not enabled");
        } elseif (!($this->gAppsApiKey && $this->gAppsClientId)) {
            throw new OutOfRangeException("Google Apps credentials not specified");
        }
    }


    /**
     * Sets appropriate views from the mimeFilter
     * @param string $mimeFilter
     */
    private function setViewsFromMimeFilter(string $mimeFilter): void
    {
        $this->views = [];

        // no filter = all regular documents and files
        if (is_null($mimeFilter)) {
            // $this->views being empty will fall back to DOCS with all files
            return;
        }

        // explode on common delimiters
        $xpl = preg_split('[ ,;]', $mimeFilter);

        $map = [
            'application/pdf' => 'PDFS',
            'image/*'         => 'DOCS_IMAGES',
            'video/*'         => 'DOCS_VIDEOS',
        ];

        foreach ($xpl as $mime) {
            if (isset($map[$mime])) {
                $this->addView($map[$mime]);
            }
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

        $page->setVar(
            [
                'API_KEY'   => $this->gAppsApiKey,
                'CLIENT_ID' => $this->gAppsClientId,
                'SCOPES'    => implode("','", array_keys($this->scopes)),
                'VIEWS'     => implode(',', array_keys($this->views)),
                'FEATURES'  => implode(',', array_keys($this->features)),
            ]
        );
    }


    /**
     * @param string $scope
     * @return self
     */
    private function addScope(string $scope): self
    {
        $this->scopes[self::SCOPE_PREFIX . $scope] = true;
        return $this;
    }


    /**
     * @param string $view
     * @return self
     */
    private function addView(string $view): self
    {
        $this->views[self::VIEW_PREFIX . $view] = true;
        return $this;
    }


    /**
     * @param string $feature
     * @return self
     */
    private function addFeature(string $feature): self
    {
        $this->features[self::FEATURE_PREFIX . $feature] = true;
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


    /**
     * @return string
     */
    public function getIcon()
    {
        return 'google-drive';
    }
}
