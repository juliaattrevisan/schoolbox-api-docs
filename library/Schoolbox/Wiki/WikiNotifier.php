<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Wiki;

use Alaress\Async\Notification\Instance\Builder;
use Monolog\Logger;
use Schoolbox\Collection;
use Schoolbox\Feed\FeedableMapperInterface;
use Schoolbox\Feed\SourceInterface;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\CategoryParam;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundle;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class WikiNotifier extends AbstractModuleNotifier implements SourceInterface
{

    const TRIGGER_CREATED = 'created';

    /** @var RevisionMapper */
    protected $revisionMapper;

    /** @var UserMapper */
    protected $userMapper;

    /** @var Folder\Mapper */
    protected $folderMapper;

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_WIKI;
    }


    /**
     * @param array $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies['revisionMapper'])) {
            $dependencies['revisionMapper'] = RevisionMapper::getInstance();
        }
        if (empty($dependencies['userMapper'])) {
            $dependencies['userMapper'] = UserMapper::getInstance();
        }
        if (empty($dependencies['folderMapper'])) {
            $dependencies['folderMapper'] = Folder\Mapper::getInstance();
        }

        parent::loadDependencies($dependencies);
    }


    /**
     * @param RevisionMapper $revisionMapper
     * @return static
     */
    protected function setRevisionMapper($revisionMapper)
    {
        $this->revisionMapper = $revisionMapper;
        return $this;
    }


    /**
     * @param UserMapper $userMapper
     * @return static
     */
    protected function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }


    /**
     * @param Folder\Mapper $folderMapper
     * @return static
     */
    protected function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }

    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        // we only care about creation
        if ($bundle->getMethod() == Mapper::METHOD_CREATE) {
            /** @var Wiki $wiki */
            $wiki = $bundle->getEvent()->getObject();
            $bundle->attach(self::TRIGGER_CREATED, $wiki->getCreated()->getUnixTimeStamp());
        }
    }

    /**
     * @inheritdoc
     */
    public function getFeed($context, User $user, $limit = self::HARD_LIMIT, $offset = 0)
    {
        $c = new Collection();

        // be sure the target method exists
        if (!$this->mapper instanceof FeedableMapperInterface) {
            return $c;
        }

        /** @var Wiki[]|Collection $wikis */
        $wikis = $this->mapper->findForFeed($context, $limit, $offset);

        foreach ($wikis as $wiki) {
            // Wiki creator is the author of first (not latest) revision
            try {
                $first  = $this->revisionMapper->findOneByColumn('wikiID', $wiki->getId(), ['order' => ['created' => 'asc']]);
                $author = $first->getUser();
                $c      = $c->merge(
                    new Collection(
                        $this->notifyCreated(
                            $user->getRole()->getType(),
                            $wiki,
                            $author
                        )->getAll($wiki->getCreated()->getUnixTimeStamp())
                    )
                );
            } catch (\Schoolbox\Mapper\Exception $e) {
                //noop
            }
        }

        return $c;
    }

    /**
     * Returns the metadata pertaining to this source.
     * Useful to RSS
     *
     * @return mixed
     */
    public function getChannelMetadata()
    {
        return null;
    }



    /* ASYNC NOTIFY METHODS */

    /**
     * @param string $roleType
     * @param Wiki   $wiki
     * @param User   $author
     * @return Envelope
     */
    protected function notifyCreated($roleType, $wiki, $author)
    {
        $envelope = new Envelope();

        $wikiId   = $wiki->getId();
        $wikiSlug = $wiki->getSlug();

        $authorId = $author->getId();

        $folder   = $this->folderMapper->findById($wiki->getCategory()->getId());
        $folderId = $folder->getId();

        $bob = new Builder();

        $bob->setTitle(':author created a new wiki in :catname')
            ->setParams([
                'author'  => new UserParam($author),
                'catname' => new CategoryParam($folder),
            ])
            ->setHref("/wiki/$folderId/$wikiSlug")
            ->setIcon($this->getUserIcon($author))
            ->setSeverity(Logger::INFO)
            ->setTopics(["wiki.$wikiId.created"])
            ->setFilter(['roletype' => $roleType])
            ->setNamespace($this->getSlug());

        $envelope->broadcastToCategory($folderId, UserAccess::ACCESS_READ, $bob->build());

        return $envelope;
    }

    /**
     * @param Wiki  $wiki
     * @param User  $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyStudentCreated($wiki, $author, $diff = [])
    {
        return $this->notifyCreated(Role::STUDENT, $wiki, $author);
    }

    /**
     * @param Wiki  $wiki
     * @param User  $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyStaffCreated($wiki, $author, $diff = [])
    {
        return $this->notifyCreated(Role::STAFF, $wiki, $author);
    }

    /**
     * @param Wiki  $wiki
     * @param User  $author
     * @param array $diff
     * @return Envelope
     */
    public function notifyParentCreated($wiki, $author, $diff = [])
    {
        return $this->notifyCreated(Role::PARENT, $wiki, $author);
    }
}
