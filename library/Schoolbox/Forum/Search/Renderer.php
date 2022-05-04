<?php

/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Forum\Search;

use Schoolbox\Forum\Post;
use Schoolbox\Resource\Search\MapperResult\Base as MapperResult;
use Schoolbox\Resource\Search\Renderer\Base;
use Scrub;
use TextFormatter;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Renderer extends Base
{

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'forum';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'Forums';

    /**
     * Render html fragment
     *
     * @param MapperResult $mapperResult
     * @param string|null $type
     */
    public function render(MapperResult $mapperResult, $type)
    {
        if ($mapperResult->getCount()) {
            $folderMapper = FolderMapper::getInstance();
            $userHandler = new \User();

            $this->getPage()->setFile('forum_t', 'resource/forum/search/results.html');
            $this->getPage()->setBlock('forum_t', 'forum', 'forum_block');
            $this->getPage()->setVar(array(
                                         'FORUM_DISP_COUNT' => $mapperResult->getResult()->count(),
                                         'FORUM_COUNT' => $mapperResult->getCount(),
                                     ));

            foreach ($mapperResult->getResult() as $post) {
                /** @var Post $post */
                try {
                    $folder = $folderMapper->findCheaplyById($post->getThread()->getCategory()->getId());
                    $forumBreadcrumb = '';

                    foreach ($folderMapper->breadcrumb($folder, true) as $i => $breadcrumb) {
                        $forumBreadcrumb .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb->getId()}\">" . Scrub::text($breadcrumb->getName()) . "</a>";
                    }

                    $this->getPage()->setVar(
                        array(
                            'FORUM_CID'        => $post->getThread()->getCategory()->getId(),
                            'FORUM_THREADID'   => $post->getId(),
                            'FORUM_TITLE'      => $post->getThread()->getTitle(),
                            'FORUM_BODY'       => TextFormatter::cutSentence(Scrub::html2text($post->getBody()), 150),
                            'FORUM_OWNER'      => $post->getOwner()->getId(),
                            'FORUM_OWNER_NAME' => $post->getOwner()->getFullName(),
                            'FORUM_CREATED'    => $post->getDate()->getRelativeTime(),
                            'FORUM_BREADCRUMB' => $forumBreadcrumb,
                        )
                    );

                    $this->getPage()->parse('forum_block', 'forum', true);
                } catch (MapperException $e) {
                    //noop
                }
            }
            $this->getPage()->setBlock($this->getType() . '_t', 'see_more', 'see_more_block');
            /* "outputMore"
            */
            if ($type == 'all' && $mapperResult->getCount() > \Config::get('search_all_limit')) {
                $this->getPage()->setVar('MORE_COUNT', $mapperResult->getCount());
                $this->getPage()->parse('see_more_block', 'see_more');
            } else {
                $this->getPage()->setVar('see_more_block', '');
            }

            $this->getPage()->parse('RESULT', 'forum_t', true);
        } else {
            return ;
        }
    }
}
