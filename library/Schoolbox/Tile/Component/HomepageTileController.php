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
use Twig_Environment;

class HomepageTileController extends Component_Homepage_ControllerAbstract
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
     * @var Twig_Environment
     */
    protected $templateEngine;

    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     * @param GroupHandler       $group
     */
    public function __construct(
        Component_Instance $instance,
        User $user,
        SitePage $sitepage,
        GroupHandler $group
    ) {
        parent::__construct($instance, $user, $sitepage);
        $this->groupHandler = $group;
    }

    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
        // configuration:
        // gridId
        // tileLayout:
        //  don't quite understand this
        // tileShape
        //  landscape?
        $layoutId = $this->getConfig('layout');

        // empty layout by default
        $mapper = Layout\Mapper::getInstance();
        $this->setLayout($mapper->createModel());
        if (!empty($layoutId)) {
            try {
                // Load the layout if it's there
                // This load should include each tile.
                $this->setLayout(
                    $mapper->findById(
                        $layoutId,
                        array('joins' => array('tile'),)
                    )
                );
            } catch (MapperException $e) {
                // if it wasn't, use the empty layout
            }
        }
        return true;
    }

    /**
     * @param String $templateHandle
     *
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        // Don't mess about with blocks, shove this into Twig
        if (!$this->loadTemplate($templateHandle, 'tile')) {
            return false;
        }
        $this->parseConfig();

        // There should at least be a sitepage I can get a container out of!
        // And that container always ought to be there!
        // It won't be though, if this page wasn't loaded through
        // Schoolbox\Application\WebRequest\Kernel.
        // Therefore I have to do this PHPLib/Twig hybrid thing.
        $this->setBlock($templateHandle, 'header', 'header_block');
        $this->setBlock($templateHandle, 'config', 'config_block');
        $this->setBlock($templateHandle, 'buttons', 'buttons_block');

        // Load content into Twig
        $content = '';
        try {
            $content = $this->sitepage->getTwig()->render(
                '@tile/component/tile.twig',
                array('component' => $this,)
            );
        } catch (\Exception $e) {
            // Don't update content
        }

        // Necessary blockage for readonly vs. write
        $this->setVar(
            array(
                'COMPONENT_CONTENT' => $content,
                'header_block'      => '',
                'config_block'      => '',
                'buttons_block'     => '',
            )
        );
        $permission = $this->groupHandler->getPermission(
            $this->getContext('id'),
            $this->user->getId()
        );
        if ($permission >= WRITE_ACCESS) {
            $this->parse('header_block', 'header');
            $this->parse('config_block', 'config');
            $this->parse('buttons_block', 'buttons');
        }
        return true;
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
