<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Forum\Search;

use Schoolbox\Resource\Search\Renderer\Handler;
use Scrub;
use TextFormatter;
use Alaress_Date_Relative;

class Renderer extends Handler {

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
     * Render
     */
    public function render() {
        $categoryStd = new \Category();
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
                foreach ($categoryStd->breadcrumb($forum['categoryID']) as $i => $breadcrumb) {
                    $forum['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
                }

                $createdTime = new Alaress_Date_Relative($forum['date']);

                $this->getPage()->setVar(array(
                    'FORUM_CID' => $forum['categoryID'],
                    'FORUM_THREADID' => $forum['threadID'],
                    'FORUM_TITLE' => Scrub::text($forum['threadTitle']),
                    'FORUM_BODY' => TextFormatter::cutSentence(Scrub::html2text($forum['body']), 150),
                    'FORUM_OWNER' => $forum['uid'],
                    'FORUM_OWNER_NAME' => $userHandler->formatName($forum),
                    'FORUM_CREATED' => $createdTime->getRelativeTime(),
                    'FORUM_BREADCRUMB' => $forum['breadcrumb'],
                ));

                $this->getPage()->parse('forum_block', 'forum', true);
            }

            $this->outputSeeMore();

            $this->getPage()->parse('RESULT', 'forum_t', true);
        }
        else {
            $this->renderNoResults();
        }
    }
}