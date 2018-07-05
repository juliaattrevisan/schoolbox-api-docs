<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Component\Dashboard;

use Alaress\Web\Controller\Base;
use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Policy;
use Schoolbox\Comms\News\SaveForLater;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception;
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
     * @return bool|void
     */
    public function execute()
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
        return $this->renderTwigTemplate($templateHandle, '@commsNewsDashboard/component.twig', []);
    }

    /**
     * A list of articles that you saved for later
     *
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getList()
    {
        $canSaveForLater = true;


    }


    /**
     * Asynchronously obtain a list of articles that you can somehow see
     *
     * TODO: Audiencing decides whether you can see it
     */
    public function getArticlesByTopic()
    {
    }
}
