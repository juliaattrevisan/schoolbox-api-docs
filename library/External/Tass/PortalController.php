<?php
/**
 * TASS portal
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Tass_PortalController extends Alaress\Web\Controller\Base
{
    /**
     * Connection to SSO server
     *
     * @var External_Tass_SSO
     */
    protected $sso;

    /**
     * The URL on which TASS services are hosted
     *
     * @var string
     */
    protected $portalHost;

    /**
     * Application codes
     *
     * @var array|string[]
     */
    protected $appCodes = [
        'parentLounge' => '',
        'tassWeb'      => '',
        'teacherKiosk' => '',
        'webBook'      => '',
    ];

    /**
     * Constructor
     *
     * @param External_Tass_SSO $sso
     * @param string            $portalHost The URL on which TASS services are hosted
     * @param array|string[]    $appCodes
     */
    public function __construct(External_Tass_SSO $sso, $portalHost, $appCodes)
    {
        $this->sso        = $sso;
        $this->portalHost = $portalHost;
        $this->appCodes   = array_merge($this->appCodes, $appCodes);
    }

    /**
     * SSO to parent lounge
     */
    public function parentLounge()
    {
        $token = $this->getToken([
            'prod_code' => 'PL',
            'app_code'  => $this->appCodes['parentLounge'],
        ]);
        if ($token) {
            $this->getResponse()->redirect->to(
                $this->portalHost."/ParentLounge/PIALogin.cfm?sso_token=$token"
            );
        } else {
            $this->getResponse()->status->setCode(404);
        }
    }

    /**
     * SSO to TASSWeb
     */
    public function tassWeb()
    {
        $token = $this->getToken([
            'prod_code' => 'TASSWEB',
            'app_code'  => $this->appCodes['tassWeb'],
        ]);
        if ($token) {
            $this->getResponse()->redirect->to(
                $this->portalHost."/tassweb/login.cfm?sso_token=$token"
            );
        } else {
            $this->getResponse()->status->setCode(404);
        }
    }

    /**
     * SSO to teacher kiosk
     */
    public function teacherKiosk()
    {
        $token = $this->getToken([
            'prod_code' => 'TIA',
            'app_code'  => $this->appCodes['teacherKiosk'],
        ]);
        if ($token) {
            $this->getResponse()->redirect->to(
                $this->portalHost."/kiosk/TIALogin.cfm?sso_token=$token"
            );
        } else {
            $this->getResponse()->status->setCode(404);
        }
    }

    /**
     * SSO to web book
     */
    public function webBook()
    {
        $token = $this->getToken([
            'prod_code' => 'WB',
            'app_code'  => $this->appCodes['webBook'],
        ]);
        if ($token) {
            $this->getResponse()->redirect->to(
                $this->portalHost."/webbook/WBLogin.cfm?sso_token=$token"
            );
        } else {
            $this->getResponse()->status->setCode(404);
        }
    }

    /**
     * Gets a token out of the SSO token fetcher.
     *
     * @param array $config
     *
     * @return string
     */
    protected function getToken($config = [])
    {
        if ($this->getUser()->getRole()->isParent()) {
            // remove any non-numeric characters so it's the proper TASS id
            $ssoCode = preg_replace('/[^0-9]/s', '', $this->getUser()->getExternalId());
        } else {
            $ssoCode = $this->getUser()->getUsername();
        }
        $config['sso_code'] = $ssoCode;
        return $this->sso->getUserToken($config);
    }
}
