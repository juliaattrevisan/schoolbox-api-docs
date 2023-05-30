<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Filesystem;

use OutOfRangeException;
use Schoolbox\Config\ConfigMapper;
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
    const VIEW_PREFIX    = 'google.picker.ViewId.';
    const FEATURE_PREFIX = 'google.picker.Feature.';

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
     * A list of mime types accepted by google picker
     * They must be in some unique format, for some google reason
     *
     * @var array
     */
    private array $mimeTypes = [];


    /**
     * @param bool          $multi
     * @param string|null   $mimeFilter
     * @param IConfigLoader $conf
     * @throws OutOfRangeException
     */
    public function __construct($multi, $mimeFilter = null, $conf = null)
    {
        parent::__construct($multi, $mimeFilter);

        $this->addFeature('SUPPORT_TEAM_DRIVES');
        $this->setViewsFromMimeFilter($mimeFilter);

        if ($conf) {
            $this->gDriveEnabled = $conf->get(ConfigMapper::NAME_GOOGLEDRIVE_ENABLED);
            $this->gAppsClientId = $conf->get(ConfigMapper::NAME_GOOGLEAPPS_CLIENT_ID);
        }

        if (!$this->gDriveEnabled) {
            throw new OutOfRangeException("Google Drive not enabled");
        } elseif (!$this->gAppsClientId) {
            throw new OutOfRangeException("Google Apps credentials not specified");
        }
    }


    /**
     * Sets appropriate views from the mimeFilter
     * @param string|null $mimeFilter
     */
    private function setViewsFromMimeFilter(?string $mimeFilter): void
    {
        $this->views = [];

        // no filter = all regular documents and files
        if (is_null($mimeFilter)) {
            // $this->views being empty will fall back to DOCS with all files
            return;
        }

        // explode on common delimiters
        $xpl = preg_split('/[ ,;]+/', $mimeFilter);

//        $map = [
//            'application/pdf' => 'PDFS',
//            'image/*'         => 'DOCS_IMAGES',
//            'video/*'         => 'DOCS_VIDEOS',
//        ];

        /* The mimetypes can also be used to filter items in the picker itself
         * but the google picker needs them in a particular format.
         *
         * We currently support "images only" and "all files", basically, as two
         * "sets" of files from which we can choose
         *
         * TODO Not sure if addView() etc. are actually obsolete now...?
         *
         * FIXME These views only work if the $xpl split succeeds. The way it was originally
         *  written is not compatible with the heif/heic support and is actually broken,
         *  which causes google picker to show the My Drive appearance. If the regex
         *  succeeds, like in this commit, the DOCS_IMAGES view strictly shows you all
         *  the images, but this disregards any folder structure you may have had.
         *  imho those views aren't worth anything, so imma remove them next.
         *  This is why the === comparison failed when doing:  $mime === 'image/*'
         *  because it's been compounded with the heic/heif extensions.
         */
        foreach ($xpl as $mime) {
//            if (isset($map[$mime])) {
//                $this->addView($map[$mime]);
//            }

            /* The mimetypes can also be used to filter items in the picker itself
             * Note that heic/heif, in true apple fashion, have somewhat broken mimetype
             * interpretation and is hazardous. I'm not sure google even supports it.
             *
             * Anyway the google picker can't interpret image/*, you need to specify
             * all the individual mimes, because google reasons I suppose
             *
             * However, due to our intended support for heic/heif files, $mimeFilter
             * might actually be a combination of mime types and file extensions:
             *
             * like this: image/*,.heic,.heics,.heif
             *
             * which means we need to interpret image/* as a particular set of mimes
             * that google picker can comprehend...
             */
//            new \Alaress_Probe($mimeFilter, $xpl, $mime);

            if ('image/*' === $mime) {
                $this->mimeTypes[] = 'image/png,image/jpeg,image/gif,image/heic,image/heif';
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
                'API_KEY'                  => $this->gAppsApiKey,
                'CLIENT_ID'                => $this->gAppsClientId,
                'GOOGLE_ACCESS_TOKEN'      => $_SESSION['googleAccessToken'] ?? '',
                'GOOGLE_ACCESS_TOKEN_TYPE' => $_SESSION['googleAccessTokenType'] ?? '',
                'VIEWS'                    => implode(',', array_keys($this->views)),
                'FEATURES'                 => implode(',', array_keys($this->features)),
                'MIME_TYPES'               => implode(',', $this->mimeTypes)
            ]
        );
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
