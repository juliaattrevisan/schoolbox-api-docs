<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\News\Component\Homepage;

use CategoryNews;
use Component_Homepage_ControllerAbstract;
use Schoolbox\Permission\Access\UserAccess;
use News;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Category\NewsMapper;

class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * @param News $news
     */
    protected $news;

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
     * Loads the grid component into its set configuration.
     *
     * @return true
     */
    public function execute()
    {
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
        $newsMapper = new NewsMapper();

        if ($this->getContentPermission() < READ_ACCESS) {
            $news = null;
        } else {
            $news = $this->newsHandler->getAllForCategory($this->source->getId(),
                ['size' => $this->getConfig('size')]);
        }

        /**
         * Check if there's any news items in the archive to conditionally show the archive link
         */
        $showArchive = $newsMapper->checkArchive($this->source->getId(), $_SESSION['id']);

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
                'compact'               => $this->getConfig('compactMode','value'),
                'showArchive'           => $showArchive,
            ]
        );
    }
}