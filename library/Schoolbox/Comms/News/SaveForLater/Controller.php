<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\SaveForLater;

use Alaress\Web\Controller\Base;
use Schoolbox\Comms\News;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * @var ErrorMapper
     */
    private $errorMapper;

    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(ErrorMapper $errorMapper, UserMapper $userMapper)
    {
        $this->errorMapper = $errorMapper;
        $this->userMapper = $userMapper;
    }

    /**
     * Provide form
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getList()
    {

        /* TODO: Get a list of articles that you saved for later
         */

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsSaveForLater/index.twig', [
            'user' => []
        ])->setTitle('Example'));
    }


    /**
     * Process the form vars
     * @param News\Model $news
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function postExample(News\Model $news)
    {

        /* TODO: Async save an article to MyLinks for the time being, because cheapness
         * Recycle the old handlers I suppose
         */

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsSaveForLater/submit.twig', [
            'var1' => $this->getRequest()->post['var1']
        ])->setTitle('Example Form Output'));
    }
}