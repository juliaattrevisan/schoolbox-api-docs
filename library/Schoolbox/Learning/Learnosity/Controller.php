<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use Alaress\Web\Controller\Base;
use LearnositySdk\Request\Init;
use LearnositySdk\Utils\Uuid as LearnosityUuid;
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
        $userId = LearnosityUuid::generate();
        $sessionId = LearnosityUuid::generate();
        $consumerKey = 'yis0TYCu7U9V4o7M';
        $consumerSecret = '74c5fd430cf1242a527f6223aebd42d30464be22';

        $request = [
            'user_id'              => $userId,
            'activity_template_id' => 'quickstart_examples_activity_template_001',
            'session_id'           => $sessionId,
            'activity_id'          => 'quickstart_examples_activity_001',
            'rendering_type'   => 'assess',
            'type'             => 'submit_practice',
            'name'             => 'Items API Quickstart',
        ];

        $security = [
            'domain'       => 'localhost',
            'consumer_key' => $consumerKey
        ];

        new \Alaress_Probe($request, $security);

        $initOptions = (new Init(
            'items',
            $security,
            $consumerSecret,
            $request
        ))->generate();

        $this->getResponse()->content->set($this->buildHtmlOutput('@learnosity/index.twig', [
            'initOptions' => $initOptions
        ])->setTitle('Example'));
    }
}
