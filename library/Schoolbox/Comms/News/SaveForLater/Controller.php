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
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

class Controller extends Base
{

    /**
     * @var Mapper
     */
    private $saveForLaterMapper;

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
     * @param Mapper      $saveForLaterMapper
     * @param ErrorMapper $errorMapper
     */
    public function __construct(Mapper $saveForLaterMapper, ErrorMapper $errorMapper)
    {
        $this->saveForLaterMapper = $saveForLaterMapper;
        $this->errorMapper = $errorMapper;
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
     * Save For Later
     *
     * @param News\Model $news
     */
    public function postActivate(News\Model $news)
    {
        if ($this->getRequest()->isXhr()) {
            try {
                $this->saveForLaterMapper->findOneByColumnList([
                    'user_id' => $this->getUser()->getId(),
                    'article_id' => $news->getId()
                ]);

                $exists = true;
            } catch (Exception $e) {
                $exists = false;
            }

            /* You've already saved this for later...and we know this. Good job.
             */
            if ($exists) {
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => true
                ]));

                return;
            }

            /* Only save a new one if it doesn't already exist
             */
            try {
                $record = $this->saveForLaterMapper->createModel()
                    ->setUser($this->getUser())
                    ->setArticle($news);

                $this->saveForLaterMapper->save($record);

                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => true,
                ]));
            } catch (\PDOException|\UnexpectedValueException|Exception $e) {
                $this->getResponse()->status->set(400);
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => false,
                    'message' => 'Could not Save For Later.'
                ]));
            }

            return;
        }
    }
}