<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\OAuth2\Client\Provider;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

class Office365 extends AbstractProvider
{

    /**
     * Get the URL that this provider uses to begin authorization.
     *
     * @return string
     */
    public function urlAuthorize()
    {
        return 'https://login.windows.net/common/oauth2/authorize';
    }


    /**
     * Get the URL that this provider users to request an access token.
     *
     * @return string
     */
    public function urlAccessToken()
    {
        return 'https://login.windows.net/common/oauth2/token';
    }


    /**
     * Get the URL that this provider uses to request user details.
     *
     * Since this URL is typically an authorized route, most providers will require you to pass the access_token as
     * a parameter to the request. For example, the google url is:
     *
     * 'https://www.googleapis.com/oauth2/v1/userinfo?alt=json&access_token='.$token
     *
     * @param AccessToken $token
     * @return string
     */
    public function urlUserDetails(AccessToken $token)
    {
        return null;
    }


    /**
     * Given an object response from the server, process the user details into a format expected by the user
     * of the client.
     *
     * @param object      $response
     * @param AccessToken $token
     * @return mixed
     */
    public function userDetails($response, AccessToken $token)
    {
        return null;
    }

    public function getBaseAuthorizationUrl()
    {
        return 'https://login.windows.net/common/oauth2/authorize';
    }

    public function getBaseAccessTokenUrl(array $params)
    {
        return 'https://login.windows.net/common/oauth2/token';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return 'https://outlook.office.com/api/v1.0/me';
    }

    protected function getDefaultScopes()
    {
        // Not sure if there are any?
        return [];
    }

    protected function checkResponse(ResponseInterface $response, $data)
    {
        //check $response status is a failure.
        if (200 !== $response->getStatusCode()) {
            //check if there is an error message, display it
            if (isset($data['error'], $data['error_description'])) {
                throw new SchoolboxException("Office365 Error: {$data['error']}:\n{$data['error_description']}");
            }
            //else just say we had an issue...
            throw new SchoolboxException("Something went wrong");
        }
    }

    protected function createResourceOwner(array $response, AccessToken $token)
    {
        // TODO: Implement createResourceOwner() method.
    }
}
