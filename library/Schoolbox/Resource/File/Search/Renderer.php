<?php
/**
 * Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\File\Search;

use Alaress_Byte_Format;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\File\Model;
use Schoolbox\Resource\Search\MapperResult\Base as MapperResult;
use Schoolbox\Resource\Search\Renderer\Base;
use Scrub;
use TextFormatter;

/* File specific
 */

class Renderer extends Base
{

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
     * Render html fragment
     *
     * @param MapperResult $mapperResult
     * @param $type
     * @throws MapperException
     */
    public function render(MapperResult $mapperResult, $type)
    {
        if (!$mapperResult->getCount()) {
            return;
        }

        $this->getPage()->setFile('file_t', 'resource/file/search/results.html');
        $this->getPage()->setBlock('file_t', 'file', 'file_block');
        $this->getPage()->setBlock('file_t', 'see_more', 'see_more_block');
        $this->getPage()->setBlock('file', 'modify', 'modify_block');
        $this->getPage()->setBlock('file', 'file_user', 'file_user_block');
        $this->getPage()->setBlock('file', 'file_deleted_user', 'file_deleted_user_block');

        $this->getPage()->setVar([
            'see_more_block' => ''
        ]);

        $this->getPage()->setVar([
            'FILE_DISP_COUNT' => $mapperResult->getResult()->count(),
            'FILE_COUNT' => $mapperResult->getCount(),
        ]);

        /* phplib
         */
        foreach ($mapperResult->getResult() as $currentFile) {
            /** @var Model $currentFile */
            $isModified = $currentFile->getModifiedBy() !== null;
            $fileUser = $isModified ? $currentFile->getModifiedBy() : $currentFile->getOwner();

            $this->getPage()->setVar([
                'FILE_ID'          => $currentFile->getId(),
                'FILE_ICON'        => $currentFile->getMimetype()->getIconFile(),
                'FILE_SIZE'        => Alaress_Byte_Format::bytesToString($currentFile->getSize()),
                'FILE_DESCRIPTION' => Scrub::text($currentFile->getTitle()),
                'FILE_COMMENT'     => TextFormatter::cutSentence(Scrub::text($currentFile->getDescription()), 150),
                'FILE_USER'        => $fileUser->getId(),
                'FILE_USER_NAME'   => $fileUser->formatName(),
                'FILE_ACTION_DATE' => $isModified ? $currentFile->getModifiedAt()->getRelativeTime() : $currentFile->getCreatedAt()->getRelativeTime(),
                'FILE_ACTION'      => $isModified ? 'Last updated' : 'Posted',
                'FILE_BREADCRUMB'  => $this->spawnBreadcrumbs($currentFile->getFolder()),
            ]);

            if (Access::getInstance()->getFolderAccess($_SESSION['user'], $currentFile->getFolder()->getId()) >= UserAccess::ACCESS_WRITE) {
                $this->getPage()->parse('modify_block', 'modify');
            } else {
                $this->getPage()->setVar('modify_block');
            }

            $this->getPage()->setVar('file_user_block', '');
            $this->getPage()->setVar('file_deleted_user_block', '');

            if (!$fileUser->isDeleted()) {
                $this->getPage()->parse('file_user_block', 'file_user');
            } else {
                $this->getPage()->parse('file_deleted_user_block', 'file_deleted_user');
            }

            $this->getPage()->parse('file_block', 'file', true);
        }

        /* "outputMore"
         */
        if ($type == 'all' && $mapperResult->getCount() > \Config::get('search_all_limit')) {
            $this->getPage()->setVar('MORE_COUNT', $mapperResult->getCount());
            $this->getPage()->parse('see_more_block', 'see_more');
        }

        $this->getPage()->parse('RESULT', 'file_t', true);
    }
}
