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
use Schoolbox\Mapper;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use UnexpectedValueException;

/**
 * Class Zone
 *
 * Used specifically for use with Storage.
 * If you need a less-specific FileInterface implementation, use GenericZone.
 *
 * @package Schoolbox\Resource\Attachment
 */
class Zone {

    const TPL = 'azTpl';

    /** @var string */
    protected $id;

    /** @var int */
    protected $size;

    /** @var string */
    protected $position;

    /** @var IStorage[] */
    protected $attachments;

    /** @var array */
    protected $text;

    /** @var string */
    protected $mimeFilter;

    /** @var bool */
    protected $multi;

    /** @var bool */
    protected $readOnly;

    /** @var StorageMapper */
    protected $mapper;


    /**
     * @param string $zoneId
     * @param Mapper $mapper
     * @throws UnexpectedValueException
     */
    public function __construct($zoneId, $mapper = null)
    {
        $this->id       = $zoneId;
        $this->size     = 64;
        $this->multi    = false;
        $this->text     = array(
            'attachPrompt'     => 'Attach files',
            'attachmentsLabel' => 'Attachments',
            'modalTitle'       => 'Select files',
        );

        $this->mapper      = $mapper ?: StorageMapper::getInstance();
        $this->mimeFilter  = '';
        $this->attachments = array();
        $this->readOnly    = false;
    }


    /**
     * @param array $hashlist
     * @return self
     */
    public function attachFiles(array $hashlist)
    {
        foreach ($hashlist as $hash) {
            try {
                $this->attachments[$hash] = $this->findFile($hash);
            } catch (Exception $e) {
                // file given doesn't exist in Storage
            }
        }
        return $this;
    }


    /**
     * @param string $hash
     * @return IStorage
     */
    protected function findFile($hash)
    {
        return $this->mapper->findByHash($hash);
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
     * The text to appear either in the attachzone or the fallback button.
     * If dropzone activates, note that 'Click or drag here to ' will be prepended to lcfirst($text).
     *
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
    public function setFileVar($text)
    {
        $this->text['fileVar'] = $text;
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
     * @param bool $readOnly
     * @return $this
     */
    public function setReadOnly($readOnly) {
        $this->readOnly = $readOnly;
        return $this;
    }


    public function getTwigContext() {

        // wrap in quotes
        $filter = "'$this->mimeFilter'";

        if ($this->mimeFilter === null) {
            $filter = 'null';
        } elseif ($this->mimeFilter === false) {
            $filter = 'false';
        }


        $context = array(
            'title'    => $this->text['modalTitle'],
            'id'       => $this->id,
            'camelId'  => $this->delim2camel($this->id),
            'label'    => count($this->attachments) ? $this->text['attachmentsLabel'] : '',
            'prompt'   => $this->text['attachPrompt'],
            'mime'     => $filter,
            'readonly' => $this->readOnly,
            'multi'    => $this->multi,
            'size'     => $this->size
        );

        foreach ($this->attachments as $storage) {

            $vars = $this->deriveSitePageVars($storage);

            $context['attachments'][] = array(
                'name'    => $vars['AZ_FILE_NAME'],
                'hash'    => $vars['AZ_FILE_HASH'],
                'preview' => $vars['AZ_FILE_PREVIEW'],
                'isImage' => $vars['AZ_FILE_IS_IMAGE'],
            );
        }

        return $context;
    }


    /**
     * @param \SitePage $page
     * @param string $varname
     */
    public function renderIntoSitePage(\SitePage $page, $varname)
    {
        $page->setFile(self::TPL, 'resources/attachzone.html');
        $page->setBlock(self::TPL, 'can_drop', 'can_drop_block');
        $page->setBlock(self::TPL, 'attachment_list', 'attachment_list_block');
        $page->setBlock('attachment_list', 'attachment_editable', 'attachment_editable_block');

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
            'AZ_FILE_VAR'   => $this->text['fileVar'] ? $this->text['fileVar'] : 'attachment[]',
            'AZ_ATTACH_PROMPT'     => $this->text['attachPrompt'],
            'AZ_ATTACHMENTS_LABEL' => count($this->attachments) ? $this->text['attachmentsLabel'] : '',
            'AZ_MODAL_TITLE'       => $this->text['modalTitle'],
            'AZ_MIME_FILTER'       => $filter,
            'AZ_MULTI'             => $this->multi ? 'true' : 'false',

            'attachment_list_block' => '',
        ));

        if ($this->readOnly) {
            $page->setVar('can_drop_block', '');
        } else {
            $page->parse('can_drop_block', 'can_drop');
        }

        foreach ($this->attachments as $file) {
            $page->setVar('attachment_editable_block', '');
            $page->setVar($this->deriveSitePageVars($file));

            if (!$this->readOnly) {
                $page->parse('attachment_editable_block', 'attachment_editable', false);
            }


            $page->parse('attachment_list_block', 'attachment_list', true);
        }

        $page->parse($varname, self::TPL);
    }


    /**
     * Returns some PHPlibs, based on the File given
     *
     * @param IStorage $file
     * @return array
     */
    protected function deriveSitePageVars($file)
    {
        // if it's an image, render it out, otherwise use its mime thumbnail
        if ($file->getMimetype()->isImage()) {
            $href = "/storage/image.php?hash={$file->getHash()}&size=square{$this->size}";
            $isImage = true;
        } else {
            $href = '/ajax/mime/' . $file->getMime();
            $isImage = false;
        }

        return array(
            'AZ_FILE_NAME'     => $file->getFilename(),
            'AZ_FILE_HASH'     => NodeFactory::STORE . ':' . $file->getHash(),
            'AZ_FILE_PREVIEW'  => $href,
            'AZ_FILE_IS_IMAGE' => $isImage,
        );

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