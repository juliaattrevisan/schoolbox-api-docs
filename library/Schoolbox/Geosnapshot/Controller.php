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
use Schoolbox\Learning\Assessment\Redirect;
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
     * @param ErrorMapper $errorMapper
     */
    public function __construct(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }


    /**
     * With a provided url, make a request to Geosnapshot
     *
     * @param User $viewee
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function getStudentPhotosUrl(User $viewee): void
    {
        /* If you're allowed to make this request...
         */
        $isGeosnapshotAvailable = (new ValidStudentPhotosAvailable())
            ->isSatisifiedBy(
                $this->getConfigLoader()->get('geosnapshot_api_key'),
                $this->getConfigLoader()->get('geosnapshot_enabled'),
                $this->getUser(),
                $viewee
            );

        /* No Geosnapshot? No trabajo
         *
         * One or more misconfigurations or access controls could have caused this
         * but all result in nothing to see for the current user
         */
        if (!$isGeosnapshotAvailable) {
            throw new \RuntimeException('Geosnapshot: Unable to obtain photos: Geosnapshot is not available for this user.');
        }

        /* Set up guzzle with the authentication pieces
         *
         * Get the iframe url back, if successful, and pop out a html fragment
         * which we can inject into a modal, I guess
         *
         * Otherwise just don't do anything and let the toaster communicate the issue
         */
        $response = (new Api($this->getConfigLoader()->get('geosnapshot_api_key')))
            ->retrieveStudentPhotos($viewee);

        /* Redirect to a special place
         */
        $this->getResponse()->content->set($this->buildJsonOutput([
            'url' => $this->getRouter()->generate('geosnapshotApi.getStudentPhotos', [
                'id' => $viewee->getId(),
                'url' => Redirect::encode($response['url'])
            ])
        ]));
    }


    /**
     * Display all the photos that Geosnapshots gave to us via $url
     *
     * Perform the access check again, in case a) you're not who you say you are
     * and/or b) whether something about the setup/access changed in the meantime
     *
     * @param User   $viewee
     * @param string $url
     * @throws \RuntimeException
     */
    public function getStudentPhotos(User $viewee, string $url): void
    {

        /* If you're allowed to make this request...
         */
        $isGeosnapshotAvailable = (new ValidStudentPhotosAvailable())
            ->isSatisifiedBy(
                $this->getConfigLoader()->get('geosnapshot_api_key'),
                $this->getConfigLoader()->get('geosnapshot_enabled'),
                $this->getUser(),
                $viewee
            );

        /* No Geosnapshot? No trabajo
         *
         * One or more misconfigurations or access controls could have caused this
         * but all result in nothing to see for the current user
         */
        if (!$isGeosnapshotAvailable) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        /* Otherwise show the gallery
         */
        $this->getResponse()->content->set($this->buildHtmlOutput(
            '@geosnapshotApi/studentPhotos.twig', [
                'allBreadcrumbs' => [
                    [$viewee->getFullName(), '/search/user/' . $viewee->getId()],
                    ['Photos', null]
                ],
                'viewee' => $viewee,
                'url' => $url
            ]
        ));
    }
}
