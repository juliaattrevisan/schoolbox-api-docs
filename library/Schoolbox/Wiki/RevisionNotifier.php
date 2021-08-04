<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Wiki;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
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
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\User;

class RevisionNotifier extends AbstractModuleNotifier implements SourceInterface
{

    const TRIGGER_REVISED = 'revised';

    /** @var WikiMapper */
    protected $wikiMapper;

    /** @var FolderMapper */
    protected $catMapper;

    /**
     * Instantiate all your dependencies within yourself.
     *
     * @param mixed $dependencies
     * @return void
     */
    public function loadDependencies($dependencies = null)
    {
        $this->wikiMapper = WikiMapper::getInstance();
        $this->catMapper  = FolderMapper::getInstance();
        parent::loadDependencies($dependencies);
    }

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_WIKI;
    }


    /**
     * @param TriggerBundle $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        // we only care about creation
        if ($bundle->getMethod() == Mapper::METHOD_CREATE) {
            /** @var Revision $revision */
            $revision = $bundle->getEvent()->getObject();
            $bundle->attach(self::TRIGGER_REVISED, $revision->getCreated()->getUnixTimeStamp());
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

        /** @var Collection|Revision[] $revisions */
        $revisions = $this->mapper->findForFeed($context, $limit, $offset);

        $c = new Collection();
        foreach ($revisions as $revision) {
            $c = $c->merge(
                new Collection(
                    $this->notifyRevised(
                        $user->getRole()->getType(),
                        $revision,
                        $revision->getUser()
                    )->getAll($revision->getCreated()->getUnixTimeStamp())
                )
            );
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
     * @param string   $roleType
     * @param Revision $revision
     * @param User     $author
     * @return Envelope
     */
    public function notifyRevised($roleType, $revision, $author)
    {

        /** @var Revision $revision */
        $revision   = $this->mapper->findById($revision->getId());
        $revisionId = $revision->getId();

        $wiki   = $revision->getWiki();
        $wikiId = $wiki->getId();

        $cat        = $wiki->getCategory();
        $categoryId = $cat->getId();

        $href = "/wiki/revision/$wikiId/$revisionId";

        $envelope = new Envelope();
        $bob      = new Builder();

        $bob->setTitle(':author revised the wiki :title in :folder')
            ->setHref($href)
            ->setIcon($this->getUserIcon($author))
            ->addTopic("wiki.$revisionId.created")
            ->setSeverity(Logger::INFO)
            ->setNamespace($this->getSlug())
            ->setParams([
                'author' => new UserParam($author),
                'title'  => new Param($revision->getHeading(), $href),
                'folder' => new CategoryParam($cat),
            ])
            ->setFilter([
                'roletype' => $roleType,
            ]);

        $envelope->broadcastToCategory($categoryId, UserAccess::ACCESS_READ, $bob->build());

        return $envelope;
    }

    /**
     * @param Revision $revision
     * @param User     $author
     * @param array    $diff
     * @return Envelope
     */
    public function notifyStudentRevised($revision, $author, $diff = [])
    {
        return $this->notifyRevised(Role::STUDENT, $revision, $author);
    }

    /**
     * @param Revision $revision
     * @param User     $author
     * @param array    $diff
     * @return Envelope
     */
    public function notifyStaffRevised($revision, $author, $diff = [])
    {
        return $this->notifyRevised(Role::STAFF, $revision, $author);
    }

    /**
     * @param Revision $revision
     * @param User     $author
     * @param array    $diff
     * @return Envelope
     */
    public function notifyParentRevised($revision, $author, $diff = [])
    {
        return $this->notifyRevised(Role::PARENT, $revision, $author);
    }
}
