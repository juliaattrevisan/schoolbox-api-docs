<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Help;

use Alaress\Web\Controller\Base;
use Schoolbox\Error;
use Schoolbox\Mapper\Exception as MapperException;

class Controller extends Base
{

    /**
     * @var Error\Mapper
     */
    private $errorMapper;

    /**
     * Controller constructor.
     * @param Error\Mapper $errorMapper
     */
    public function __construct(Error\Mapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }

    /**
     * NESA Report
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getNesa()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@help/nesa.twig', [

        ]));
    }
}
