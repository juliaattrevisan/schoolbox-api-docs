<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\SportsFixtures\Sport\Model as Sport;
use \Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;

class Controller extends Base
{

    /**
     * @var SportMapper
     */
    protected $sportMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->setSportMapper(SportMapper::getInstance());
        $this->setErrorMapper(ErrorMapper::getInstance());
    }

    /**
     * Just a raw template render
     *
     * @param string $template
     * @param array  $data
     */
    protected function tpl($template, $data = [])
    {
        $content = [
            'template' => "@sportsfixtures/$template.twig",
            'data'     => $data,
        ];

        if ($this->getRequest()->isXhr()) {
            $content['format'] = 'nochrome';
        }

        $this->getResponse()->content->set($content);
    }

    /**
     * Admin landing page
     */
    public function getLanding()
    {
        $sports = $this->getSportMapper()->findAll();
        $this->tpl('landing', ['sports' => $sports]);
    }

    /* Getters/Setters
     */

    /**
     * @return SportMapper
     */
    protected function getSportMapper()
    {
        return $this->sportMapper;
    }

    /**
     * @param SportMapper $sportMapper
     */
    protected function setSportMapper($sportMapper)
    {
        $this->sportMapper = $sportMapper;
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     */
    protected function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
    }
}
