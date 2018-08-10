<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Statistics;

use Alaress\Web\Controller\Base;
use Schoolbox\Comms\News;

class Controller extends Base
{

    /**
     * Controller constructor.
     */
    public function __construct()
    {
    }


    /**
     * Statistical overview for $article
     *
     * @param News\Model $article
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getOverview(News\Model $article)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsStatistics/index.twig', [
            'article' => $article
        ])->setTitle("Statistics for {$article->getTitle()}"));
    }
}