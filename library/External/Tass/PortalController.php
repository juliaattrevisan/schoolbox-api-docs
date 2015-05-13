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
        $sso = new External_Tass_SSO();

        // remove any non-numeric characters so it's the proper TASS id
        $tassID = preg_replace('/[^0-9]/s', '', $_SESSION['sid']);

        $token = $sso->getUserToken(array('sso_code' => $tassID));

        // regardless of what happens, redirect the user somewhere
        if ($token) {
            // hostname will be configurable
            $this->getResponse()->redirect->to(
                "https://tassportal.cathedral.qld.edu.au/ParentLounge/PIALogin.cfm?sso_token=$token"
            );
        } else {
            $this->getResponse()->status->setCode(404);
        }
    }
}
