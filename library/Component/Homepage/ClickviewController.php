<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Psr\SimpleCache\CacheInterface;
use Schoolbox\Permission\Access\UserAccess;

class Component_Homepage_ClickviewController extends Component_Homepage_ControllerAbstract
{
    /**
     * Default: cache URL responses for 1 hour
     */
    private const CLICKVIEW_URL_CACHE_DEFAULT_TTL = 3600;

    /**
     * Where to cache the Clickview URL responses
     * %s the md5 hash of the original URL we sent to Clickview
     */
    private const CLICKVIEW_URL_CACHE_KEY = 'clickview_url_%s';

    /* Pattern match the necessary code out of a url given to us
    * by the Clickview web interface
    *
    * http://webplayer.clickview.com.au/?p=kwRu&v1=home.clickview.com.au
    */
    private const PATTERN = '/(\?|&)p=([a-zA-Z0-9#]+)/';

    /* New version of clickview has links in this format
    * http://online.clickview.com.au/MyLibrary/Play?Id=21b30fcc-e2f3-e28e-5ac9-6920d15d98b6&m=False
    */

    private const PATTERN_ONLINE = '/(\?|&)Id=([a-zA-Z0-9\-]+)/';


    /* Latest version now provides iframe embedding but uses a masking URL, so annoying
    * http://clickv.ie/w/-eFa
    * Also can be embed code with the following URL
    * <iframe frameborder="0"
    *  allowfullscreen webkitallowfullscreen mozallowfullscreen
    *  width="640" height="360"
    *  src="http://online.clickview.com.au/Share/PlayEmbed?shareCode=b207b279&a=false"
    * ></iframe>
    */
    private const PATTERN_REDIR  = '/clickv\.ie\/w\/[a-zA-Z0-9\-]+(?:(?:\?t=([0-9]+))?(?:\&et=([0-9]+))?)?/';
    private const PATTERN_IFRAME = '/(\?|&)shareCode=([a-zA-Z0-9\-]+)/i';

    /* For handling the clickview.net links
        * The possible formats are:
        * - https://www.clickview.net/share/b9OaVD
        * - https://www.clickview.net/share/embed/b9OaVD
        *
        * These can also come from a redirect after a clickv.ie link
        */
    private const PATTERN_NET = '/clickview\.net\/share(?:\/embed)?\/([a-zA-Z0-9\-]+)/';

    /**
     * Renders the data into the SitePage. Presentation logic.
     * @param string $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        if (!parent::loadTemplate($templateHandle, 'clickview')) {
            return false;
        }
        $this->parseConfig();

        $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
        $this->setBlock($templateHandle, 'has_clickview', 'has_clickview_block');
        $this->setBlock($templateHandle, 'online_clickview', 'online_clickview_block');
        $this->setBlock($templateHandle, 'iframe_clickview', 'iframe_clickview_block');

        $clickviewSchoolId = Config::get('clickview_school_id');
        $this->setVar('CLICKVIEW_SCHOOL_ID', $clickviewSchoolId);

        // Clickview has different endpoints in NZ to Aus.  No idea if this affects any other countries.
        if (stripos($this->getConfigLoader()->get('date_timezone'), 'Auckland') !== false) {
            $this->setVar('CLICKVIEW_URL', 'online.clickview.co.nz');
        } else {
            $this->setVar('CLICKVIEW_URL', 'online.clickview.com.au');
        }

        $showEmpty    = false;
        $emptyInvalid = false;

        if (!strlen(trim($this->getConfig('url')))) {
            $showEmpty = true;
        } else {
            $this->setVar(
                [
                    'empty_state_block'      => '',
                    'has_clickview_block'    => '',
                    'online_clickview_block' => '',
                    'iframe_clickview_block' => '',
                    'HIDE_CLICKVIEW'         => '',
                ]
            );

            $matches = [];

            $this->setVar(
                [
                    'START' => '',
                    'END'   => '',
                ]
            );

            if (preg_match(self::PATTERN, $this->getConfig('url'), $matches)) {
                $this->setVar('VIDEO_ID', $matches[2]);
                $this->parse('has_clickview_block', 'has_clickview');
            } elseif (preg_match(self::PATTERN_ONLINE, $this->getConfig('url'), $matches)) {
                $this->setVar('VIDEO_ID', $matches[2]);
                $this->parse('online_clickview_block', 'online_clickview');
            } elseif (preg_match(self::PATTERN_IFRAME, $this->getConfig('url'), $matches)) {
                // online.clickview.com.au uses a shareCode parameter in the URL.
                // As this regex matches any URL with a shareCode parameter, use the shareCode to build our own ClickView embed URL
                $this->setVar('CLICKVIEW_URL', $this->getVar('CLICKVIEW_URL') . '/share/embed?a=false&shareCode=' . $matches[2]);
                $this->parse('iframe_clickview_block', 'iframe_clickview');
            } elseif (preg_match(self::PATTERN_NET, $this->getConfig('url'), $matches)) {
                $this->setVar('CLICKVIEW_URL', '//clickview.net/share/embed/' . $matches[1]);
                $this->parse('iframe_clickview_block', 'iframe_clickview');
            } elseif (preg_match(self::PATTERN_REDIR, $this->getConfig('url'), $matches)) {
                // https://clickv.ie/w/idGe

                /** @var CacheInterface $cache */
                $cache = $this->getSitePage()->getDi()->get('component.homepage.clickview.cache');

