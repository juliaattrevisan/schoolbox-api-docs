<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Blog\Homepage\Search;

use Alaress_Date_Format;
use Schoolbox\Resource\Search\Renderer\Handler;
use Scrub;
use TextFormatter;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Mapper\Exception as MapperException;


class Renderer extends Handler
{

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'blog';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'Blogs';

    /**
     * Render
     */
    public function render()
    {
        $userHandler = new \User();
        $folderMapper = new FolderMapper();

        $result = $this->getResult($this->getType());

        if (!empty($result)) {
            $this->getPage()->setFile('blog_t', 'resource/blog/search/results.html');
            $this->getPage()->setBlock('blog_t', 'blog', 'blog_block');
            $this->getPage()->setVar(array(
                'BLOG_DISP_COUNT' => count($this->getResult($this->getType())),
                'BLOG_COUNT' => $this->getCount($this->getType()),
            ));

            foreach ($result as $blog) {

                try {
                    $folder = $folderMapper->findCheaplyById($blog['folder_id']);

                    foreach ($folderMapper->breadcrumb($folder) as $i => $breadcrumb) {
                        $blog['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb->getId()}\">" . Scrub::text($breadcrumb->getName()) . "</a>";
                    }

                    $createdTime = new Alaress_Date_Format($blog['created_at'], 'Y-m-d H:i:s');

                    $this->getPage()->setVar(
                        array(
                            'BLOG_ID'         => $blog['id'],
                            'BLOG_CID'        => $blog['folder_id'],
                            'BLOG_HEADING'    => Scrub::text($blog['heading']),
                            'BLOG_BODY'       => TextFormatter::cutSentence(Scrub::html2text($blog['body']), 150),
                            'BLOG_OWNER'      => $blog['owner_id'],
                            'BLOG_OWNER_NAME' => $userHandler->formatName($blog),
                            'BLOG_CREATED'    => $createdTime->getRelativeTime(),
                            'BLOG_BREADCRUMB' => $blog['breadcrumb'],
                            'BLOG_COUNT'      => $this->getCount($this->getType()),
                        )
                    );

                    $this->getPage()->parse('blog_block', 'blog', true);
                } catch (MapperException $e) {
                    //noop
                }
            }

            $this->outputSeeMore();

            $this->getPage()->parse('RESULT', 'blog_t', true);
        } else {
            $this->getPage()->setVar('see_more_block', '');

            return;
        }
    }
}
