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
use Schoolbox\Comms\News\Policy;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Redirect;
Use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\News\TopicMapper;
use Schoolbox\Permission\Access\UserAccess;
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
        $news = $this->newsMapper->createModel();

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
     */
    public function getUpdate(Model $news)
    {
        /* Decide if the current user is a Comms Officer
         */
        $commsOfficers = $this->userAccessMapper->findUsersForService(UserAccess::SERVICE_COMMS_OFFICER)->getUnique('id')->indexBy('id');
        $isCommsOfficer = $this->getUser()->isSuperuser() || $commsOfficers->getOffset($this->getUser()->getId());

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
            'returnUrl' => $this->getRequest()->query['returnUrl'],
            'isCommsOfficer' => $isCommsOfficer,
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

            /* Re-send the form action back to js
             */
            $formAction = $this->getRouter()->generate('commsNews.getUpdate', ['newsId' => $news->getId()]);

            /* Redirect back to Moderation if you edited this from the Moderation workflow
             */
            $returnUrl = $this->getRequest()->query['returnUrl'];

            if ($returnUrl) {
                $formAction = "{$formAction}?returnUrl={$returnUrl}";
            }

            /* Save a draft of the news article
             * This can occur from either the Create or Update context
             * Update the form action accordingly
             */
            if ($this->getRequest()->isXhr()) {
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => true,
                    'formAction' => $formAction
                ]));
            } else {

                /* Redirect back to Moderation if you edited this from the Moderation workflow
                 */
                if (null !== $returnUrl) {
                    $this->getResponse()->redirect->to(Redirect::decode($returnUrl));
                    return;
                }

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
            ->setAuthor($this->getUser())
            ->setTopics($this->topicMapper->findByColumn('newsTopicID', $data['topics']))
            ->setModeratedBy(null)
            ->setModeratedAt(new \Alaress_Date_NullDate())
            ->setPublishAt(\Alaress_Date_Format::newLocal($data['publishAt']))
            ->setExpiresAt(\Alaress_Date_Format::newLocal($data['expiresAt']));

        /* If any Feature Image was provided, remember it
         * Otherwise clear it (remove a Feature Image)
         */
        if (\is_array($data['attachment'])) {
            $news->setFeatureImage((new CollectorFactory())->build($data['attachment'] ?: [])->getFirst());
        }
        else {
            $news->setFeatureImage(null);
        }

        /* Identify drafts
         * if 'draft' key exists, it's a draft
         * if 'submitConfirmation' key exists, author ticked the checkbox to signal article is ready for moderation
         */
        $news->setSubmittedAt(new \Alaress_Date_NullDate());

        if (array_key_exists('submitConfirmation', $data)) {
            $news->setSubmittedAt(\Alaress_Date_Format::now());
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
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getDetail(Model $news)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/detail.twig', [
            'news' => $news,
        ])->setTitle($news->getTitle()));
    }
}