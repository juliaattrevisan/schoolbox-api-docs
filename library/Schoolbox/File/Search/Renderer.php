<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\File\Search;

use Schoolbox\Resource\Search\Renderer\Handler;
use Scrub;
use TextFormatter;
use Alaress_Date_Relative;
use Alaress_Byte_Format;

class Renderer extends Handler {

    /**
     * Type (e.g. 'link', 'blog', etc)
     *
     * @var string
     */
    protected $type = 'file';

    /**
     * Tab Name (e.g. 'Link', 'Blogs', etc);
     *
     * @var string
     */
    protected $tabName = 'Files';

    /**
     * Render
     */
    public function render() {
        $categoryStd = new \Category();
        $userHandler = new \User();

        $result = $this->getResult($this->getType());

        if (!empty($result)) {
            $this->getPage()->setFile('file_t', 'resource/file/search/results.html');
            $this->getPage()->setBlock('file_t', 'file', 'file_block');
            $this->getPage()->setBlock('file', 'modify', 'modify_block');
            $this->getPage()->setVar(array(
                'FILE_DISP_COUNT' => count($this->getResult($this->getType())),
                'FILE_COUNT' => $this->getCount($this->getType()),
            ));

            foreach ($result as $file) {
                foreach ($categoryStd->breadcrumb($file['category']) as $i => $breadcrumb) {
                    $file['breadcrumb'] .= ($i != 0 ? ' > ' : '') . "<a href=\"/homepage/{$breadcrumb['id']}\">{$breadcrumb['name']}</a>";
                }

                $createdTime = new Alaress_Date_Relative($file['submitted']);

                $this->getPage()->setVar(array(
                    'FILE_ID' => $file['id'],
                    'FILE_ICON' => $file['iconFile'],
                    'FILE_SIZE' => Alaress_Byte_Format::bytesToString($file['file_size']),
                    'FILE_DESCRIPTION' => Scrub::text($file['description']),
                    'FILE_COMMENT' => TextFormatter::cutSentence(Scrub::text($file['comment']), 150),
                    'FILE_OWNER' => $file['uid'],
                    'FILE_OWNER_NAME' => $userHandler->formatName($file),
                    'FILE_CREATED' => $createdTime->getRelativeTime(),
                    'FILE_BREADCRUMB' => $file['breadcrumb'],
                    'FILE_COUNT' => $this->getCount($this->getType()),
                ));

                if ($file['perms_type'] < 2 && $file['userAccess_type'] < 2)
                    $this->getPage()->setVar('modify_block');
                else
                    $this->getPage()->parse('modify_block', 'modify');

                $this->getPage()->parse('file_block', 'file', true);
            }

            $this->outputSeeMore();

            $this->getPage()->parse('RESULT', 'file_t', true);
        }
    }
}