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
     * @param string    $productCode The code for the Tass product we wish to redirect to
     * @param string    $appCode     The code by which Schoolbox is known to the specified Tass product
     * @param string    $productPath The Tass portal path where the Tass product is made available
     * @param array     $deepLink    Parameters to indicate the Tass product page to deep link to
     * @param bool      $prodMenu    Should Tass render the product menu? Null for default behaviour
     */
    private function redirectToTassProduct(string $productCode, string $appCode, string $productPath, array $deepLink, bool $prodMenu)
    {
        // Generate the SSO token request params
        $config = [
            'prod_code' => $productCode,
            'app_code'  => $appCode,
            'sso_code'  => $this->getSsoCode(),
        ];

        // Send off the SSO token request
        try{
            //        $config['sso_code'] = $ssoCode;
            //        $config['sso_code'] = '10253';
            $config['sso_code'] = 'chapmanj'; // teacher

            $token = $this->sso->getUserToken($config, $deepLink, $prodMenu);
        } catch (SsoTokenGenerationException|\RuntimeException $e){
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
        $this->redirectToTassProduct('PL', $this->appCodes['parentLounge'], '/ParentLounge/PIALogin.cfm', $this->getDeepLink(), $this->getProdMenu());
    }

    /**
     * SSO to TASSWeb
     */
    public function tassWeb()
    {
        $this->redirectToTassProduct('TASSWEB', $this->appCodes['tassWeb'], '/tassweb/login.cfm', $this->getDeepLink(), $this->getProdMenu());
    }

    /**
     * SSO to teacher kiosk
     */
    public function teacherKiosk()
    {
//        $deepLink = $this->getDeepLink();
//        $deepLink = ['target' => 'student.profile', 'studcode' => 'GRIK150'];
        $deepLink = [
            'target'      => 'teacher.rollcall',
            'ttid'        => '22',
            'yeargroups'  => '',
            'classes'     => '1192',
            'tchcode'     => 'CHJ',
            'subjectcode' => '2',
            'date'        => '22/03/2018',
            'periodcode'  => '2',
            'daycode'     => '9',
        ];
        // tt_id=22&classes=1192&yeargroupsandclasses=|1192&bfromtimetable=true&currenttchcode=CHJ&mode=Attend&subjectcode=2&ttdate=22/03/2018&periodcode=2&dayCode=9&tchCode=CHJ

        $this->redirectToTassProduct('TIA', $this->appCodes['teacherKiosk'], '/kiosk/TIALogin.cfm', $deepLink, $this->getProdMenu());
    }

    /**
     * SSO to web book
     */
    public function webBook()
    {
        $this->redirectToTassProduct('WB', $this->appCodes['webBook'], '/webbook/WBLogin.cfm', $this->getDeepLink(), $this->getProdMenu());
    }

    /**
     * SSO to student cafe
     */
    public function studentCafe()
    {
        $this->redirectToTassProduct('SC', $this->appCodes['studentCafe'], '/studentcafe/login.cfm', $this->getDeepLink(), $this->getProdMenu());
    }


    /**
     * Returns the current user's SSO Code to identify them to the Tass SSO server
     *
     * @return string
     */
    private function getSsoCode(): string
    {
        if ($this->getUser()->getRole()->isParent() && \Cfg::get('external_tass_sso_parent_code') === "TASS ID") {
            // remove any non-numeric characters so it's the proper TASS id
            return preg_replace('/\D/', '', $this->getUser()->getExternalId());
        }
        if ($this->getUser()->getRole()->isStaff() && \Cfg::get('external_tass_sso_staff_code') === "TASS ID") {
            return $this->getUser()->getExternalId();
        }
        if ($this->getUser()->getRole()->isStudent() && \Cfg::get('external_tass_sso_student_code') === "TASS ID") {
            return $this->getUser()->getExternalId();
        }
        return urlencode($this->getUser()->getUsername());
    }



    /**
     * Gets deep link args from SB redirect link for passing off to TASS SSOv2
     *
     * @return string[]
     */
    private function getDeepLink(): array
    {
        $deepLink      = $this->getRequest()->query->get('deep_link', '');
        $deepLinkArray = [];

        foreach (explode('&', $deepLink) as $arg) {
            [$key, $val] = explode('=', $arg, 2);
            $deepLinkArray[(string)$key] = (string)$val;
        }
        return $deepLinkArray;
    }


    /**
     * Gets whether the SB link indicates that we want Tass to render their product menu after redirect.
     *
     * Defaults to true if not specified
     *
     * @return bool
     */
    private function getProdMenu(): bool
    {
        $prodMenu = $this->getRequest()->query->get('prod_menu', true);
        if(\in_array($prodMenu, [false, 0, '0', 'n', 'N'], true)){
            return false;
        }
        return true;
    }
}
