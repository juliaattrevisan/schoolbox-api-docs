<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Import\Photos;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Import\Navigation\Direct;
use Schoolbox\Admin\Import\Navigation\INavigation;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\UserMapper;

/**
 * Class Controller
 *
 * Photo upload admin page
 *
 * @package Schoolbox\Admin\Import\Photos
 */
class Controller extends Base
{
    /**
     * @var StorageMapper $storageMapper
     */
    protected $storageMapper;


    /**
     * @var UserMapper $userMapper
     */
    protected $userMapper;


    /**
     * @var INavigation
     */
    protected $navigation;


    /**
     * Controller constructor.
     *
     * @param StorageMapper $storageMapper
     * @param UserMapper    $userMapper
     * @param IConfigLoader $configLoader
     */
    public function __construct(Storage\Mapper $storageMapper, UserMapper $userMapper, IConfigLoader $configLoader)
    {
        $this->storageMapper = $storageMapper;
        $this->userMapper = $userMapper;

        $this->navigation = new Direct($configLoader);
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
            $user = $this->userMapper->findByExternalId($this->requestPost['id']);
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
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function postUpload()
    {
        $post = $this->requestPost;

        $results = [
            'success' => [],
            'error'   => [],
        ];
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
                            $results['success'][] = $portrait->getFilename();
                        } catch (\Schoolbox\Mapper\Exception $e) {
                            $results['error'][$portrait->getBasename()] = $e->getMessage();
                            /* User doesn't exist; noop
                             * TODO: Log it somewhere meaningful?
                             */
                        }
                    }
                }
            }
        }
        if ($this->isJsonRequest()) {
            $this->response = $this->responseAsJson($results)->withStatus(200);
            return;
        }

        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminImportPhotos.getIndex'));
    }


    /**
     * Render the upload page
     */
    public function getIndex()
    {
        $surname = $this->requestQuery['surname'] ?? '';

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
        $pb = $this->userMapper->getPredicateBuilder()
            ->notNull('synergy_id')
            ->notNull('portrait_hash')
            ->like('lastname', "{$surname}%");

        $users = $this->userMapper->findByPredicates($pb, [
            'order' => [
                'lastname'  => 'asc',
                'firstname' => 'asc'
            ]
        ]);

        $this->response = $this->responseFromTemplate(
            '@adminImportPhotos/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminImportPhotos.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminImportPhotos.getIndex')),
                'allNavigation'  => $this->navigation->buildNavigation($this->routeGenerator, 'adminImportPhotos')->getAllNodes(),
                'attachzone' => $this->getAttachZone(),
                'users' => $users,
                'surname' => $surname
            ],
        );
    }
}
