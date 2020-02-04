<?php
/**
 * Tile component for homepage
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Tile\Component;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Tile\Layout;
use Schoolbox\User;
use SitePage;

class HomepageTileController extends Component_Homepage_ControllerAbstract implements TileControllerInterface
{

    /**
     * @var Layout\Model|null
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
        // If current user can't view component's source, only display the
        // component if the user has context WRITE+ permission
        if (!$this->isSourceRenderable() && !$this->hasContextWriteAccess()) {
            return false;
        }

        $this->layout = $this->delegate->fetchLayout($this);
        return true;
    }

    /**
     * @param String $templateHandle
     *
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        if (!$this->layout || !$this->isSourceRenderable()) {
            return $this->renderSourceUnavailableTwig($templateHandle);
        } else {
            $twigTemplate = '@tile/component/tile.twig';
            $twigData     = [
                'component'   => $this,
                'writeAccess' => $this->hasWriteAccess(),
            ];
            $renderHeader = !$this->getConfig('compactMode') || $this->hasContextWriteAccess();
            return $this->renderTwigTemplate($templateHandle, $twigTemplate, $twigData, $renderHeader);
        }
    }

    /**
     * Required so that Twig may access this component's layout.
     *
     * @return Layout\Model|null
     */
    public function getLayout()
    {
        return $this->layout;
    }
}
