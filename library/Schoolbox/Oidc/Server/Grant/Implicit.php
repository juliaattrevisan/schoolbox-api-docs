<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Oidc\Server\Grant;

use League\OAuth2\Server\Grant\ImplicitGrant;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Contains what I believe is a fix for
 * `\League\OAuth2\Server\Grant\ImplicitGrant`: instead of checking that query
 * string param `response_type` equals `token`, checks that it simply contains
 * that value.
 */
class Implicit extends ImplicitGrant
{
    /**
     * @inheritdoc
     */
    public function canRespondToAuthorizationRequest(ServerRequestInterface $request): bool
    {
        return
            isset($request->getQueryParams()['client_id'])
            && in_array('token', explode(' ', $request->getQueryParams()['response_type'] ?? ''))
        ;
    }
}
