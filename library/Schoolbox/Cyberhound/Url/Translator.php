<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Cyberhound\Url;

use Zend\Uri\Uri;

/**
 * Translates a URL to a CyberHound URL
 *
 * @package Schoolbox\Cyberhound
 */
class Translator
{
    /**
     * The URL of the Cyberhound API.
     *
     * @var string
     */
    protected $apiUrl;

    /**
     * The shared key the Cyberhound API uses.
     *
     * @var string
     */
    protected $apiSharedKey;

    /**
     * Translator constructor.
     *
     * @param string $apiUrl
     * @param string $apiSharedKey
     */
    public function __construct($apiUrl, $apiSharedKey)
    {
        $this->apiUrl       = $apiUrl;
        $this->apiSharedKey = $apiSharedKey;
    }

    /**
     * Translates a URL to a CyberHound one.
     *
     * If the API URL or shared key aren't set, or this is a relative link,
     * just return the same URL passed in.
     *
     * @param  string $url
     * @return string
     */
    public function translate($url)
    {
        if ($this->apiSharedKey && $this->apiUrl) {
            $urlObject = new Uri($url);
            if (!$urlObject->isValidRelative()) {
                // If using Cyberhound: redirect to its proxy rather than the URL directly.
                // Only redirect for non-local links though.
                $cyberHoundUrl = new Uri($this->apiUrl);
                $cyberHoundUrl
                    ->setPath('/api/overrideurl')
                    ->setQuery([
                        'key'  => hash('sha256', $_SERVER['REQUEST_TIME'].base64_encode($url).$this->apiSharedKey),
                        'time' => $_SERVER['REQUEST_TIME'],
                        'base64url'  => base64_encode($url),
                    ]);
                $url = $cyberHoundUrl->toString();
            }
        }
        return $url;
    }
}
