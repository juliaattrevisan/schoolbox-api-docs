<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Token;

use Alaress\Web\Controller\Base;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;

/**
 * Controller which contains user token related endpoints.
 */
class Controller extends Base
{
    /**
     * @var Jwt
     */
    private Jwt $tokenBuilder;

    private Mapper $tokenMapper;

    /**
     * Constructor
     *
     * @param Jwt    $tokenBuilder
     * @param Mapper $tokenMapper
     */
    public function __construct(Jwt $tokenBuilder, Mapper $tokenMapper)
    {
        $this->tokenBuilder = $tokenBuilder;
        $this->tokenMapper  = $tokenMapper;
    }


    /**
     * Retrieve an auth token for the current user.
     *
     * @api   /user/token
     * @since 20.1
     */
    public function getTokenForSelf()
    {
        $this->getTokenForUser($this->getUser());
    }

    /**
     * Retrieve an auth token for a specified user.
     *
     * @param User $user
     * @throws Exception
     * @api   /user/token/{userId}
     * @since 20.1
     */
    public function getTokenForUser(User $user): void
    {
        $jwtIdToUse = $this->request->post->get('jwtIdToUse') ?? 'no';
        $userJwtId  = null;
        $showExisting = false;
        switch ($jwtIdToUse) {
            case 'new':
                // delete existing
                $this->tokenMapper->deleteByColumn('user_id', $user->getId());
                // generate
                $jwtId     = $this->tokenMapper->generateUuid();
                $userJwtId = $this->tokenMapper->createModel()
                                               ->setUser($user)
                                               ->setJwtId($jwtId);
                $this->tokenMapper->save($userJwtId);
                $showExisting = true;
                break;
            case 'existing':
                try {
                    $userJwtId = $this->tokenMapper->findOneByColumn('user_id', $user->getId());
                    $showExisting = true;
                } catch (Exception $e) {
                    // this is not expected to happen, but if happens fall back to no jwtId
                }
                break;
            case 'no':
            default:
        }

        // JSON response:
        // generate from JWT generator
        $this->response->content->set(
            $this->buildJsonOutput(
                [
                    'token' => $this->tokenBuilder->generate(
                        $user,
                        $userJwtId ? $userJwtId->getExpiry() : null,
                        $userJwtId ? $userJwtId->getJwtId() : null
                    ),
                    'showExisting' => $showExisting,
                ]
            )
        );
    }
}
