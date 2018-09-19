<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\News\Topic;
use Schoolbox\News\TopicMapper;
use Schoolbox\User;
use SitePage;
use Schoolbox\Permission\Access\UserAccess;

class Controller extends Component_Homepage_ControllerAbstract
{

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
        $this->topics = $this->topicMapper->findAll();

        /* Figure out the first topic to select by default
         * This initiates an async to load actual articles
         *
         * Doctor's note: for now, hp component doesn't provide a config for this, so ALL in all cases
         */
        $this->slug = News\Mapper::TOPIC_ALL_SLUG;

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
        return $this->renderTwigTemplate($templateHandle, '@commsNewsHomepage/component.twig', [
            'componentInstanceId' => $this->getInstance()->getId(),
            'topics' => $this->topics,
            'topicAll' => News\Mapper::TOPIC_ALL_SLUG,
            'slug' => $this->slug,
            'compactMode' => $this->getConfig('compactMode'),
            'limit' => (int)$this->getConfig('size'),
            'folderId' => $this->getSource()->getId(),
            'key' => $this->getUser()->getKey(),
            'referrer' => News\Statistics\Mapper::REFERRER_HOMEPAGE,
            'readonly' => $this->getContextPermission() < UserAccess::ACCESS_WRITE
        ]);
    }
}
