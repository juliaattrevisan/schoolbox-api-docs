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
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getGreenhouse($templateName)
    {
        $builder = new \Schoolbox\Curriculum\WACurriculum\Builder();
        $content = $builder->generate();

        $headers = $this->getResponse()->headers;
        $headers->set('Pragma', ' ');
        $headers->set('Cache-Control', ' ');
        $headers->set('Content-Type', 'text/csv');
        $headers->set('Content-Length', \strlen($content));
        $headers->set('Content-Disposition', "attachment; filename=\"WACurriculum.csv\"");

        $this->getResponse()->content->set([
            'format' => 'file',
            'data'   => $content
        ]);
    }
}
