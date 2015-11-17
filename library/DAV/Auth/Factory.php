<?php
/**
 * Factory class for creating a DAV authenticator.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class DAV_Auth_Factory
{
    /**
     * @var Schoolbox\IConfigLoader
     */
    protected $config;

    public function __construct(Schoolbox\IConfigLoader $config)
    {
        $this->config = $config;
    }

    /**
     * @return Sabre\DAV\Auth\Backend\BackendInterface
     */
    public function build()
    {
        switch ($this->config->get('webdav_auth_type')) {
            case 'basic':
                $auth = new DAV_Auth_Basic();
                break;
            case 'digest':
            default:
                $auth = new DAV_Auth_Digest();
                break;
        }
        $auth->setRealm($this->config->get('site_location'));
        return $auth;
    }
}
