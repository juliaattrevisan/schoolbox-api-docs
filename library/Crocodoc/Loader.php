<?php
/**
 * Session
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Crocodoc;

use Alaress_Curl_Url;
use Schoolbox\User;

class Loader
{

    /**
     * Contains API keys
     *
     * @var Authenticator
     */
    private $authenticator = null;

    /**
     * The user doing the marking
     *
     * @var User
     */
    private $user = null;

    /**
     * A unique identifier for a resource uploaded to Crocodoc
     *
     * @var string
     */
    private $uuid = '';

    /**
     * A group handler
     *
     * @var \Group
     */
    private $groupHandler = null;

    /**
     * Curl object
     *
     * @var Alaress_Curl_Url
     */
    private $curl = null;

    /**
     * Constructor
     *
     * @param $uuid
     * @param User $user
     */
    public function __construct($uuid, User $user)
    {
        $this->setAuthenticator(new Authenticator());
        $this->setUuid($uuid);
        $this->setUser($user);
        $this->setGroupHandler(new \Group());
        $this->setCurl(new Alaress_Curl_Url('https://crocodoc.com/api/v2/session/create'));
    }

    /**
     * Spawn an iframe
     */
    public function render()
    {

        /* Handle postfields different from student viewers and teacher/superuser viewers
         */
        if($this->getUser()->getRole()->isStudent()) {
            $postFields = "token={$this->getAuthenticator()->getApiToken()}&uuid={$this->getUuid()}&editable=false";
        }
        else if ($this->getUser()->getRole()->isStaff() || $this->getUser()->isSuperuser()) {
            $userTuple = "{$this->getUser()->getId()},{$this->getUser()->getUsername()}";
            $postFields = "token={$this->getAuthenticator()->getApiToken()}&uuid={$this->getUuid}&editable=true&user={$userTuple}";
        } else {
            $postFields = '';
        }

        /* Bicep curl
         */
        $this->getCurl()->addOptions(
            array(
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_POST => true,
            )
        );

        $this->getCurl()->exec();

        $result = json_decode($this->getCurl()->getResult());

        return json_encode(
            array(
                'url' => "https://crocodoc.com/view/{$result->session}",
                'errors' => $this->getCurl()->getErrors(),
                /* Pass this so that crocodoc uuid gets phplibbed upon
                 * first load in Inline Grader, for student view
                 */
                'uuid' => $this->getUuid()
            )
        );
    }

    /* Getters/Setters
     */

    /**
     * Set user
     *
     * @param User $user
     */
    private function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * Get user
     *
     * @return User
     */
    private function getUser()
    {
        return $this->user;
    }

    /**
     * Set uuid
     *
     * @param string $uuid
     */
    private function setUuid($uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * Get uuid
     *
     * @return string
     */
    private function getUuid()
    {
        return $this->uuid;
    }

    /**
     * Set authenticator
     *
     * @param Authenticator $authenticator
     */
    private function setAuthenticator($authenticator)
    {
        $this->authenticator = $authenticator;
    }

    /**
     * Get authenticator
     *
     * @return Authenticator
     */
    private function getAuthenticator()
    {
        return $this->authenticator;
    }

    /**
     * Set group handler
     *
     * @param \Group $groupHandler
     */
    private function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * Get group handler
     *
     * @return \Group
     */
    private function getGroupHandler()
    {
        return $this->groupHandler;
    }

    /**
     * Set curl
     *
     * @param Alaress_Curl_Url $curl
     */
    private function setCurl($curl)
    {
        $this->curl = $curl;
    }

    /**
     * Get curl
     *
     * @return Alaress_Curl_Url
     */
    private function getCurl()
    {
        return $this->curl;
    }
}