<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News;


use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use InvalidArgumentException;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Comms\News\Policy;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\News\TopicMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
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
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * @var Mapper
     */
    private $newsMapper;

    /**
     * @var Policy\Mapper
     */
    private $policyMapper;

    /**
     * @var TopicMapper
     */
    private $topicMapper;

    /**
     * Controller constructor.
     *
     * @param ErrorMapper      $errorMapper
     * @param UserMapper       $userMapper
     * @param UserAccessMapper $userAccessMapper
     * @param Mapper           $newsMapper
     * @param Policy\Mapper    $policyMapper
     * @param TopicMapper      $topicMapper
     */
    public function __construct(
        ErrorMapper $errorMapper,
        UserMapper $userMapper,
        UserAccessMapper $userAccessMapper,
        Mapper $newsMapper,
        Policy\Mapper $policyMapper,
        TopicMapper $topicMapper
    ) {
        $this->errorMapper = $errorMapper;
        $this->userMapper = $userMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->newsMapper = $newsMapper;
        $this->policyMapper = $policyMapper;
        $this->topicMapper = $topicMapper;
    }


    /**
     * Provide form to create a news article
     *
     * @throws MapperException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \UnexpectedValueException
     */
    public function getCreate()
    {
        $policies = $this->policyMapper->findAll()->indexBy('identifier');

        /* Spawn an empty news with default settings
         */
        /** @var Model $news */
        $news = $this->newsMapper->createModel()
            ->setPublishAt(\Alaress_Date_Format::now())
            ->setExpiresAt(\Alaress_Date_Format::now());

        /* Policies: Publish Date Offset
         */
        $policy = $policies->getOffset('publish_date_offset');

        if ((new ValidDateOffset())->isSatisfiedBy($policy)) {
            $news->getPublishAt()->modify($policy->getValue())->endOfDay();
        }

        /* Policies: Expiry Date Offset
         */
        $policy = $policies->getOffset('expiry_date_offset');

        if ((new ValidDateOffset())->isSatisfiedBy($policy)) {
            $news->getExpiresAt()->modify($policy->getValue())->endOfDay();
        }

        /* Feature image
         */
        $zone = (new Zone('featureImage'))
            ->setMulti(false)
            ->setAttachPrompt('Attach a feature image')
            ->setAttachmentsLabel('')
            ->setMimeFilter('image/*');

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/create.twig', [
            'policies' => $policies,
            'action' => 'Create',
            'user' => $this->getUser(),
            'news' => $news,
            'featureImageZone' => $zone->getTwigContext(),
        ])->setTitle("Create {$this->getDi()->get('sitePage')->getLocalisedTerm('news', false, true)}"));
    }


    /**
     * Create a news article
     *
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \Schoolbox\Resource\Exception
     * @throws \UnexpectedValueException
     */
    public function postCreate()
    {
        /* Set YOU as the initial author
         * Otherwise, when a moderator edits your article, they might assume direct authorship instead
         */
        $news = $this->newsMapper->createModel();
        $news->setAuthor($this->getUser());

        $this->postUpdate($news);
    }


    /**
     * Provide form to update a news article
     *
     * @param Model $news
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function getUpdate(Model $news)
    {
        /* Only comms officers can edit articles that have been accepted for publishing
         */
        if (!(new ValidUserCanEdit())->isSatisfiedBy($this->getUser(), $news)) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_COMMS_NEWS_ALREADY_ACCEPTED);
        }

        $policies = $this->policyMapper->findAll()->indexBy('identifier');

        /* Feature image
         */
        $zone = (new Zone('featureImage'))
            ->setMulti(false)
            ->setAttachPrompt('Attach a feature image')
            ->setAttachmentsLabel('')
            ->setMimeFilter('image/*');

        if ($news->getFeatureImage()) {
            $zone->attachFiles([$news->getFeatureImage()->getHash()]);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/create.twig', [
            'policies' => $policies,
            'action' => 'Update',
            'user' => $this->getUser(),
            'news' => $news,
            'featureImageZone' => $zone->getTwigContext(),
            'isCommsOfficer' => (new ValidUserIsCommsOfficer())->isSatisfiedBy($this->getUser()),
        ])->setTitle("Update {$news->getTitle()}"));
    }


    /**
     * Update a news article
     *
     * @param Model $news
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Resource\Exception
     */
    public function postUpdate(Model $news)
    {
        try {

            /* TODO: Check that the current session user is allowed to modify this $news
             */

            $this->persist($news, $this->getRequest()->post);

            /* Save a draft of the news article
             * This can occur from either the Create or Update context
             * Update the form action accordingly
             */
            if ($this->getRequest()->isXhr()) {

                /* Current url should secretly update to the Update route
                 */
                $url = $this->getRouter()->generate('commsNews.getUpdate', ['newsId' => $news->getId()]);

                /* Form action should secretly update to the Update route
                 */
                $formAction = $this->getRouter()->generate('commsNews.postUpdate', ['newsId' => $news->getId()]);

                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => true,
                    'url' => $url,
                    'formAction' => $formAction
                ]));
            } else {

                /* Otherwise, you're the creator and you're editing this thing normally
                 * Go to the detail screen to see the outcome of your changes
                 * and also use that screen to advance to the next step
                 */
                $nextStep = $this->getRouter()->generate('commsNews.getDetail', ['newsId' => $news->getId()]);
                $this->getResponse()->redirect->afterPost($nextStep);
                return;
            }

            return;
        }
        catch (MapperException|RouteNotFound|\PDOException|\UnexpectedValueException|\Schoolbox\Resource\Exception $e) {
            if ($this->getRequest()->isXhr()) {
                $this->getResponse()->status->set(400);
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => false,
                    'message' => $e->getMessage()
                ]));

                return;
            }

            throw $e;
        }
    }


    /**
     * Persist model to db during news article authorship
     *
     * @param Model                          $news
     * @param array|\Aura\Web\Request\Values $data
     * @return void
     * @throws MapperException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Resource\Exception
     */
    private function persist(Model $news, $data): void
    {
        $news
            ->setTitle($data['title'])
            ->setBody($data['body'])
            ->setBlurb($data['blurb'])
            ->setTopics($this->topicMapper->findByColumn('newsTopicID', $data['topics']));

        /* Once it's passed Moderation, only Moderators can alter contents.
         * Tough luck, wren; in accordance with the po-po
         */

        /* Exception thrown when an invalid date is submitted; use last known value instead
         * This can occur with bad input; or when datepicker is still processing strtotime shuriken
         */
        try {
            $news->setPublishAt(\Alaress_Date_Format::newLocal($data['publishAt']));
        } catch (InvalidArgumentException $e) {
            // noop
        }

        try {
            $news->setExpiresAt(\Alaress_Date_Format::newLocal($data['expiresAt']));
        } catch (InvalidArgumentException $e) {
            // noop
        }

        /* If any Feature Image was provided, remember it
         * Otherwise clear it (remove a Feature Image)
         */
        if (\is_array($data['attachment'])) {
            $news->setFeatureImage((new CollectorFactory())->build($data['attachment'] ?: [])->getFirst());
        }
        else {
            $news->setFeatureImage(null);
        }

        $this->newsMapper->save($news);
    }


    /**
     * Get a list of available news topics that can be assigned to an article
     */
    public function getTopics()
    {
        $this->getResponse()->content->set($this->buildJsonOutput($this->topicMapper->findAll()));
    }


    /**
     * Load a news article so you can read its contents (the detail screen)
     *
     * @param Model $news
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    public function getDetail(Model $news)
    {
        $policies = $this->policyMapper->findAll()->indexBy('identifier');

        $moderationRequired = $policies->getOffset('global_moderation')->getValue();

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/detail.twig', [
            'policies' => $policies,
            'news' => $news,
            'moderationRequired' => $moderationRequired,
            'isCommsOfficer' => (new ValidUserIsCommsOfficer())->isSatisfiedBy($this->getUser()),
            'isAcceptable' => (new Moderation\ValidUserCanAccept())->isSatisfiedBy($this->getUser(), $moderationRequired, $news),
            'isDeletable' => (new ValidUserCanDelete())->isSatisfiedBy($this->getUser(), $news)
        ])->setTitle($news->getTitle()));
    }


    /**
     * Delete an article
     *
     * @param Model $news
     * @throws MapperException
     * @throws RouteNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function postDelete(Model $news)
    {
        try {

            /* Try to delete this article
             *
             * Moderators can delete any article, but authors can only delete articles
             * that haven't been witnessed by the world. Such articles have to be unaccepted
             * and unpublished
             */
            if ((new ValidUserCanDelete())->isSatisfiedBy($this->getUser(), $news)) {
                $news->setDeletedAt(\Alaress_Date_Format::now());
                $this->newsMapper->save($news);

                /* If you're a moderator, go back to the Moderation list
                 *
                 * TODO: Otherwise go...somewhere else? I don't have a component or dashboard yet
                 */
                $nextStep = '/';

                if ((new ValidUserIsCommsOfficer())->isSatisfiedBy($this->getUser())) {
                    $nextStep = $this->getRouter()->generate('commsNewsModeration.getList');
                }

                if ($this->getRequest()->isXhr()) {
                    $this->getResponse()->content->set($this->buildJsonOutput([
                        'success' => true,
                        'redirect' => $nextStep
                    ]));
                }
                else {
                    $this->getResponse()->redirect->to($nextStep);
                }

                return;
            }

            /* Couldn't delete this article; maybe you weren't allowed
             */
            if ($this->getRequest()->isXhr()) {
                $this->getResponse()->status->set(400);
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => false,
                    'message' => "You don't have permission to delete this article."
                ]));

                return;
            }

            throw $this->errorMapper->generateException(ErrorMapper::ERROR_COMMS_NEWS_CANNOT_DELETE);
        } catch (MapperException|RouteNotFound|\Alaress\Database\Predicate\InvalidColumnException|\PDOException|DefinitionException|\UnexpectedValueException|Exception $e) {
            if ($this->getRequest()->isXhr()) {
                $this->getResponse()->status->set(400);
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => false,
                    'message' => $e->getMessage()
                ]));

                return;
            }

            throw $e;
        }
    }
}
