<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Iframe;

use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Error\Mapper as ErrorMapper;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(private ErrorMapper $errorMapper)
    {
    }


    /**
     * Load an iframe with the specified url
     *
     * @param string $encodedUrl
     * @throws Exception
     * @throws Error
     */
    public function getCivicaAttendance(string $encodedUrl): void
    {
        $url = base64_decode($encodedUrl);

        /* $url isn't safe; exit
         */
        if (!(new Validator($this->getConfigLoader()->get('safe_iframe_source_list')))->isValid($url)) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $this->response = $this->responseFromTemplate('@iframe/index.twig', [
            'url' => $url
        ], 'Civica Attendance');
    }

    /**
     * @param string $encodedUrl
     * @throws Exception
     * @throws Error
     */
    public function getKamiInteractiveView(string $encodedUrl): void
    {
        //TODO considering the encoded url which could potentially have the / character in the url causing the url to redirect to wrong page
        $url = base64_decode($encodedUrl);

        //TODO to be optimised with reusable function(?)
        if (!(new Validator($this->getConfigLoader()->get('safe_iframe_source_list')))->isValid($url)) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $this->response = $this->responseFromTemplate('@iframe/index.twig', [
            'url' => $url
        ], 'Kami Interactive View');
    }
}
