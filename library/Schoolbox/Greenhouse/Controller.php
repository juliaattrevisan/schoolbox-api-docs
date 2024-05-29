<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Greenhouse;

use Alaress\Web\Controller\Base;
use Twig\Error\Error;

/**
 * The Greenhouse lets you experiment with twig templates and contents without
 * needing a backend dev to do it for you. You can also experiment with other
 * front-end stuff in an isolated manner
 */
class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
    }


    /**
     * Load $templateName
     *
     * @param string $templateName
     * @throws Error
     */
    public function getGreenhouse(string $templateName): void
    {
        $workTypeMapper = \Schoolbox\Learning\Assessment\WorkType\Mapper::getInstance();
        $termMapper = \Schoolbox\Assessment\Term\Mapper::getInstance();
        $userMapper = \Schoolbox\UserMapper::getInstance();

        $student = $userMapper->findById(25);

        $result = (new \Schoolbox\Admin\SummativeReport\Payload\VictorianProgression($workTypeMapper, $termMapper))->generate($student);

        /* Concoct arbitrarily for a test
         */
        $result['data'][140]['subnodes'] = null;
        $result['data'][140]['current'] = 9;
        $result['data'][140]['previous'] = 8;

        $result['data'][157]['subnodes'] = null;
        $result['data'][157]['current'] = 9;
        $result['data'][157]['previous'] = 8;

        $result['data'][156]['subnodes'] = null;
        $result['data'][156]['current'] = 9;
        $result['data'][156]['previous'] = 8;

        $this->response = $this->responseFromTemplate('@greenhouse/' . $templateName . '.twig', [
            'payload' => $result
        ]);
    }
}
