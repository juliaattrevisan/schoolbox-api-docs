<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Cyberhound\Url;

use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Symfony\Component\HttpFoundation\IpUtils;
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
     * The IP of the user, we only use API if internal IP
     *
     * @var string
     */
    protected $ip;

    /**
     * List of CIDR ranges that can access the API
     * @var array
     */
    protected $whitelist;

    /**
     * Translator constructor.
     *
     * @param string $ip
     * @param string $apiUrl
     * @param string $apiSharedKey
     */
    public function __construct($ip, $apiUrl, $apiSharedKey)
    {
        $this->ip           = $ip;
        $this->apiUrl       = $apiUrl;
        $this->apiSharedKey = $apiSharedKey;

        $config = ConfigLoader::getInstance();
        $whitelist = $config->get('cyberhound_whitelist');

        $this->whitelist = array_filter(explode("\n", $whitelist));
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

            /* Test if user is local before using API */
            $local = false;
            foreach($this->whitelist as $range) {
                if(IpUtils::checkIp($this->ip, $range)) {
                    $local = true;
                    break;
                }
            }
            if(!$local) {
                return $url;
            }

            try {
                $urlObject = new Uri($url);
                if (!$urlObject->isValidRelative()) {
                    // If using Cyberhound: redirect to its proxy rather than the URL directly.
                    // Only redirect for non-local links though.

                    $cyberHoundUrl = new Uri($this->apiUrl);
                    $cyberHoundUrl
                        ->setPath('/api/overrideurl')
                        ->setQuery([
                            'key'       => hash('sha256', $_SERVER['REQUEST_TIME'] . $url . $this->apiSharedKey),
                            'time'      => $_SERVER['REQUEST_TIME'],
                            'base64url' => base64_encode($url),
                        ]);
                    $url = $cyberHoundUrl->toString();
                }
            }
            catch (\InvalidArgumentException $e) {
                return $url;
            }
        }
        return $url;
    }
}
