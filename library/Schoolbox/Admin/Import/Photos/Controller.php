<?php
/**
 * Photo upload admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Import\Photos;

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
        try {
            $user = $this->userMapper->findByExternalId($this->getRequest()->post['id']);
            $user->setPortrait(null);

            $this->userMapper->save($user);
        } catch (\Schoolbox\Mapper\Exception $e) {

            /* User doesn't exist; noop
             */
        }
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

        $this->getResponse()->redirect->afterPost('/adminv2/import/photos/upload');
    }

    /**
     * Render the upload page
     */
    public function getIndex()
    {
        $surname = $this->getRequest()->query['surname'];

        /* If a letter was requested in the url, sanitise it
         */
        if ('' !== $surname) {
            $surname = preg_replace('/[^A-Za-z]/', '', $surname)[0];
        }

        /* Default to everyone whose surnames start with 'a' if the query param was bad
         */
        if ('' === $surname) {
            $surname = 'a';
        }

        /* Find all the people who have portraits - needs external id and portrait hash
         * Do a match on first letter to minimise hydrations that we throw out
         */
        $pb = $this->userMapper->getPredicateBuilder();
        $pb->notNull('synergy_id');
        $pb->notNull('portrait_hash');
        $pb->like('lastname', "{$surname}%");

        $users = $this->userMapper->findByPredicates($pb, [
            'order' => [
                'lastname'  => 'asc',
                'firstname' => 'asc'
            ]
        ]);

        $this->getResponse()->content->set(
            array(
                'template' => '@adminImportPhotos/index.twig',
                'data'     => array(
                    'allBreadcrumbs' => $this->buildBreadcrumbs($this->getRouter()),
                    'allNavigation'  => $this->buildNavigation($this->getRouter(), 'adminImportPhotos')->getAllNodes(),
                    'attachzone' => $this->getAttachZone(),
                    'users' => $users,
                    'surname' => $surname
                ),
            )
        );
    }
}
