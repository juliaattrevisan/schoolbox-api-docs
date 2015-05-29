<?php
/**
 * Tile component for homepage
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Tile\Component;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Group as GroupHandler;
use Schoolbox\Tile\Layout;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use SitePage;

class HomepageTileController extends Component_Homepage_ControllerAbstract implements TileControllerInterface
{
    /**
     * Deprecated group handler, used for fetching user's permissions
     *
     * @var GroupHandler
     */
    protected $groupHandler;

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
        $this->groupHandler = new GroupHandler();
        $this->delegate = new TileControllerDelegate();
    }

    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
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
        return $this->renderTwigTemplate(
            $templateHandle,
            '@tile/component/tile.twig',
            array(
                'component'                  => $this,
                'showButtons'                => $this->hasWriteAccess(),
                'tile_component_instance_id' => $this->getConfig('tileSource') ?: $this->getInstance()->getId(),
            ),
            $this->hasWriteAccess() || $this->getConfig('compactMode')
        );
    }

    /**
     * @return Layout\Model
     */
    public function getLayout()
    {
        return $this->layout;
    }

    /**
     * @param  Layout\Model $layout
     * @return $this
     */
    public function setLayout($layout)
    {
        $this->layout = $layout;
        return $this;
    }
}
