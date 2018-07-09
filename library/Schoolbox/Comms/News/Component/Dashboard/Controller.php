<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Policy;
use Schoolbox\Comms\News\SaveForLater;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\News\TopicMapper;
use Schoolbox\User;
use SitePage;


class Controller extends Component_Dashboard_ControllerAbstract
{

    /**
     * @var SaveForLater\Mapper
     */
    private $saveForLaterMapper;

    /**
     * @var Policy\Mapper
     */
    private $policyMapper;

    /**
     * @var News\Mapper
     */
    private $newsMapper;

    /**
     * @var ErrorMapper
     */
    private $errorMapper;

    /**
     * @var TopicMapper
     */
    private $topicMapper;

    /**
     * @var Collection
     */
    private $topics;

    /**
     * @var int
     */
    private $allDefault;

    /**
     * @var int
     */
    private $firstTopicId;

    /**
     * Controller constructor.
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage) {
        parent::__construct($instance, $user, $sitepage);

        $this->newsMapper  = News\Mapper::getInstance();
        $this->topicMapper = TopicMapper::getInstance();
        $this->errorMapper = ErrorMapper::getInstance();
    }


    /**
     * Get the data to put into the template
     *
     * @return bool
     */
    public function execute(): bool
    {
        $this->topics = $this->topicMapper->findAll();

        /* Figure out the first topic to select by default
         * This initiates an async to load actual articles
         */
        $this->allDefault = $this->getConfig('default');

        $this->firstTopicId = $this->allDefault ? News\Mapper::TOPIC_ALL : $this->topics->first()->getId();

        /* Only render component if there's potentially content somewhere
         * If there's any non-deleted articles for any of the topics
         * maybe the user can see it with their async request
         *
         * Otherwise, hide the component (nothing to see)
         */
        return $this->newsMapper->findAll(['limit' => 1])->count();
    }


    /**
     * Render the component's template
     *
     * @param string $templateHandle
     * @return bool
     * @throws \Exception
     */
    public function renderTemplate($templateHandle): bool
    {
        return $this->renderTwigTemplate($templateHandle, '@commsNewsDashboard/component.twig', [
            'componentInstanceId' => $this->getInstance()->getId(),
            'topics' => $this->topics,
            'topicAll' => News\Mapper::TOPIC_ALL,
            'firstTopicId' => $this->firstTopicId,
            'allDefault' => (bool)$this->allDefault,
            'compactMode' => $this->getConfig('compactMode'),
            'limit' => $this->getConfig('size')
        ]);
    }
}
