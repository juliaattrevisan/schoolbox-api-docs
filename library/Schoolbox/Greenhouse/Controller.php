<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Greenhouse;

use Alaress\Web\Controller\Base;

class Controller extends Base
{

    /**
     * @var \Schoolbox\Error\Mapper
     */
    private $errorMapper;

    /**
     * Controller constructor.
     *
     * @param \Schoolbox\Error\Mapper $errorMapper
     */
    public function __construct(\Schoolbox\Error\Mapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }

    /**
     * Load $templateName
     *
     * @param string $templateName
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getGreenhouse($templateName)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@greenhouse/' . $templateName . '.twig', []));
    }
}
