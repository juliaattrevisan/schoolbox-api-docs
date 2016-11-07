<?php
/**
 * Creates a session with crocodoc and returns the URL for the document in that session
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\InlineGrader;

use \Alaress_Curl_Url;
use Schoolbox\Permission\Access\UserAccess;
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
     * Is this work archived?
     *
     * @var bool
     */
    private $archived = false;

    /**
     * Crocodoc location for starting a session
     *
     * @var string
     */
    private $crocodocUrl = 'https://crocodoc.com/api/v2/session/create';

    /**
     * Crocodoc location for viewing a file
     * 
     * @var string
     */
    private $crocodocViewUrl = 'https://crocodoc.com/view';

    /**
     * Crocodoc location for downloading an annotated file
     * 
     * @var string
     */
    private $crocodocDownloadUrl = 'https://crocodoc.com/api/v2/download/document';

    /**
     * Constructor
     *
     * @param Authenticator
     * @param $uuid
     * @param User $user
     * @param bool $archived
     */
    public function __construct(Authenticator $authenticator, $uuid, User $user, $archived = false)
    {
        $this->setAuthenticator($authenticator);
        $this->setUuid($uuid);
        $this->setUser($user);
        $this->setArchived($archived);
        $this->setCurl(new Alaress_Curl_Url($this->getCrocodocUrl()));
    }

    /**
     * Setups up session with crocodoc and returns the URL to access the crocodoc document for that iframe
     */
    public function annotate()
    {
        $access = UserAccess::ACCESS_DENY;
        if ($this->getUser()->isSuperuser()) {
            $access = UserAccess::ACCESS_WRITE;
        } elseif ($this->getUser()->getRole()->isStaff()) {
            $access = $this->getArchived()
                ? UserAccess::ACCESS_READ
                : UserAccess::ACCESS_WRITE;
        } elseif ($this->getUser()->getRole()->isStudent() || $this->getUser()->getRole()->isParent()) {
            $access = UserAccess::ACCESS_READ;
        }

        $postFields = '';
        switch ($access) {
            case UserAccess::ACCESS_WRITE:
                $userTuple = "{$this->getUser()->getId()},{$this->getUser()->getUsername()}";
                $postFields = "token={$this->getAuthenticator()->getApiToken()}&uuid={$this->getUuid()}&editable=true&user={$userTuple}";
                break;
            case UserAccess::ACCESS_READ:
                $postFields = "token={$this->getAuthenticator()->getApiToken()}&uuid={$this->getUuid()}&editable=false";
                break;
            default:
                break;
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

        if ($this->getCurl()->getErrors()) {
            throw new \DomainException(implode(', ', $this->getCurl()->getErrors()));
        }
        elseif (isset($result->error)) {
            throw new \DomainException($result->error);
        }
        else {
            return "{$this->getCrocodocViewUrl()}/{$result->session}";
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

        $queryString = http_build_query($parts);

        return "{$this->getCrocodocDownloadUrl()}?{$queryString}";
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
     * @return $this
     */
    private function setUuid($uuid)
    {
        $this->uuid = $uuid;
        return $this;
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
     * @return $this
     */
    private function setAuthenticator(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;
        return $this;
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
     * @return $this
     */
    private function setCurl(Alaress_Curl_Url $curl)
    {
        $this->curl = $curl;
        return $this;
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

    /**
     * @return bool
     */
    private function getArchived()
    {
        return $this->archived;
    }

    /**
     * @param  bool $archived
     * @return $this
     */
    private function setArchived($archived)
    {
        $this->archived = $archived;
        return $this;
    }

    /**
     * @return string
     */
    private function getCrocodocUrl()
    {
        return $this->crocodocUrl;
    }

    /**
     * @param string $crocodocUrl
     * @return $this
     */
    private function setCrocodocUrl($crocodocUrl)
    {
        $this->crocodocUrl = $crocodocUrl;
        return $this;
    }

    /**
     * @return string
     */
    private function getCrocodocViewUrl()
    {
        return $this->crocodocViewUrl;
    }

    /**
     * @param string $crocodocViewUrl
     * @return $this
     */
    private function setCrocodocViewUrl($crocodocViewUrl)
    {
        $this->crocodocViewUrl = $crocodocViewUrl;
        return $this;
    }

    /**
     * @return string
     */
    private function getCrocodocDownloadUrl()
    {
        return $this->crocodocDownloadUrl;
    }

    /**
     * @param string $crocodocDownloadUrl
     * @return $this
     */
    private function setCrocodocDownloadUrl($crocodocDownloadUrl)
    {
        $this->crocodocDownloadUrl = $crocodocDownloadUrl;
        return $this;
    }
}