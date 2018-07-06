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
     * @var ErrorMapper
     */
    private $errorMapper;

    /**
     * Controller constructor.
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage) {
        parent::__construct($instance, $user, $sitepage);

        $this->saveForLaterMapper = SaveForLater\Mapper::getInstance();
        $this->policyMapper       = Policy\Mapper::getInstance();
        $this->errorMapper        = ErrorMapper::getInstance();
    }


    /**
     * Get the data to put into the template
     *
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
        $topics = TopicMapper::getInstance()->findAll();
        $allDefault = $this->getConfig('default');

        return $this->renderTwigTemplate($templateHandle, '@commsNewsDashboard/component.twig', [
            'componentInstanceId' => $this->getInstance()->getId(),
            'topics' => $topics,
            'topicAll' => News\Mapper::TOPIC_ALL,
            'firstTopicId' => $allDefault ? News\Mapper::TOPIC_ALL : $topics->first()->getId(),
            'allDefault' => (bool)$allDefault,
            'compactMode' => $this->getConfig('compactMode'),
            'limit' => $this->getConfig('size')
        ]);
    }
}
