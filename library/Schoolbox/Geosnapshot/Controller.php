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
     * Display all the photos that Geosnapshots gave to us via $url
     *
     * Perform the access check again, in case a) you're not who you say you are
     * and/or b) whether something about the setup/access changed in the meantime
     *
     * @param User $viewee
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \GuzzleHttp\Exception\InvalidArgumentException
     * @throws \JsonException
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function getStudentPhotos(User $viewee): void
    {
        /* Set up guzzle with the authentication pieces
         *
         * If it dies, it dies (throw out a generic error screen when that happens)
         */
        try {
            $response = (new Api($this->getConfigLoader()->get('geosnapshot_api_key')))
                ->retrieveStudentPhotos($viewee);
        } catch (\RuntimeException|\DomainException $e) {

            /* RuntimeException is thrown when student doesn't have a profile pic
             * DomainException is thrown when json can't be decoded
             */
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_GEOSNAPSHOT_UNAVAILABLE);
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
                'url' => $response['url']
            ]
        ));
    }
}
