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
use Schoolbox\Comms\News\Policy;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception;

class Controller extends Base
{

    /**
     * @var Mapper
     */
    private $saveForLaterMapper;

    /**
     * @var Policy\Mapper
     */
    private $policyMapper;

    /**
     * @var ErrorMapper
     */
    private $errorMapper;


    /**
     * Controller constructor.
     *
     * @param Mapper        $saveForLaterMapper
     * @param Policy\Mapper $policyMapper
     * @param ErrorMapper   $errorMapper
     */
    public function __construct(Mapper $saveForLaterMapper, Policy\Mapper $policyMapper, ErrorMapper $errorMapper)
    {
        $this->saveForLaterMapper = $saveForLaterMapper;
        $this->policyMapper = $policyMapper;
        $this->errorMapper = $errorMapper;
    }


    /**
     * Provide form
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getList()
    {
        $articles = $this->saveForLaterMapper->findForUser($this->getUser()->getId());

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsSaveForLater/index.twig', [
            'articles' => $articles
        ])->setTitle('Saved For Later'));
    }


    /**
     * Save For Later
     *
     * @param News\Model $news
     * @throws Exception
     * @throws Exception\Deleted
     */
    public function postActivate(News\Model $news)
    {

        /* Can only save when the Policy is on
         */
        try {
            $policy = $this->policyMapper->findOneByColumn('identifier', 'save_for_later');

            $canSaveForLater = (bool)$policy->getValue();

            if (!$canSaveForLater) {
                $this->getResponse()->status->set(400);
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => false,
                    'message' => 'This feature has been disabled.'
                ]));
                return;
            }
        } catch (Exception $e) {

            /* Unknown settings; just leave
             */
            return;
        }

        /* Policy lets you do a thing
         */
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