                $iframeUrl = $cache->get(sprintf(self::CLICKVIEW_URL_CACHE_KEY, md5($this->getConfig('url'))));

                // If we don't have the iframe URL cached, then we need to fetch it
                // This is cached to avoid repeated requests to ClickView when the same URL is used unnecessarily
                if ($iframeUrl === null) {
                    $iframeUrl = $this->fetchClickviewRedirectUrl($this->getConfig('url'));

                    // Did we manage to get a valid iframe URL? If so, cache it
                    // If we didn't, the URL may have expired or is actually invalid
                    if ($iframeUrl !== null) {
                        $cache->set(
                            sprintf(self::CLICKVIEW_URL_CACHE_KEY, md5($this->getConfig('url'))),
                            $iframeUrl,
                            self::CLICKVIEW_URL_CACHE_DEFAULT_TTL
                        );
                    }
                }

                // If we have a cached URL, then we can use it
                if ($iframeUrl !== null) {
                    $this->setVar('CLICKVIEW_URL', $iframeUrl);
                    $this->parse('iframe_clickview_block', 'iframe_clickview');
                } else {
                    $showEmpty = true;
                    $emptyInvalid = true;
                }
            } else {
                $showEmpty    = true;
                $emptyInvalid = true;
            }
        }

        if ($showEmpty) {
            $this->setBlock($templateHandle, 'empty_state', 'empty_state_block');
            $this->setBlock('empty_state', 'empty_config', 'empty_config_block');
            $this->setBlock('empty_state', 'empty_invalid', 'empty_invalid_block');

            $this->setVar(
                [
                    'has_clickview'       => '',
                    'online_clickview'    => '',
                    'iframe_clickview'    => '',
                    'empty_config_block'  => '',
                    'empty_invalid_block' => '',
                    'HIDE_CLICKVIEW'      => 'hide',
                ]
            );

            if ($this->getContentPermission() < UserAccess::ACCESS_WRITE) {
                return false;
            } else {
                if ($emptyInvalid) {
                    $this->parse('empty_invalid_block', 'empty_invalid');
                } else {
                    $this->parse('empty_config_block', 'empty_config');
                }
            }

            $this->parse('empty_state_block', 'empty_state');
        }

        return true; // everything went according to plan
    }

    /**
     * For a given clickv.ie URL, fetch the URL we need to embed the video
     *
     * Why fetch and not directly iframe? Sometimes the link clickview redirects to may not be an embeddable link, for instance:
     *  https://clickv.ie/w/FP4x will route to https://online.clickview.com.au/share?sharecode=94f3eda7
     * The above link is a whole interactive page, so we need to use the sharecode to make our own embeddable link.
     * ClickView's Content Security Policy also prevents us from embedding their pages directly as they have set frame-ancestors to 'none'
     *
     * @param string $url A clickv.ie URL matching self::PATTERN_REDIR
     * @return string|null The embeddable iframe URL, or null if the request failed/if the URL is invalid
     */
    private function fetchClickviewRedirectUrl($url): ?string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getConfig('url'));
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER["HTTP_USER_AGENT"]);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        $curlinfo = curl_getinfo($ch);

        // Did the request fail?
        if ($curlinfo === false) {
            return null;
        }

        // Have we found a valid clickview.net URL?
        $matchesNew = [];
        if (preg_match(self::PATTERN_NET, $curlinfo['redirect_url'], $matchesNew)) {
            return '//clickview.net/share/embed/' . $matchesNew[1];
        }

        // Otherwise, we'll need to parse the URL to get the sharecode

        // Get the query params from the redirect URL, noting that there may be no query at all
        // If there is no sharecode parameter, then the URL is invalid
        parse_str(parse_url($curlinfo['redirect_url'], PHP_URL_QUERY) ?? '', $parts);
        $parts = array_change_key_case($parts, CASE_LOWER);
        if (isset($parts['sharecode'])) {
            $iframeUrl = $this->getVar('CLICKVIEW_URL') . '/share/embed?a=false&shareCode=' . $parts['sharecode'];

            // Pass in start/end time parameters, if the original URL had them
            // e.g. https://clickv.ie/w/idGe?t=305&et=408&i=2

            $matches = [];
            if (preg_match(self::PATTERN_REDIR, $url, $matches)) {
                if (count($matches) == 3) {
                    $iframeUrl .= "&t={$matches[1]}&et={$matches[2]}";
                }
            }

            return $iframeUrl;
        }

        return null;
    }
}
