<?php

/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Folder\News\Search;

use Schoolbox\Resource\Search\Renderer\Handler;
use Scrub;
use TextFormatter;
use Alaress_Date_Format;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Mapper\Exception as MapperException;

class Renderer extends Handler
{

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'homepagenews';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'Homepage News';

    /**
     * Set the tab name to a localised format.
     */
    public function setup()
    {
        $this->setTabName('Homepage ' . $this->getPage()->getLocalisedTerm('news', false, true));
    }

    /**
     * Render
     */
    public function render()
    {
        $folderMapper = FolderMapper::getInstance();
        $userHandler = new \User();

        $result = $this->getResult($this->getType());

        if (!empty($result)) {
            $this->getPage()->setFile('homepagenews_t', 'resource/homepagenews/search/results.html');
            $this->getPage()->setBlock('homepagenews_t', 'homepagenews', 'homepagenews_block');
            $this->getPage()->setVar(array(
                'HOMEPAGENEWS_DISP_COUNT' => count($this->getResult($this->getType())),
                'HOMEPAGENEWS_COUNT' => $this->getCount($this->getType()),
            ));

            foreach ($result as $news) {
                try {
                    $folder = $folderMapper->findCheaplyById($news['ccde']);

                    foreach ($folderMapper->breadcrumb($folder) as $i => $breadcrumb) {
                        $news['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb->getId()}\">" . Scrub::text($breadcrumb->getName()) . "</a>";
                    }

                    $now = Alaress_Date_Format::now();
                    $expiryDate = new Alaress_Date_Format(
                        $news['datetime_expires'],
                        Alaress_Date_Format::DATABASE_FORMAT
                    );
                    $createdTime = new Alaress_Date_Format(
                        $news['datetime_posted'],
                        Alaress_Date_Format::DATABASE_FORMAT
                    );

                    if ($now->gt($expiryDate)) {
                        $news['link'] = "/homepage/newsArchive/{$news['code']}#{$news['id']}";
                    } else {
                        $news['link'] = "/homepage/{$news['code']}#news-{$news['id']}";
                    }

                    $this->getPage()->setVar(
                        array(
                            'HOMEPAGENEWS_ID'         => $news['id'],
                            'HOMEPAGENEWS_CODE'       => $news['code'],
                            'HOMEPAGENEWS_LINK'       => $news['link'],
                            'HOMEPAGENEWS_HEADING'    => Scrub::text($news['heading']),
                            'HOMEPAGENEWS_BODY'       => TextFormatter::cutSentence(Scrub::html2text($news['body']), 150),
                            'HOMEPAGENEWS_OWNER'      => $news['uid'],
                            'HOMEPAGENEWS_OWNER_NAME' => $userHandler->formatName($news),
                            'HOMEPAGENEWS_CREATED'    => $createdTime->getRelativeTime(),
                            'HOMEPAGENEWS_BREADCRUMB' => $news['breadcrumb'],
                            'HOMEPAGENEWS_COUNT'      => $this->getCount($this->getType()),
                        )
                    );

                    $this->getPage()->parse('homepagenews_block', 'homepagenews', true);
                } catch (MapperException $e) {
                    //noop
                }
            }

            $this->outputSeeMore();

            $this->getPage()->parse('RESULT', 'homepagenews_t', true);
        } else {
            $this->getPage()->setVar('see_more_block', '');

            return;
        }
    }
}
