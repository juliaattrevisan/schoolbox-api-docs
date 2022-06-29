<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Auth;

use Okta\JwtVerifier\JwtVerifier;
use Schoolbox\Application\Exception as SchoolboxException;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Class for parsing a Schoolbox user out of a JWT token.
 *
 * TODO remove and replace with `\Schoolbox\API\Token\JwtParser`
 */
class TokenParser
{
    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * Verifier for JWT tokens.
     *
     * @var JwtVerifier
     */
    private $jwtVerifier;


    /**
     * A set of candidate claims in the JWT.
     *
     * Each will be checked in order for whether it contains a value, and if
     * that user exists within Schoolbox.
     *
     * @var string[]|array
     */
    private array $candidateClaims;


    /**
     * Constructor
     *
     * @param UserMapper  $userMapper
     * @param JwtVerifier $jwtVerifier
     * @param array       $candidateClaims
     */
    public function __construct(UserMapper $userMapper, JwtVerifier $jwtVerifier, array $candidateClaims)
    {
        $this->userMapper      = $userMapper;
        $this->jwtVerifier     = $jwtVerifier;
        $this->candidateClaims = $candidateClaims;
    }


    /**
     * Finds the user who matches the token, or null if no user found.
     * This doesn't actually authenticate the user: it merely retrieves a user
     * who is then passed to the authentication function.
     *
     * @param string $token
     * @return User
     * @throws SchoolboxException\NotFound If no user was found
     */
    public function findUserFromToken(string $token): User
    {
        // Parse the token and extract a user from it
        // This line is squelched due to an unfixed warning in 3rd party method
        // `JwtVerifier::validateClientId`: the eventual solution is to remove
        // `okta/jwt-verifier` completely, but for now just ignore the warning.
        $decodedToken = @$this->jwtVerifier->verify($token);
        $claims       = $decodedToken->getClaims();

        // The identification in the token seems to be in one of two claims:
        // * "preferred_username" matches a Schoolbox username
        // * "https://cloudwork.id/claims/sis_id" matches an external ID
        // We'll just use the preferred username until I hear otherwise.

        $messages = [];
        foreach ($this->candidateClaims as $candidateClaim) {
            $candidateClaim = trim($candidateClaim);
            if (isset($claims[$candidateClaim])) {
                $user = $this->userMapper->findByUsername($claims[$candidateClaim]);
                if ($user) {
                    return $user;
                }
                $failureMessage = sprintf(
                    'The token contained a "%s" claim, but its value did not match a Schoolbox user',
                    $candidateClaim
                );
            } else {
                $failureMessage = sprintf(
                    'The token did not contain a "%s" claim',
                    $candidateClaim
                );
            }
            $messages[] = $failureMessage;
        }

        // Candidate claims found no user: can't use it
        throw new SchoolboxException\NotFound(
            sprintf('No claim in the candidates matched a user (%s)', implode('; ', $messages))
        );
    }


    /**
     * Finds the user from a JWT in PHP's server vars.
     *
     * The JWT must be in the "Authorization" HTTP header, and be in the form
     * "Bearer: $token".
     *
     * @param array  $serverVars The contents of the `$_SERVER` super-global.
     *
     * @return User
     * @throws SchoolboxException\Exception       if the "Authorization" header doesn't exist
     * @throws SchoolboxException\Exception       if the "Authorization" header isn't type "Bearer"
     * @throws SchoolboxException\NotFound        if the claims in the JWT could not be mapped to a user
     */
    public function findUserFromServerVars(array $serverVars): User
    {
        // Find the auth header in server vars. If it's not there: do nothing
        $authHeader = $serverVars['HTTP_AUTHORIZATION'] ?? null;
        if (!isset($authHeader)) {
            throw new SchoolboxException\Exception('No "Authorization" header found');
        }

        if (preg_match('/^Bearer\\s+(.*)$/i', $authHeader, $matches)) {
            [, $token] = $matches;
            return $this->findUserFromToken($token);
        }

        // Header was malformed: can't use?
        throw new SchoolboxException\Exception('Only the "Bearer" authorization method is supported');
    }
}
