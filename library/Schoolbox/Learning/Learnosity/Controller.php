<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../../../vendor/learnosity/learnosity-sdk-php/bootstrap.php';

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private UserMapper $userMapper
    ) {
    }


    /**
     * Provide form
     */
    public function getExample()
    {
        $payload = [
            'mode' => 'activity_list',
            'user' => [
                'id' => (string)$this->getUser()->getId(),
                'firstname' => $this->getUser()->getFirstname(),
                'lastname' => $this->getUser()->getLastname(),
                'email' => $this->getUser()->getEmail()
            ]
        ];

        $api = new Api($this->getConfigLoader()->get('learnosity_consumer_key'), $this->getConfigLoader()->get('learnosity_consumer_secret'));

        $this->getResponse()->content->set($this->buildHtmlOutput('@learnosity/index.twig', [
            'initOptions' => $api->initOptions('author', $payload)
        ])->setTitle('Example'));
    }
}
