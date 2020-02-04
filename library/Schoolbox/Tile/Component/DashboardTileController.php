<?php
/**
 * Tile component for dashboard
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Tile\Component;

use Component_Dashboard_ControllerAbstract;
use Component_Instance;
use Schoolbox\Tile\Layout;
use Schoolbox\User;
use SitePage;

class DashboardTileController extends Component_Dashboard_ControllerAbstract implements TileControllerInterface
{

    /**
     * @var Layout\Model
     */
    protected $layout;

    /**
     * @var TileControllerDelegate
     */
    protected $delegate;

    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     */
    public function __construct(
        Component_Instance $instance,
        User $user,
        SitePage $sitepage
    ) {
        parent::__construct($instance, $user, $sitepage);
        $this->delegate = new TileControllerDelegate();
    }

    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
        if ($this->isUsingAltSource() && $this->getSource()->isDeleted()) {
            return false;
        }

        $this->setLayout($this->delegate->fetchLayout($this));
        return true;
    }

    /**
     * @param String $templateHandle
     *
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        if ($this->getLayout()->getTileList()->count()) {
            return $this->renderTwigTemplate(
                $templateHandle,
                '@tile/component/tile.twig',
                [
                    'component'   => $this,
                    'showButtons' => false,
                    'writeAccess' => false,
                ],
                !$this->getConfig('compactMode'),
                false
            );
        }

        return false;
    }

    /**
     * @return Layout\Model
     */
    public function getLayout()
    {
        return $this->layout;
    }

    /**
     * @param Layout\Model $layout
     * @return $this
     */
    public function setLayout($layout)
    {
        $this->layout = $layout;
        return $this;
    }
}
