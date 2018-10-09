<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Component\Dashboard;

use Component_Config_Size;
use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\News\Topic;
use Schoolbox\News\TopicMapper;
use Schoolbox\User;
use SitePage;

class Controller extends Component_Dashboard_ControllerAbstract
{
    // Max number of articles to show if 'size' config is set to 'ALL'
    private const HARD_LIMIT = 50;

    /**
     * @var News\Mapper
     */
    private $newsMapper;

    /**
     * @var TopicMapper
     */
    private $topicMapper;

    /**
     * @var Collection|Topic[]
     */
    private $topics;

    /**
     * @var string
     */
    private $slug;


    /**
     * Controller constructor.
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     * @throws \InvalidArgumentException
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);

        $this->newsMapper  = $this->getMapperFactory()->get(News\Mapper::class);
        $this->topicMapper = $this->getMapperFactory()->get(TopicMapper::class);
    }


    /**
     * Get the data to put into the template
     *
     * @return bool
     */
    public function execute(): bool
    {
        $this->topics = $this->topicMapper->findAll(['order'=>['sequence'=>'ASC']]);

        /* Figure out the first topic to select by default
         * This initiates an async to load actual articles
         */
        $this->slug = (bool)$this->getConfig('default') ? News\Mapper::TOPIC_ALL_SLUG : $this->topics->first()->getSlug();

        /* Only render component if there's potentially content somewhere
         * If there's any non-deleted articles for any of the topics
         * maybe the user can see it with their async request
         *
         * Otherwise, hide the component (nothing to see)
         */
        return $this->newsMapper->countAll() > 0;
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
        $limit = Component_Config_Size::SIZE_ALL === $this->getConfig('size')
            ? self::HARD_LIMIT
            : (int)$this->getConfig('size');

        return $this->renderTwigTemplate($templateHandle, '@commsNewsDashboard/component.twig', [
            'componentInstanceId' => $this->getInstance()->getId(),
            'topics' => $this->topics,
            'topicAll' => News\Mapper::TOPIC_ALL_SLUG,
            'slug' => $this->slug,
            'compactMode' => $this->getConfig('compactMode'),
            'limit' => $limit,
            'referrer' => News\Statistics\Mapper::REFERRER_DASHBOARD
        ]);
    }
}
