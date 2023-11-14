<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Isams\Register;

use Alaress\Web\Controller\Base as BaseController;
use GuzzleHttp\Client as HttpClient;
use Schoolbox\Url\Uri;
use Schoolbox\User;

/**
 * Controller for the iSams register.
 */
class Controller extends BaseController
{
    /**
     * Constructor
     *
     * @param HttpClient $httpClient for querying iSams
     */
    public function __construct(private HttpClient $httpClient)
    {
    }

    /**
     * Get the register for a student.
     *
     * @api GET /modules/isams/register/student/{studentId}
     * @since 23.1
     *
     * @param User $student
     * @return void
     */
    public function getStudent(User $student): void
    {
        // Hit the iSams API
        $response = $this->httpClient->get(
            (new Uri('https://developerdemo.isams.cloud/Main/api/students/registers/' . $student->getExternalId()))
                ->appendQueryParameters(['apikey' => '?????'])
        );

        var_dump($response->getBody()->getContents());

        $this->response = $this->responseFromTemplate(
            '@isamsRegister/student.twig',
            ['student' => $student],
        );
    }
}
