<?php
/**
 * TASS portal
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace External\Tass\Portal;

use Alaress;
use Aura\Web\Exception\InvalidStatusCode;

class Controller extends Alaress\Web\Controller\Base
{

    /**
     * Connection to SSO server
     *
     * @var SSO
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
     * Tass's identifier for Schoolbox to use their SSO mechanism, per Tass product.
     *
     * @var array|string[]
     */
    protected $appCodes = [
        'parentLounge' => '',
        'tassWeb'      => '',
        'teacherKiosk' => '',
        'webBook'      => '',
        'studentCafe'      => '',
    ];

    /**
     * Constructor
     *
     * @param SSO               $sso
     * @param string            $portalHost The URL on which TASS services are hosted
     * @param array|string[]    $appCodes
     */
    public function __construct(SSO $sso, $portalHost, $appCodes)
    {
        $this->sso        = $sso;
        $this->portalHost = $portalHost;
        $this->appCodes   = array_merge($this->appCodes, $appCodes);
    }


    /**
     * Generate an SSO token, and redirect to the specified Tass product
     *
     * @param string $productCode The code for the Tass product we wish to redirect to
     * @param string $appCode     The code by which Schoolbox is known to the specified Tass product
     * @param string $productPath The Tass portal path where the Tass product is made available
     * @param string $deepLink    Json-encoded parameters to indicate the Tass product page to deep link to
     */
    private function redirectToTassProduct(string $productCode, string $appCode, string $productPath, string $deepLink = '')
    {
        // Generate the SSO token request params
        $config = [
            'prod_code' => $productCode,
            'app_code'  => $appCode,
        ];
        if($deepLink){
            $config['deep_link'] = $deepLink;
        }

        // Send off the SSO token request
        try{
            $token = $this->getToken($deepLink);
        } catch (\UnexpectedValueException $e){
            $this->captureException($e);
            $token = '';
        }

        // If we successfully got a token, redirect the user to the Tass product URL
        if ($token) {
            $this->getResponse()->redirect->to("{$this->portalHost}{$productPath}?sso_token={$token}");
        } else {
            try{
                $this->getResponse()->status->setCode(404);
            } catch (InvalidStatusCode $e){
                $this->captureException($e);
                exit($e->getMessage());
            }
        }
    }

    /**
     * SSO to parent lounge
     */
    public function parentLounge()
    {
        $this->redirectToTassProduct('PL', $this->appCodes['parentLounge'], '/ParentLounge/PIALogin.cfm');
    }

    /**
     * SSO to TASSWeb
     */
    public function tassWeb()
    {
        $this->redirectToTassProduct('TASSWEB', $this->appCodes['tassWeb'], '/tassweb/login.cfm');
    }

    /**
     * SSO to teacher kiosk
     */
    public function teacherKiosk()
    {
        $deepLink = urlencode($this->getRequest()->query->get['link']);
        $this->redirectToTassProduct('TIA', $this->appCodes['teacherKiosk'], '/kiosk/TIALogin.cfm', $deepLink);
    }

    /**
     * SSO to web book
     */
    public function webBook()
    {
        $this->redirectToTassProduct('WB', $this->appCodes['webBook'], '/webbook/WBLogin.cfm');
    }

    /**
     * SSO to student cafe
     */
    public function studentCafe()
    {
        $this->redirectToTassProduct('SC', $this->appCodes['studentCafe'], '/studentcafe/login.cfm');
    }


    /**
     * Gets a token out of the SSO token fetcher.
     *
     * @param array $config
     *
     * @return string
     *
     * @throws \UnexpectedValueException
     */
    protected function getToken(array $config = []): string
    {
        if ($this->getUser()->getRole()->isParent() && \Cfg::get('external_tass_sso_parent_code') === "TASS ID") {
            // remove any non-numeric characters so it's the proper TASS id
            $ssoCode = preg_replace('/\D/', '', $this->getUser()->getExternalId());
        }
        else if ($this->getUser()->getRole()->isStaff() && \Cfg::get('external_tass_sso_staff_code') === "TASS ID") {
            $ssoCode = $this->getUser()->getExternalId();
        }
        else if ($this->getUser()->getRole()->isStudent() && \Cfg::get('external_tass_sso_student_code') === "TASS ID") {
            $ssoCode = $this->getUser()->getExternalId();
        } else {
            $ssoCode = urlencode($this->getUser()->getUsername());
        }

        $config['sso_code'] = $ssoCode;

        return $this->sso->getUserToken($config);
    }
}
