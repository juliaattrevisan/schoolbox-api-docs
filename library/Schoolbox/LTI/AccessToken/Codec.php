<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\AccessToken;

use Alaress\Codec\ICodec;
use DateTimeImmutable;
use DateTimeInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Entity\AccessToken;
use OAT\Library\Lti1p3Core\Security\OAuth2\Entity\Scope;
use TypeError;

/**
 * Codec for serializing/unserializing access tokens.
 */
class Codec implements ICodec
{
    /**
     * Client repository: required when decoding a token.
     */
    private ClientRepositoryInterface $clientRepository;

    /**
     * Constructor
     *
     * @param ClientRepositoryInterface $clientRepository
     */
    public function __construct(ClientRepositoryInterface $clientRepository)
    {
        $this->clientRepository = $clientRepository;
    }

    /**
     * @param AccessToken $rawValue
     * @return string
     */
    public function encode($rawValue)
    {
        // TODO properly serialize/unserialize
        if (!$rawValue instanceof AccessToken) {
            throw new TypeError('$rawValue must be an AccessToken instance');
        }

        $serializedToken = [
            'identifier'     => $rawValue->getIdentifier(),
            'userIdentifier' => $rawValue->getUserIdentifier(),
            'clientId'       => $rawValue->getClient()->getIdentifier(),
            'scopes'         => $rawValue->getScopes(),
            'expiry'         => $rawValue->getExpiryDateTime()->format(DateTimeInterface::ISO8601),
        ];
        return json_encode($serializedToken);
    }

    /**
     * @param string $encodedValue
     * @return AccessToken
     */
    public function decode($encodedValue)
    {
        $decoded = json_decode($encodedValue, true);
        $token   = new AccessToken();
        $token->setIdentifier($decoded['identifier']);
        $token->setUserIdentifier($decoded['userIdentifier']);
        // Need to fetch client from repository
        $token->setClient($this->clientRepository->getClientEntity($decoded['clientId']));
        foreach ($decoded['scopes'] as $scope) {
            $token->addScope(new Scope($scope));
        }
        $token->setExpiryDateTime(DateTimeImmutable::createFromFormat(DateTimeInterface::ISO8601, $decoded['expiry']));
        return $token;
    }
}
