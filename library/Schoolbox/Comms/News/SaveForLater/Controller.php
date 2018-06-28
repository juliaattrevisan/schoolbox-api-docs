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
     */
    public function postActivate(News\Model $news)
    {
        if ($this->getRequest()->isXhr()) {
            try {

                /* TODO: actually save it
                 */
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => true,
                ]));

            } catch (\Exception $e) {
                $this->getResponse()->status->set(400);
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => false,
                    'message' => "You don't have permission to delete this article."
                ]));
            }
        }

        return;
    }
}