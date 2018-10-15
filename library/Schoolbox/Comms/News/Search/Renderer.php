<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Search;

use Schoolbox\Resource\Search\MapperResult\Base as MapperResult;
use Schoolbox\Resource\Search\Renderer\Base;

class Renderer extends Base
{

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'news';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'News';

    /**
     * Render html fragment
     *
     * @param MapperResult $mapperResult
     * @param              $type
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \RuntimeException
     */
    public function render(MapperResult $mapperResult, $type): void
    {
    }
}