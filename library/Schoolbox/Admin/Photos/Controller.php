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
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\UserMapper;
use Schoolbox\Import\ImportNavigation;

class Controller extends Base
{
    use ImportNavigation;

    /**
     * @var StorageMapper $storageMapper
     */
    protected $storageMapper;

    /**
     * @var UserMapper $userMapper
     */
    protected $userMapper;

    /**
     * Controller constructor.
     *
     * @param StorageMapper $storageMapper
     * @param UserMapper    $userMapper
     */
    public function __construct(Storage\Mapper $storageMapper, UserMapper $userMapper) {
        $this->storageMapper = $storageMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Insert an attach zone into the template
     *
     * @return array
     * @throws \UnexpectedValueException
     */
    private function getAttachZone()
    {
        $zone = new Zone('attachment');
        $zone
            ->setThumbnailSize(64)
            ->setModalTitle('Select Image')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload Image')
            ->setMimeFilter('image/*')
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
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     */
    public function postUpload()
    {
        $post = $this->getRequest()->post;

        if (\is_array($post['attachment'])) {

            /* Remove any bad data SA-8244
             */
            $attachments = array_filter($post['attachment']);

            if (\count($attachments)) {
                $portraits = (new CollectorFactory())->build($attachments)->getAll();

                /** @var \Schoolbox\Resource\Storage\Model $portrait */
                foreach ($portraits as $portrait) {
                    if ($portrait->getMimetype()->isImage()) {
                        try {
                            $user = $this->userMapper->findByExternalId($portrait->getFilename());
                            $user->setPortrait($portrait);
                            $this->userMapper->save($user);
                        } catch (\Schoolbox\Mapper\Exception $e) {

                            /* User doesn't exist; noop
                             * TODO: Log it somewhere meaningful?
                             */
                        }
                    }
                }
            }
        }

        $this->getResponse()->redirect->afterPost('/adminv2/photos');
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
                    'pageCurrent' => 'import',
                    'allBreadcrumbs' => $this->buildBreadcrumbs($this->getRouter())->getAllNodes(),
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
        if ($this->getRequest()->query["surname"] == null)
            $surname = "a";
        else
            $surname = $this->getRequest()->query["surname"];

        $pb = $this->userMapper->getPredicateBuilder();
        $pb->notNull('synergy_id');
        $options = ['order' => [
            'lastname'  => 'asc',
            'firstname' => 'asc'
        ] ];
        $allUsers = $this->userMapper->findByPredicates($pb, $options);

        $users = [];

        /** @var \Schoolbox\Config\Loader $config */
        $config = $this->getDi()->get("config");

        /** @var \Schoolbox\User $user */
        foreach ($allUsers as $user) {

            if (strtolower(substr($user->getLastname(), 0, 1)) == $surname) {
                $destination = realpath($config->get("portrait_dir")) . "/" . $user->getExternalId() . ".jpg";

                if (file_exists($destination))
                    $users[] = $user;
            }
        }

        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminPhotos/view.twig',
                'data'     => array(
                    'allBreadcrumbs' => $this->buildBreadcrumbs($this->getRouter())->getAllNodes(),
                    'pageCurrent' => 'import',
                    'users' => $users,
                    'surname' => $surname
                ),
            )
        );
    }
}
