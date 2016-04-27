<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * Extension of SabreDAV client for Schoolbox purposes
 */
class DAV_ExtendedClient extends Sabre\DAV\Client
{
    /**
     * Uploads a file via a PUT request
     *
     * @param string          $path Destination path on the server
     * @param string|resource $file Path to source file, or a file resource
     *
     * @return array
     */
    public function put($path, $file)
    {
        $absolutePath = $this->getAbsoluteUrl($path);
        $request      = new Sabre\HTTP\Request('PUT', $absolutePath);
        $curl         = curl_init($absolutePath);
        $request->setBody($file);

        $settings = $this->createCurlSettingsArray($request);
        $settings[CURLOPT_HTTPAUTH]   = Cfg::get('webdav_allow_any_auth') ? CURLAUTH_ANY : CURLAUTH_BASIC;
        $settings[CURLOPT_COOKIEFILE] = '';
        if (Cfg::get('webdav_allow_insecure')) {
            $settings[CURLOPT_SSL_VERIFYHOST] = false;
            $settings[CURLOPT_SSL_VERIFYPEER] = false;
        }
        curl_setopt_array($curl, $settings);

        return array(
            curl_exec($curl),
            curl_getinfo($curl),
            curl_errno($curl),
            curl_error($curl)
        );
    }

    /**
     * Moves/renames a file or directory
     *
     * @param string $path Source path
     * @param string $destination Destination path
     *
     * @return array
     */
    public function move($path, $destination)
    {
        return $this->request('MOVE', $path, null, array(
            'Destination' => $this->getAbsoluteUrl($destination)));
    }
}
