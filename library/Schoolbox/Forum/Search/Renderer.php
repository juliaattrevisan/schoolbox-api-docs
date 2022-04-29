<?php

/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Forum\Search;

use Schoolbox\Resource\Search\MapperResult\Base as MapperResult;
use Schoolbox\Resource\Search\Renderer\Base;
use Scrub;
use TextFormatter;
use Alaress_Date_Format;
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
        $folderMapper = FolderMapper::getInstance();
        $userHandler = new \User();

        $result = $this->getResult($this->getType());

        if (!empty($result)) {
            $this->getPage()->setFile('forum_t', 'resource/forum/search/results.html');
            $this->getPage()->setBlock('forum_t', 'forum', 'forum_block');
            $this->getPage()->setVar(array(
                'FORUM_DISP_COUNT' => count($this->getResult($this->getType())),
                'FORUM_COUNT' => $this->getCount($this->getType()),
            ));

            foreach ($result as $forum) {
                try {
                    $folder = $folderMapper->findCheaplyById($forum['categoryID']);
                    $forum['breadcrumb'] = '';

                    foreach ($folderMapper->breadcrumb($folder, true) as $i => $breadcrumb) {
                        $forum['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb->getId()}\">" . Scrub::text($breadcrumb->getName()) . "</a>";
                    }

                    $createdTime = new Alaress_Date_Format($forum['date'], 'Y-m-d H:i:s');

                    $this->getPage()->setVar(
                        array(
                            'FORUM_CID'        => $forum['categoryID'],
                            'FORUM_THREADID'   => $forum['threadID'],
                            'FORUM_TITLE'      => Scrub::text($forum['threadTitle']),
                            'FORUM_BODY'       => TextFormatter::cutSentence(Scrub::html2text($forum['body']), 150),
                            'FORUM_OWNER'      => $forum['uid'],
                            'FORUM_OWNER_NAME' => $userHandler->formatName($forum),
                            'FORUM_CREATED'    => $createdTime->getRelativeTime(),
                            'FORUM_BREADCRUMB' => $forum['breadcrumb'],
                        )
                    );

                    $this->getPage()->parse('forum_block', 'forum', true);
                } catch (MapperException $e) {
                    //noop
                }
            }

            $this->outputSeeMore();

            $this->getPage()->parse('RESULT', 'forum_t', true);
        } else {
            $this->getPage()->setVar('see_more_block', '');

            return;
        }
    }
}
