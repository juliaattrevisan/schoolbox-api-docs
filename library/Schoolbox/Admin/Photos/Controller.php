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
use Guzzle\Common\Collection;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * @var StorageMapper $storageMapper
     */
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
     * @var UserMapper $userMapper
     */
    protected $userMapper;

    /**
     * @return UserMapper
     */
    public function getUserMapper() {
        return $this->userMapper;
    }

    /**
     * @param UserMapper $userMapper
     * @return $this
     */
    public function setUserMapper($userMapper) {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * Controller constructor.
     */
    public function __construct() {
        $this->setStorageMapper(StorageMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
    }

    /**
     * Insert an attach zone into the template
     *
     * @return array
     */
    private function getAttachZone()
    {
        $zone = new Zone('attachment');
        $zone
            ->setThumbnailSize(64)
            ->setModalTitle('Select Image')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload Image')
            ->setMimeFilter('image/jpeg')
            ->setReadOnly(false)
            ->setMulti(true);

        return $zone->getTwigContext();
    }

    /**
     * Delete existing user photo
     */
    public function postDelete()
    {
        /** @var \Schoolbox\Config\Loader $config */
        $config = $this->getDi()->get("config");

        $destination = realpath($config->get("portrait_dir")) . "/" . $this->getRequest()->post["id"] . ".jpg";

        if (file_exists($destination))
            unlink($destination);
    }

    /**
     * Upload any JPG files into the portrait cache folder overwriting whatever was previously there
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function postUpload()
    {
        /** @var \Schoolbox\Config\Loader $config */
        $config = $this->getDi()->get("config");

        if (is_array($this->getRequest()->post["attachment"])) {
            foreach ($this->getRequest()->post["attachment"] as $hash) {
                $hash_array = explode(":", $hash);
                $storageObject = $this->getStorageMapper()->findByHash($hash_array[1]);
                if ($storageObject->getMimetype()->isImage()) {
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

    /**
     * Render the upload page
     */
    public function getIndex()
    {
        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminPhotos/index.twig',
                'data'     => array(
                    'pageCurrent' => 'photos',
                    'attachzone' => $this->getAttachZone(),
                ),
            )
        );
    }

    /**
     * Render the view page
     */
    public function getView()
    {
        $allUsers = $this->getUserMapper()->findByColumn("!synergy_id", NULL)->sortBy('lastname', 'asc')->sortBy('firstname', 'asc');

        $users = [];

        /** @var \Schoolbox\User $user */
        foreach ($allUsers as $user) {
            /** @var \Schoolbox\Config\Loader $config */
            $config = $this->getDi()->get("config");

            $destination = realpath($config->get("portrait_dir")) . "/" . $user->getExternalId() . ".jpg";

            if (file_exists($destination))
                $users[] = $user;
        }

        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminPhotos/view.twig',
                'data'     => array(
                    'pageCurrent' => 'photos',
                    'users' => $users,
                ),
            )
        );
    }
}
