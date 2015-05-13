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
     * SSO to parent lounge
     */
    public function parentLounge()
    {
        $token = $this->getToken();
        if ($token) {
            $this->getResponse()->redirect->to(
                $this->getTassPortalHost()."/ParentLounge/PIALogin.cfm?sso_token=$token"
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
        $token = $this->getToken(['prod_code' => 'TASSWEB', 'app_code' => 'ec']);
        if ($token) {
            $this->getResponse()->redirect->to(
                $this->getTassPortalHost()."/tassweb/login.cfm?sso_token=$token"
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
        $token = $this->getToken(['prod_code' => 'TIA', 'app_code' => 'ec']);
        if ($token) {
            $this->getResponse()->redirect->to(
                $this->getTassPortalHost()."/kiosk/TIALogin.cfm?sso_token=$token"
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
        $token = $this->getToken(['prod_code' => 'WB', 'app_code' => 'ec']);
        if ($token) {
            $this->getResponse()->redirect->to(
                $this->getTassPortalHost()."/webbook/WBLogin.cfm?sso_token=$token"
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
        $sso = new External_Tass_SSO($config);
        // remove any non-numeric characters so it's the proper TASS id
        $tassID = preg_replace('/[^0-9]/s', '', $_SESSION['sid']);
        return $sso->getUserToken(array('sso_code' => $tassID));
    }

    /**
     * Gets the TASS portal host, will eventually be configurable
     *
     * @return string
     */
    protected function getTassPortalHost()
    {
        return "https://tassportal.cathedral.qld.edu.au/";
    }

}
