<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Learning\Moment\Evidence\Translator;
use Schoolbox\User;

/**
 * Controller for learning moments.
 */
class Controller extends Base
{
    /**
     * @var Mapper
     */
    private Mapper $mapper;


    /**
     * Constructor
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }


    /**
     * Render a create form for a learning moment.
     *
     * @api   GET /learning/evidence/user/{userIds}/create
     * @since 20.2
     *
     * @param Collection|User[] $users
     */
    public function getCreateForm(Collection $users): void
    {
        // For ease of rendering: filter out users which are not myself
        $sharingForSelf = false;
        $otherUsers = $users->emptyClone();
        $thisUserId = $this->getUser()->getId();
        foreach ($users as $user) {
            if ($user->getId() === $thisUserId) {
                $sharingForSelf = true;
            } else {
                $otherUsers->add($user);
            }
        }

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningMomentEvidence/form.twig',
                [
                    'users'          => $users,
                    'userIds'        => $users->getPropertyValues('id'),
                    'otherUsers'     => $otherUsers,
                    'sharingForSelf' => $sharingForSelf,
                ]
            )
        );
    }


    /**
     * Retrieve a JSON representation of a single learning moment.
     *
     * @api   GET /learning/evidence/{id}
     * @since 20.2
     */
    public function getMoment(): void
    {
    }


    /**
     * Retrieve a user's learning moments.
     *
     * @api   GET /learning/evidence/user/{userId}
     * @since 20.2
     */
    public function getMomentsForUser(User $user): void
    {
    }


    /**
     * Post a JSON representation of one or more learning moments.
     *
     * @api   POST /learning/evidence/
     * @since 20.2
     */
    public function postMoments(): void
    {
    }


//    /**
//     * Post a JSON representation of a learning moment for a user.
//     *
//     * @api   POST /learning/evidence/user/{userId}
//     * @since 20.2
//     *
//     * @param User $user
//     */
//    public function postMomentForUser(User $user): void
//    {
//        $model = $this->mapper->createModel();
//        /** @var \stdClass $requestBody */
//        $requestBody = $this->request->content->get();
//        $model
//            ->setBody($requestBody->body)
//            ->setRecipient($user)
//            ->setCreator($this->getUser());
//        $this->mapper->save($model);
//        $requestBody->id = $model->getId();
//        $this->response->content->set(
//            $this->buildJsonOutput($requestBody)
//        );
//    }


    /**
     * Post the same learning moment to multiple users at once.
     *
     * @api   POST /learning/evidence/user/{userIds}/create
     * @since 20.2
     *
     * @param Collection|User[] $users
     * @throws \Schoolbox\Mapper\Exception
     */
    public function postMomentForUsers(Collection $users): void
    {
        /** @var \stdClass $requestBody */
        $requestBody        = $this->request->content->get();
        $evidenceCollection = $this->mapper->createCollection();
        $baseModel          = (new Translator\ArrayToModel())((array)$requestBody)
            ->setCreator($this->getUser());
        foreach ($users as $user) {
            $evidenceCollection->add(
                (clone $baseModel)->setRecipient($user)
            );
        }
        $this->mapper->save($evidenceCollection);
        $this->response->content->set(
            $this->buildJsonOutput($evidenceCollection->map(new Translator\ModelToArray()))
        );
    }


    /**
     * Delete a single moment.
     *
     * @api   DELETE /learning/evidence/{id}
     * @since 20.2
     */
    public function deleteMoment(): void
    {
    }
}
