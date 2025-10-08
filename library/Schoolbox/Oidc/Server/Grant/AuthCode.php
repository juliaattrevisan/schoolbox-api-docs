<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Grant;

use League\OAuth2\Server\Grant\AuthCodeGrant;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Contains what I believe is a fix for
 * `\League\OAuth2\Server\Grant\AuthCodeGrant`: instead of checking that query
 * string param `response_type` equals `code`, checks that it simply contains
 * that value.
 *
 * Required so that the response type may contain other parts: e.g. a token
 * and/or id_token.
 */
class AuthCode extends AuthCodeGrant
{
    /**
     * @inheritdoc
     */
    public function canRespondToAuthorizationRequest(ServerRequestInterface $request): bool
    {
        return
            isset($request->getQueryParams()['client_id'])
            && in_array('code', explode(' ', $request->getQueryParams()['response_type'] ?? ''))
        ;
    }
}
