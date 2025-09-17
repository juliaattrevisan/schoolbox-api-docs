<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Geosnapshot;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Oidc\Server\RelyingParty\Grant\Mapper as RelyingPartyGrantMapper;
use Schoolbox\Oidc\Server\RelyingParty\Mapper as RelyingPartyMapper;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\User;

/**
 * Class Controller
 *
 * @package Schoolbox\Geosnapshot
 */
class Controller extends Base
{
    /**
     * @var ErrorMapper
     */
    private ErrorMapper $errorMapper;


    /**
     * Controller constructor.
     *
     * @param ErrorMapper     $errorMapper
     * @param FilesystemProxy $filesystemProxy
     * @param RelyingPartyGrantMapper $grantMapper
     * @param RelyingPartyMapper $partyMapper
     */
    public function __construct(ErrorMapper $errorMapper, private FilesystemProxy $filesystemProxy, private RelyingPartyGrantMapper $grantMapper, private RelyingPartyMapper $partyMapper)
    {
        $this->errorMapper = $errorMapper;
    }


    /**
     * Display all the photos that Geosnapshots gave to us via $url
     *
     * Perform the access check again, in case a) you're not who you say you are
     * and/or b) whether something about the setup/access changed in the meantime
     *
     * @param User $viewee
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function getPhotos(User $viewee): void
    {
        /* Set up guzzle with the authentication pieces
         *
         * If it dies, it dies (throw out a generic error screen when that happens)
         */
        try {
            $api = new Api($this->getConfigLoader()->get('geosnapshot_api_key'));

            $response = $api->retrievePhotos($viewee, $this->getUser(), $this->filesystemProxy);
        } catch (\RuntimeException | \DomainException $e) {
            /* RuntimeException is thrown when student doesn't have a profile pic
             * DomainException is thrown when json can't be decoded
             */
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_GEOSNAPSHOT_UNAVAILABLE);
        }

        /* Otherwise show the gallery
         */
        $this->response = $this->responseFromTemplate(
            '@geosnapshotApi/photos.twig',
            [
                'allBreadcrumbs' => [
                    [$viewee->getFullName(), '/search/user/' . $viewee->getId()],
                    ['Photos', null]
                ],
                'viewee' => $viewee,
                'url' => $response['url'],

                /* Enable this to see what we sent to Geosnapshot
                 */
                 // 'payload' => $api->getLastPayload()
            ]
        );
    }

    /**
     * Staff only Geo photo management tool, with oauth login
     *
     * @api GET /geosnapshot/management
     */
    public function getManagement()
    {
        // does an oAuth session exist for the current user
        $session = false;
        // find the Geo oAuth Client...
        $oAuthClients = $this->partyMapper->findByPredicates(
            $this->partyMapper->getPredicateBuilder()->like('name', '%geosnapshot%')
        );
        // if there is a session for the $this->getUser() in oidc_relying_party_grant then set $session true, else set session false
        foreach ($oAuthClients as $oAuthClient) {
            $auth = $this->grantMapper->findByRelyingPartyAndUser($oAuthClient, $this->getUser());
            if ($auth && (!$auth->isExpired(\Alaress_Date_Format::now()))) {
                $session = true;
                break;
            }
        }

        $this->response = $this->responseFromTemplate('@geosnapshotApi/management.twig', ['session' => $session]);
    }
}
