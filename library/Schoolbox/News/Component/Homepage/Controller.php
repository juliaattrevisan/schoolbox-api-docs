<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\News\Component\Homepage;

use CategoryNews;
use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Permission\Access\UserAccess;
use Group;
use News;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\User;
use SitePage;

class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * @param News $news
     */
    protected $news;

    /**
     * @var Group
     */
    protected $groupHandler;

    /**
     * @var CategoryNews
     */
    protected $newsHandler;

    /**
     * @var \Schoolbox\Resource\Folder\Model
     */
    protected $source;

    /**
     * @var FolderMapper
     */
    protected $folderMapper = null;    

    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage) {
        parent::__construct($instance, $user, $sitepage);
    }

    /**
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
        $this->groupHandler = new Group();
        $this->newsHandler  = new CategoryNews();
        $this->setFolderMapper(new FolderMapper());

        $this->source = $this->getContext();

        if (is_numeric($this->getConfig('src'))) {
            try {
                // remove any non-numeric values from the source config
                $this->source = $this->getFolderMapper()->findById(preg_replace('#[/a-zA-Z]#', '', $this->getConfig('src')));
            } catch (\Exception $e) {
            }
        }

        return true;
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        if ($this->getContentPermission() < READ_ACCESS) {
            $news = null;
        } else {
            $news = $this->newsHandler->getAllForCategory($this->source->getId(),
                array('size' => $this->getConfig('size')));
        }

        $moderate =  $this->getContentPermission() >= UserAccess::ACCESS_FULL;
        $modify =  $this->getContentPermission() >= UserAccess::ACCESS_WRITE;

        if (is_array($news)) {
            foreach ($news as &$item) {
                $item['posted'] = new \Alaress_Date_Format($item['posted'], \Alaress_Date_Format::TIMESTAMP);
                $item['published'] = new \Alaress_Date_Format($item['published'], \Alaress_Date_Format::TIMESTAMP);
            }
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            '@newsHomepage/component.twig',
            [
                'componentInstanceId'   => $this->getInstance()->getId(),
                'componentClass'        => $this->getComponentName(),
                'news'                  => $news,
                'modify'                => $modify,
                'moderate'              => $moderate,
                'code'                  => $this->source->getId(),
                'compact'               => $this->getConfig('compactMode','value')
            ]
        );
    }

    /**
     * @param FolderMapper $folderMapper
     * @return $this
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }

    /**
     * @return FolderMapper
     */
    public function getFolderMapper()
    {
        return $this->folderMapper;
    }    
}