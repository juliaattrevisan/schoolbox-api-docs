<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\News\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use News;
use Schoolbox\Resource\Category\NewsMapper;

class Controller extends Component_Homepage_ControllerAbstract
{

    /**
     * @param News $news
     */
    private $newsItems;

    /**
     * @var bool
     */
    private $showArchive;


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException If unable to locate Mapper class
     */
    public function execute()
    {
        if (false === parent::execute()) {
            return false;
        }

        /** @var NewsMapper $newsMapper */
        $newsMapper      = $this->getMapperFactory()->get(NewsMapper::class);
        $limit           = '0' === $this->getConfig('size') ? null : (int)$this->getConfig('size');
        $this->newsItems = $newsMapper->findForFolder($this->getSource()->getId(), $this->getUser(), $limit);

        // Check if there's any news items in the archive to conditionally show the archive link
        $this->showArchive = $newsMapper->checkArchive($this->getSource()->getId(), $this->getUser()->getId());

        return true;
    }


    /**
     * @param String $templateHandle
     * @return bool
     * @throws \Exception If something went wrong while rendering the twig and 'debug_mode' is ON
     */
    public function renderTemplate($templateHandle)
    {
        if(!$this->isSourceRenderable()) {
            return $this->renderSourceUnavailableTwig($templateHandle);
        }
        return $this->renderTwigTemplate(
            $templateHandle,
            '@newsHomepage/component.twig',
            [
                'componentInstanceId' => $this->getInstance()->getId(),
                'componentClass'      => $this->getComponentName(),
                'news'                => $this->newsItems,
                'modify'              => $this->hasWriteAccess(),
                'moderate'            => $this->hasFullAccess(),
                'code'                => $this->getSource()->getId(),
                'compact'             => (bool)$this->getConfig('compactMode', 'value'),
                'showArchive'         => $this->showArchive,
            ]
        );
    }
}
