<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Attachment;


use Schoolbox\Mapper\Exception;
use Schoolbox\Storage;
use Schoolbox\StorageMapper;

class Zone {

    const TPL = 'azTpl';

    /** @var string */
    protected $id;

    /** @var int */
    protected $size;

    /** @var string */
    protected $position;

    /** @var Storage[] */
    protected $attachments;

    /** @var array */
    protected $text;

    /** @var string */
    protected $mimeFilter;

    /** @var bool */
    protected $multi;

    /** @var StorageMapper */
    protected $mapper;

    /**
     * @param string $zoneId
     * @param StorageMapper $mapper
     */
    public function __construct($zoneId, $mapper = null)
    {
        $this->id       = $zoneId;
        $this->size     = 32;
        $this->multi    = false;
        $this->text     = array(
            'attachPrompt'     => 'Attach files',
            'attachmentsLabel' => 'Attachments',
            'modalTitle'       => 'Select files',
        );
        $this->mapper   = $mapper ?: StorageMapper::getInstance();

        $this->mimeFilter  = '';
        $this->attachments = array();
    }


    /**
     * @param array $hashlist
     * @return self
     */
    public function attachFiles(array $hashlist)
    {
        foreach ($hashlist as $hash) {
            try {
                $this->attachments[$hash] = $this->mapper->findByHash($hash);
            } catch (Exception $e) {
                // file given doesn't exist in Storage
                // TODO maybe look elsewhere?
            }
        }
        return $this;
    }


    /**
     * @param int $px
     * @return self
     */
    public function setThumbnailSize($px)
    {
        $this->size = $px;
        return $this;
    }


    /**
     * @param string $text
     * @return self
     */
    public function setAttachPrompt($text)
    {
        $this->text['attachPrompt'] = $text;
        return $this;
    }


    /**
     * @param string $text
     * @return self
     */
    public function setAttachmentsLabel($text)
    {
        $this->text['attachmentsLabel'] = $text;
        return $this;
    }


    /**
     * @param string $text
     * @return self
     */
    public function setModalTitle($text)
    {
        $this->text['modalTitle'] = $text;
        return $this;
    }


    /**
     * @param string $filter
     * @return self
     */
    public function setMimeFilter($filter)
    {
        $this->mimeFilter = $filter;
        return $this;
    }


    /**
     * Clear the mime filter
     * @return self
     */
    public function clearMimeFilter()
    {
        $this->mimeFilter = null;
        return $this;
    }


    /**
     * @param bool $multi
     * @return self
     */
    public function setMulti($multi)
    {
        $this->multi = $multi;
        return $this;
    }


    /**
     * @param \SitePage $page
     * @param string $varname
     */
    public function renderIntoSitePage(\SitePage $page, $varname)
    {
        $page->setFile(self::TPL, 'resources/attachzone.html');
        $page->setBlock(self::TPL, 'attachment_list', 'attachment_list_block');

        // wrap in quotes
        $filter = "'$this->mimeFilter'";

        if ($this->mimeFilter === null) {
            $filter = 'null';
        } elseif ($this->mimeFilter === false) {
            $filter = 'false';
        }

        $page->setVar(array(
            'AZ_ID'         => $this->id,
            'AZ_ID_CAMEL'   => $this->delim2camel($this->id),
            'AZ_THUMB_SIZE' => (int)$this->size,

            'AZ_ATTACH_PROMPT'     => $this->text['attachPrompt'],
            'AZ_ATTACHMENTS_LABEL' => count($this->attachments) ? $this->text['attachmentsLabel'] : '',
            'AZ_MODAL_TITLE'       => $this->text['modalTitle'],
            'AZ_MIME_FILTER'       => $filter,
            'AZ_MULTI'             => $this->multi ? 'true' : 'false',

            'attachment_list_block' => '',
        ));

        foreach ($this->attachments as $file) {

            // if it's an image, render it out, otherwise use its mime thumbnail
            $href = $file->getMimetype()->isImage()
                ? "/storage/image.php?hash={$file->getHash()}&size=square{$this->size}"
                : '/ajax/mime/' . $file->getMimetype()->getMime();

            $page->setVar(array(
                'AZ_FILE_NAME'    => $file->getFilename(),
                'AZ_FILE_HASH'    => $file->getHash(),
                'AZ_FILE_PREVIEW' => $href,
            ));

            $page->parse('attachment_list_block', 'attachment_list', true);
        }

        $page->parse($varname, self::TPL);
    }


    /**
     * @param string $delim
     * @param string $delimiters
     * @return string
     */
    protected function delim2camel($delim, $delimiters = '-_')
    {
        $xpl = preg_split("`[$delimiters]`", $delim);
        return implode('', array_map(function ($v) {
            return ucfirst($v);
        }, $xpl));
    }

} 