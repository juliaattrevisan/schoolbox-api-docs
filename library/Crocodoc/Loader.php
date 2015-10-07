<?php
/**
 * Creates a session with crocodoc and returns the URL for the document in that session
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
        $this->setCurl(new Alaress_Curl_Url('https://crocodoc.com/api/v2/session/create'));
    }

    /**
     * Setups up session with crocodoc and returns the URL to access the crocodoc document for that iframe
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
            $postFields = "token={$this->getAuthenticator()->getApiToken()}&uuid={$this->getUuid()}&editable=true&user={$userTuple}";
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

        if($this->getCurl()->getErrors()) {
            throw new \Exception($this->getCurl()->getErrors());
        }
        else if(isset($result->error)) {
            throw new \Exception($result->error);
        }
        else {
            return "https://crocodoc.com/view/{$result->session}";
        }
    }

    /**
     * Just gets whatever the Crocodoc download URL is for this UUID.
     *
     * @param string $filename If provided, download with this filename
     * @return string
     */
    public function getDownloadUrl($filename = null)
    {
        $parts = [
            'token'     => $this->getAuthenticator()->getApiToken(),
            'uuid'      => $this->getUuid(),
            'annotated' => 'true',
        ];
        if ($filename) {
            $parts['filename'] = $filename;
        }
        return "https://crocodoc.com/api/v2/download/document?".http_build_query($parts);
    }

    /* Getters/Setters
     */

    /**
     * Set user
     *
     * @param User $user
     */
    private function setUser(User $user)
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
    private function setAuthenticator(Authenticator $authenticator)
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
     * Set curl
     *
     * @param Alaress_Curl_Url $curl
     */
    private function setCurl(Alaress_Curl_Url $curl)
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