<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Comms\News\Component\Dashboard;

use Component_Config_Size;
use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\Comms\News;
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
    }

    /**
     * @return bool
     */
    public function execute(): bool
    {
        return true;
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
        $limit = Component_Config_Size::SIZE_ALL === (int)$this->getConfig('size')
            ? self::HARD_LIMIT
            : (int)$this->getConfig('size');

        return $this->renderTwigTemplate($templateHandle, '@commsNewsDashboard/component.twig', [
            'componentInstanceId' => $this->getInstance()->getId(),
            'topicAll' => News\Mapper::TOPIC_ALL_SLUG,
            'topic' => 'all',
            'compactMode' => $this->getConfig('compactMode'),
            'limit' => $limit,
            'referrer' => News\Statistics\Mapper::REFERRER_DASHBOARD,
            'returnUrl' => News\Querystring::encode('/'),
            'queryStringPage' => News\Querystring::PAGE_NUMBER,
            'queryStringReferrer' => News\Querystring::REFERRER,
            'queryStringTopic' => News\Querystring::TOPIC_SLUG,
            'showTopicByDefault' => !$this->getConfig('default'),
        ]);
    }
}
