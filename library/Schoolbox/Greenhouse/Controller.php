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
        $fieldMapper = \Schoolbox\Admin\Profile\Editor\Field\Mapper::getInstance();
        $userMapper = \Schoolbox\UserMapper::getInstance();

        /* Find all the fields that were enabled on $user's profile screen,
         * ordered by their prescribed sequence
         */
        $super = \Schoolbox\UserMapper::getInstance()->findById(1);
        $jana = \Schoolbox\UserMapper::getInstance()->findById(25);

        $fields = (new \Schoolbox\Profile\Field\Factory($fieldMapper, $userMapper, new \External_User()))
            ->produce($jana, $super)['data']['roleTypes']['fields']['selected'];

        $this->response = $this->responseFromTemplate('@greenhouse/' . $templateName . '.twig', [
            'fields' => $fields
        ]);
    }
}
