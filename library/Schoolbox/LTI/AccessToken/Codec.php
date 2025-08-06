<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\AccessToken;

use Alaress\Codec\ICodec;
use DateTimeImmutable;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use OAT\Library\Lti1p3Core\Security\OAuth2\Entity\AccessToken;
use OAT\Library\Lti1p3Core\Security\OAuth2\Entity\Scope;
use TypeError;
use UnexpectedValueException;

/**
 * Codec for serializing/unserializing access tokens.
 */
class Codec implements ICodec
{
    private const FIELD_IDENTIFIER      = 'identifier';
    private const FIELD_USER_IDENTIFIER = 'userIdentifier';
    private const FIELD_CLIENT_ID       = 'clientId';
    private const FIELD_SCOPES          = 'scopes';
    private const FIELD_EXPIRY          = 'expiry';

    /**
     * Date format used for encoding/decoding expiry.
     *
     * This matches the deprecated format `\DateTimeInterface::ISO8601`: however
     * it is safe to use this internally.
     */
    private const DATE_FORMAT = 'Y-m-d\TH:i:sO';

    /**
     * Fields which must be in the decoded value, otherwise the decoded value
     * will be rejected.
     */
    private const REQUIRED_FIELDS = [
        self::FIELD_IDENTIFIER,
        self::FIELD_USER_IDENTIFIER,
        self::FIELD_CLIENT_ID,
        self::FIELD_SCOPES,
        self::FIELD_EXPIRY,
    ];

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
        if (!$rawValue instanceof AccessToken) {
            throw new TypeError('$rawValue must be an AccessToken instance');
        }

        $serializedToken = [
            self::FIELD_IDENTIFIER      => $rawValue->getIdentifier(),
            self::FIELD_USER_IDENTIFIER => $rawValue->getUserIdentifier(),
            self::FIELD_CLIENT_ID       => $rawValue->getClient()->getIdentifier(),
            self::FIELD_SCOPES          => $rawValue->getScopes(),
            self::FIELD_EXPIRY          => $rawValue->getExpiryDateTime()->format(self::DATE_FORMAT),
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

        // Need to verify all required fields exist
        foreach (self::REQUIRED_FIELDS as $requiredField) {
            if (!array_key_exists($requiredField, $decoded)) {
                throw new UnexpectedValueException(
                    sprintf('Required field "%s" does not exist in decoded value', $requiredField),
                );
            }
        }


        // Now create a token
        $token = new AccessToken();
        $token->setIdentifier($decoded[self::FIELD_IDENTIFIER]);
        $token->setUserIdentifier($decoded[self::FIELD_USER_IDENTIFIER] ?? '');
        // Need to fetch client from repository
        $token->setClient($this->clientRepository->getClientEntity($decoded[self::FIELD_CLIENT_ID]));
        foreach ($decoded[self::FIELD_SCOPES] as $scope) {
            $token->addScope(new Scope($scope));
        }
        $token->setExpiryDateTime(
            DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $decoded[self::FIELD_EXPIRY]),
        );
        return $token;
    }
}
