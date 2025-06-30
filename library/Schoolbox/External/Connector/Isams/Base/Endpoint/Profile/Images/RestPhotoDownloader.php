<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile\Images;

use Schoolbox\Application\Exception\ApiException;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Api\Rest\Api;
use Schoolbox\External\Connector\Isams\Base\Endpoint\Codec\DSN;
use Schoolbox\Role;
use SensitiveParameter;

/**
 * Class PhotoDownloader
 *
 * Downloads each users full size picture from isams
 * Following the now used RestApi system
 *
 */
class RestPhotoDownloader implements IPhotoDownloader
{
    /**
     * A set of (isams internal identifier, sis photo id) tuples that remember when we fetched
     * a picture for someone; if they never had a sis photo id, we'll get the latest one.
     * Otherwise we'll use the one specified there.
     *
     * @var array
     */
    private array $photoCache = [];


    /**
     * @var Api
     */
    private Api $api;


    /**
     * Constructor.
     *
     * @param array $credentials
     * @throws ApiException
     * @param LoggerInterface $log
     */
    public function __construct(#[SensitiveParameter] array $credentials, private LoggerInterface $log,)
    {
        /* Store this locally just once
         */
        $this->api = new Api(
            trim((string)($credentials['modules'][DSN::MODULE_RESTAPI]['host'] ?? '')),
            trim((string)($credentials['modules'][DSN::MODULE_RESTAPI]['clientid'] ?? '')),
            trim((string)($credentials['modules'][DSN::MODULE_RESTAPI]['clientsecret'] ?? ''))
        );
    }

    /**
     * This uses the roletype, internal identifier and sis photo identifier to construct a url
     * to obtain a photo from the rest api.
     *
     * Ths sis photo identifier is stored in sis_user_profile.metadata and used to decide when
     * the school's updated a picture in isams: it'll be different id in that circumstance.
     * Also, we need to use that in a url for an api request to obtain the picture's binary data.
     */
    public function getPhoto(string $roleType, string $identifier, int $sisPhotoId): ?string
    {
        switch ($roleType) {
            case Role::STUDENT:
                if (!isset($this->photoCache[$identifier])) {
                    // fetch the latest portrait
                    $url = sprintf('Main/api/students/%s/photos/current', $identifier);

                    $this->log->debug(sprintf(
                        'Getting the current %s photo from %s',
                        $roleType,
                        $url
                    ));
                } else {
                    // fetch a specific portrait
                    $url = sprintf('Main/api/students/%s/photos/%s', $identifier, $sisPhotoId);

                    $this->log->debug(sprintf(
                        'Getting a specific %s photo from %s',
                        $roleType,
                        $url
                    ));
                }
                return $this->api->request(path: $url, method: 'GET', raw: true);

            case Role::STAFF:
                if (!isset($this->photoCache[$identifier])) {
                    // fetch the latest portrait
                    $url = sprintf('Main/api/humanresources/employees/%s/photos/current', $identifier);

                    $this->log->debug(sprintf(
                        'Getting the current %s photo from %s',
                        $roleType,
                        $url
                    ));
                } else {
                    // fetch a specific portrait
                    $url = sprintf('Main/api/humanresources/employees/%s/photos/%s', $identifier, $sisPhotoId);

                    $this->log->debug(sprintf(
                        'Getting a specific %s photo from %s',
                        $roleType,
                        $url
                    ));
                }
                return $this->api->request(path: $url, method: 'GET', raw: true);

            default:
                return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function downloadStudentPhotos(): void
    {
        // unused for RestApi
    }

    /**
     * @inheritDoc
     */
    public function downloadStaffPhotos(): void
    {
        // unused for RestApi
    }


    /**
     * @inheritDoc
     */
    public function getLatestPhotoId(string $roleType = Role::STUDENT, string|int|null $identifier = null): ?int
    {
        switch ($roleType) {
            case Role::STUDENT:
                $url = sprintf('Main/api/students/%s', $identifier);

                $this->log->debug(sprintf(
                    'Looking for the latest student profile picture at %s',
                    $url
                ));

                $userObj =  $this->api->request($url, 'GET');
                break;
            case Role::STAFF:
                /* The internal id is in "Id" and the value we use for external id is "PersonId".
                 * The identifier used here is the isams internal id, which is NOT the same as their external id.
                 */
                $url = sprintf('Main/api/humanresources/employees/%s', $identifier);

                $this->log->debug(sprintf(
                    'Looking for the latest staff profile picture at %s',
                    $url
                ));

                $userObj = $this->api->request($url, 'GET');

                break;
            default:
                return null;
        }

        /**
         * ignore all user data other than the latestPhotoId
         * In time we may need / want to use the rest of this payload, but for now we can just leave it.
         */
        $this->photoCache[$identifier] = $userObj['latestPhotoId'];

        return $userObj['latestPhotoId'];
    }
}
