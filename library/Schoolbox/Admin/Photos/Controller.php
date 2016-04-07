<?php
/**
 * Photo upload admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Photos;

use Alaress\Web\Controller\Base;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Attachment\Zone;

class Controller extends Base
{
    protected $storageMapper;

    /**
     * @return StorageMapper
     */
    public function getStorageMapper() {
        return $this->storageMapper;
    }

    /**
     * @param StorageMapper $storageMapper
     * @return $this
     */
    public function setStorageMapper($storageMapper) {
        $this->storageMapper = $storageMapper;
        return $this;
    }

    /**
     * Controller constructor.
     */
    public function __construct() {
        $this->setStorageMapper(StorageMapper::getInstance());
    }

    /**
     * Insert an attach zone into the template
     *
     * @param Collection|PastoralAttachmentModel $attachments
     * @return array
     */
    private function getAttachZone($attachments = null)
    {
        $zone = new Zone('attachment');
        $zone
            ->setThumbnailSize(64)
            ->setModalTitle('Select Image')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload Image')
            ->setMimeFilter('*/*')
            ->setReadOnly(false)
            ->setMulti(true);

        if ($attachments != null)
            foreach ($attachments as $attachment)
                $zone->attachFiles(array($attachment->getHash()));

        return $zone->getTwigContext();
    }

    public function postUpload()
    {
        // Upload any JPG files into the portrait cache folder overwriting whatever was previously there

        /** @var \Schoolbox\Config\Loader $config */
        $config = $this->getDi()->get("config");

        if (is_array($this->getRequest()->post["attachment"])) {
            foreach ($this->getRequest()->post["attachment"] as $hash) {
                $hash_array = explode(":", $hash);
                $storageObject = $this->getStorageMapper()->findByHash($hash_array[1]);
                if ($storageObject->getMime() == "image/jpeg") {
                    if (strtolower($storageObject->getExtension()) == "jpg") {
                        $destination = realpath($config->get("portrait_dir")) . "/" . $storageObject->getFilename() . ".jpg";

                        if (file_exists($destination))
                            unlink($destination);

                        file_put_contents($destination, $storageObject->read());
                    }
                }
            }
        }

        $this->getResponse()->redirect->afterPost("/adminv2/photos");
    }

    public function getIndex()
    {
        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminPhotos/index.twig',
                'data'     => array(
                    'pageCurrent' => basename($this->getRequest()->query->get('page')),
                    'attachzone' => $this->getAttachZone(),
                ),
            )
        );
    }
}
