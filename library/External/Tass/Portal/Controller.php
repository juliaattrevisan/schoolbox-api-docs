<?php
/**
 * TASS portal
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace External\Tass\Portal;

use Alaress;
use function array_merge;
use Aura\Web\Exception\InvalidStatusCode;
use Alaress\Session\ImpersonationCheck;
use function json_encode;
use function rtrim;
use Schoolbox\User;
use Schoolbox\Error;

class Controller extends Alaress\Web\Controller\Base
{

    /**
     * Connection to SSO server
     *
     * @var SSO
     */
    protected $sso;

    /**
     * The URL on which TASS services are hosted, no trailing slash
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

    /** @var Error\Mapper */
    private $errorMapper;

    /**
     * Constructor
     *
     * @param SSO            $sso
     * @param string         $portalHost The URL on which TASS services are hosted
     * @param array|string[] $appCodes
     * @param Error\Mapper   $errorMapper
     */
    public function __construct(SSO $sso, $portalHost, $appCodes, Error\Mapper $errorMapper)
    {
        $this->sso         = $sso;
        $this->portalHost  = rtrim($portalHost, '/');
        $this->appCodes    = array_merge($this->appCodes, $appCodes);
        $this->errorMapper = $errorMapper;
    }


    /**
     * Generate an SSO token, and redirect to the specified Tass product
     *
     * @param string     $productCode The code for the Tass product we wish to redirect to
     * @param string     $appCode     The code by which Schoolbox is known to the specified Tass product
     * @param string     $productPath The Tass portal path where the Tass product is made available
     * @param array      $deepLinkV2  Injected when generating a SSO v2 token
     * @param bool       $prodMenu    Should Tass render the product menu? Null for default behaviour
     * @param null|array $deepLinkV1  Injected when generating a SSO v1 token
     * @throws InvalidStatusCode If unable to generate a token and unable to set appropriate error code
     * @throws \Schoolbox\Application\Exception\Exception If attempting to SSO as an impersonated user
     */
    private function redirectToTassProduct(string $productCode, string $appCode, string $productPath, array $deepLinkV2, bool $prodMenu, array $deepLinkV1 = null)
    {
        // We don't permit SSO of impersonated users into external systems
        if ($this->isCurrentUserImpersonated()) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_NO_SSO_IMPERSONATION);
        }

        if ((int)$this->getConfigLoader()->get('external_tass_sso_token_generation_strategy') === SSO::SSO_STRATEGY_V3_SAML) {
            // Redirect to SAML supporting TASS url
            $samlDeepLink = json_encode(array_merge($deepLinkV2, ['prod_menu' => $prodMenu ? 'Y' : 'N']));
            $this->getResponse()->redirect->to("{$this->portalHost}{$productPath}?go={$samlDeepLink}");
            return;
        }

        // Generate the SSO token request params
        $config = [
            'prod_code' => $productCode,
            'app_code'  => $appCode,
            'sso_code'  => $this->getSsoCode(),
        ];

        // Send off the SSO token request
        try {
            $token = $this->sso->getUserToken($config, $deepLinkV2, $prodMenu, $deepLinkV1);
        } catch (SsoTokenGenerationException|\RuntimeException $e) {
            throw $this->errorMapper->generateException(Error\Mapper::ERROR_TASS_SSO_FAILURE, $e);
        }

        // If we successfully got a token, redirect the user to the Tass product URL
        if ($token) {
            $this->getResponse()->redirect->to("{$this->portalHost}{$productPath}?sso_token={$token}");
        } else {
            $this->getResponse()->status->setCode(404);
        }
    }

    /**
     * SSO to parent lounge
     * @throws InvalidStatusCode
     * @throws \Schoolbox\Application\Exception\Exception If attempting to SSO as an impersonated user
     */
    public function parentLounge()
    {
        $this->redirectToTassProduct('PL', $this->appCodes['parentLounge'], '/ParentLounge/PIALogin.cfm', $this->getDeepLinkV2(), $this->getProdMenu());
    }

    /**
     * SSO to TASSWeb
     * @throws InvalidStatusCode
     * @throws \Schoolbox\Application\Exception\Exception If attempting to SSO as an impersonated user
     */
    public function tassWeb()
    {
        $this->redirectToTassProduct('TASSWEB', $this->appCodes['tassWeb'], '/tassweb/login.cfm', $this->getDeepLinkV2(), $this->getProdMenu());
    }


    /**
     * SSO to teacher kiosk
     *
     * @throws InvalidStatusCode
     * @throws \Schoolbox\Application\Exception\Exception If attempting to SSO as an impersonated user
     */
    public function teacherKiosk()
    {
        $this->redirectToTassProduct('TIA', $this->appCodes['teacherKiosk'], '/kiosk/TIALogin.cfm', $this->getDeepLinkV2(), $this->getProdMenu());
    }


    /**
     * Redirect to the student profile page in TASS's teacher kiosk
     *
     * @param User $student
     * @throws InvalidStatusCode
     * @throws \Schoolbox\Application\Exception\Exception If attempting to SSO as an impersonated user
     */
    public function studentProfile(User $student): void
    {
        $deepLinkV2 = [
            'target'   => 'student.profile',
            'studcode' => $student->getExternalId(),
        ];

        $deepLinkV1 = [
            'do'        => 'kiosk.students.main.student.details',
            'stud_code' => $student->getExternalId(),
        ];
        $this->redirectToTassProduct('TIA', $this->appCodes['teacherKiosk'], '/kiosk/TIALogin.cfm', $deepLinkV2, true, $deepLinkV1);
    }


    /**
     * SSO to web book
     * @throws InvalidStatusCode
     * @throws \Schoolbox\Application\Exception\Exception If attempting to SSO as an impersonated user
     */
    public function webBook()
    {
        $this->redirectToTassProduct('WB', $this->appCodes['webBook'], '/webbook/WBLogin.cfm', $this->getDeepLinkV2(), $this->getProdMenu());
    }

    /**
     * SSO to student cafe
     * @throws InvalidStatusCode
     * @throws \Schoolbox\Application\Exception\Exception If attempting to SSO as an impersonated user
     */
    public function studentCafe()
    {
        $this->redirectToTassProduct('SC', $this->appCodes['studentCafe'], '/studentcafe/login.cfm', $this->getDeepLinkV2(), $this->getProdMenu());
    }


    /**
     * Returns the current user's SSO Code to identify them to the Tass SSO server
     *
     * @return string
     */
    private function getSsoCode(): string
    {
        if ($this->getUser()->getRole()->isParent() && \Config::get('external_tass_sso_parent_code') === "TASS ID") {
            // remove any non-numeric characters so it's the proper TASS id
            return preg_replace('/\D/', '', $this->getUser()->getExternalId());
        }
        if ($this->getUser()->getRole()->isStaff() && \Config::get('external_tass_sso_staff_code') === "TASS ID") {
            return $this->getUser()->getExternalId();
        }
        if ($this->getUser()->getRole()->isStudent() && \Config::get('external_tass_sso_student_code') === "TASS ID") {
            return $this->getUser()->getExternalId();
        }
        return $this->getUser()->getUsername();
    }



    /**
     * Gets deep link args from SB redirect link for passing off to TASS SSOv2
     *
     * @return string[]
     */
    private function getDeepLinkV2(): array
    {
        parse_str($this->getRequest()->query->get('deep_link', ''), $deepLink);
        return $deepLink;
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
        if (\in_array($prodMenu, [false, 0, '0', 'n', 'N'], true)) {
            return false;
        }
        return true;
    }


    /**
     * Returns whether the current session user is being impersonated (but not merely linked)
     *
     * @return bool
     */
    private function isCurrentUserImpersonated(): bool
    {
        return (new ImpersonationCheck())->isImpersonated($this->getUser(), $this->getSession()->get('previous'));
    }
}
