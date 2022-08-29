<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment;

use Alaress_Date_Format;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as StorageModel;
use UnexpectedValueException;

/**
 * Class Zone
 *
 * Used specifically for use with Storage.
 * If you need a less-specific FileInterface implementation, use GenericZone.
 *
 * @package Schoolbox\Resource\Attachment
 */
class Zone
{

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

    /** @var bool */
    protected $buttonMode;

    /**
     * Does this attachzone use the modal?
     */
    private bool $useModal = true;

    /**
     * Does this attachzone's file upload have the capture attribute
     * (for photo/video/audio capture)?
     */
    private bool $capture = false;

    /**
     * This attachzone's icon class: if left empty, the attachzone will
     * determine a default.
     */
    private ?string $iconClass = null;

    /** @var StorageMapper */
    protected $mapper;


    /**
     * One of keys from Attachment/Context/Factory.php options array
     *
     * @var string|null
     */
    private ?string $filesystemContext = null;

    /**
     * The expiry date to use for any files uploaded to this attachzone, or null
     * if not provided.
     */
    private ?Alaress_Date_Format $expiryDate = null;

    /**
     * @param string $zoneId
     * @param Mapper $mapper
     * @throws UnexpectedValueException
     */
    public function __construct($zoneId, $mapper = null)
    {
        $this->id    = $zoneId;
        $this->size  = 64;
        $this->multi = false;
        $this->text  = [
            'attachPrompt'     => 'Attach files',
            'attachmentsLabel' => 'Attachments',
            'modalTitle'       => 'Select files',
            'fileVar'          => 'attachment[]',
        ];

        $this->mapper      = $mapper ?: StorageMapper::getInstance();
        $this->mimeFilter  = '';
        $this->attachments = [];
        $this->readOnly    = false;
        $this->buttonMode  = false;
    }


    /**
     * Attach a list of files to this attachzone.
     *
     * @param iterable $hashlist Each value is either a string (hash) or an
     *                           `IStorage` object
     * @return self
     */
    public function attachFiles(iterable $hashlist)
    {
        foreach ($hashlist as $hash) {
            try {
                if ($hash instanceof IStorage) {
                    $this->attachments[$hash->getHash()] = $hash;
                } else {
                    $this->attachments[$hash] = $this->findFile($hash);
                }
            } catch (Exception $e) {
                // file given doesn't exist in Storage
            }
        }
        return $this;
    }


    /**
     * @param string $hash
     * @return StorageModel
     * @throws Exception if the file couldn't be found
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
    public function setReadOnly($readOnly)
    {
        $this->readOnly = $readOnly;
        return $this;
    }


    /**
     * @param bool $useModal
     * @return static
     */
    public function setUseModal(bool $useModal): self
    {
        $this->useModal = $useModal;
        return $this;
    }


    /**
     * @param bool $capture
     * @return static
     */
    public function setCapture(bool $capture): self
    {
        $this->capture = $capture;
        return $this;
    }


    /**
     * @param string|null $iconClass
     * @return static
     */
    public function setIconClass(?string $iconClass): self
    {
        $this->iconClass = $iconClass;
        return $this;
    }


    /**
     * @param Alaress_Date_Format|null $expiryDate
     * @return static
     */
    public function setExpiryDate(?Alaress_Date_Format $expiryDate): self
    {
        $this->expiryDate = $expiryDate;
        return $this;
    }


    /**
     * Return an array that represents a context that can be passed into Twig
     *
     * @return array
     */
    public function getTwigContext()
    {

        // wrap in quotes
        $filter = "'$this->mimeFilter'";

        if ($this->mimeFilter === null) {
            $filter = 'null';
        } elseif ($this->mimeFilter === false) {
            $filter = 'false';
        }


        $context = [
            'title'      => $this->text['modalTitle'],
            'id'         => $this->id,
            'camelId'    => $this->delim2camel($this->id),
            'label'      => count($this->attachments) ? $this->text['attachmentsLabel'] : '',
            'prompt'     => $this->text['attachPrompt'],
            'fileVar'    => $this->text['fileVar'],
            'mime'       => $filter,
            'readonly'   => $this->readOnly,
            'multi'      => $this->multi,
            'mode'       => $this->buttonMode ? 'button' : 'normal',
            'size'       => $this->size,
            'useModal'   => $this->useModal,
            'capture'    => $this->capture,
            'iconClass'  => $this->iconClass,
            'fs'         => $this->filesystemContext,
            'expiryDate' => ($this->expiryDate && !$this->expiryDate->isNull())
                ? $this->expiryDate->toRfc3339String()
                : null,
        ];

        foreach ($this->attachments as $storage) {
            $vars = $this->deriveSitePageVars($storage);

            $context['attachments'][] = [
                'name'    => $vars['AZ_FILE_NAME'],
                'hash'    => $vars['AZ_FILE_HASH'],
                'preview' => $vars['AZ_FILE_PREVIEW'],
                'isImage' => $vars['AZ_FILE_IS_IMAGE'],
            ];
        }

        return $context;
    }


    /**
     * Twig it into SitePage
     *
     * @param \SitePage $page
     * @param string    $varname
     */
    public function renderIntoSitePage(\SitePage $page, $varname)
    {
        // this is now just a twig shim
        $page->setVar($varname, $page->getTwig()->render('sub/attachzone.twig', ['attachzone' => $this->getTwigContext()]));
    }


    /**
     * Returns some PHPlibs, based on the File given
     *
     * @param IStorage $file
     * @return array
     */
    protected function deriveSitePageVars(IStorage $file): array
    {
        // if it's an image, render it out, otherwise use its mime thumbnail
        if ($file->getMimetype()->isImage()) {
            $href    = "/storage/image.php?hash={$file->getHash()}&size=square{$this->size}";
            $isImage = true;
        } else {
            $href    = '/ajax/mime/' . $file->getMime();
            $isImage = false;
        }

        return [
            'AZ_FILE_NAME'     => $file->getFilename(),
            'AZ_FILE_HASH'     => NodeFactory::STORE . ':' . $file->getHash(),
            'AZ_FILE_PREVIEW'  => $href,
            'AZ_FILE_IS_IMAGE' => $isImage,
        ];
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


    /**
     * @param bool $bool
     * @return $this
     */
    public function setButtonMode($bool)
    {
        $this->buttonMode = $bool;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getFilesystemContext(): ?string
    {
        return $this->filesystemContext;
    }


    /**
     * @param string|null $filesystemContext
     * @return $this
     */
    public function setFilesystemContext(?string $filesystemContext): self
    {
        $this->filesystemContext = $filesystemContext;

        return $this;
    }
}
