<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Qrcode;

use Alaress\Web\Controller\Base;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;
use Schoolbox\SortUsersByName;
use Schoolbox\Url\Factory;
use Schoolbox\User\Token\Mapper as TokenMapper;
use Schoolbox\User\Token\Model;
use Schoolbox\UserMapper;

/**
 * Class Controller
 *
 * @package Schoolbox\Qrcode
 */
class Controller extends Base
{
    private UserMapper $userMapper;

    private UserAccessMapper $userAccessMapper;

    private TokenMapper $tokenMapper;

    private Jwt $tokenBuilder;

    /**
     * Controller constructor.
     */
    public function __construct(
        UserMapper $userMapper,
        UserAccessMapper $userAccessMapper,
        TokenMapper $tokenMapper,
        Jwt $tokenBuilder
    ) {
        $this->userMapper       = $userMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->tokenMapper      = $tokenMapper;
        $this->tokenBuilder     = $tokenBuilder;
    }

    /**
     * Landing page for QR Code generation
     *
     * @param Folder $folder
     * @api   GET /qrcode/:folderId
     * @since 22.0
     */
    public function getListPage(Folder $folder): void
    {
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@qrcode/list.twig',
                [
                    'folder' => $folder,
                ],
                'QR Codes'
            )
        );
    }

    /**
     * Generates student list with tokens
     *
     * @param Folder $folder
     * @throws \Aura\Router\Exception\RouteNotFound
     * @since 22.0
     * @api   POST /qrcode/:folderId/generate
     */
    public function generate(Folder $folder): void
    {
        $destination = $this->request->post->get('destination') ?? null;
        $expiryMonth = $this->request->post->get('expiry') ?? null;
        $showCodes   = in_array($destination, ['homepage', 'dashboard']) && in_array($expiryMonth, [1, 3, 6]);

        try {
            $users = $this->userAccessMapper
                ->findUsersInCategory($folder, Role::STUDENT, UserAccess::ACCESS_READ, true, false)
                ->sortByCallback((new SortUsersByName())->getCallable());
        } catch (MapperException $e) {
            $users = $this->userMapper->createCollection();
        }

        if (!$showCodes) {
            // name list only
            $tokens           = $this->tokenMapper->createCollection();
            $selectedStudents = $this->userMapper->createCollection();
            $url              = null;
            $expiry           = null;
        } else {
            $studentIds = $this->request->post->get('students') ?? [];

            $selectedStudents = $this->userMapper->findByColumn('id', array_map('intval', $studentIds))->indexBy('id');

            // Ignoring expiry on the db for now, as we're using what is provided in the request for now
            $tokens = $this->tokenMapper->findByColumn('user_id', $users->getPropertyValues('id'))
                                        ->indexByCallback(
                                            static function ($token) {
                                                /** @var Model $token */
                                                return $token->getUser()->getId();
                                            }
                                        );

            foreach ($selectedStudents as $student) {
                if ($tokens->offsetExists($student->getId())) {
                    // already generated
                    continue;
                }
                try {
                    $newToken = $this->tokenMapper->generateForUser($student, $this->getUser());
                    $tokens->offsetSet($student->getId(), $newToken);
                } catch (MapperException $e) {
                    // skip for now
                }
            }

            $expiry = (new \Alaress_Date_Format())->addMonths((int)$expiryMonth);
            if (!$destination || $destination === 'homepage') {
                $params['redirect'] = sprintf('/homepage/%d', $folder->getId());
            }
            $params['jwt'] = '';
            $url           = (new Factory($this->getConfigLoader()))
                ->buildFullyQualified()
                ->setPath($this->router->generate('api.initSessionFromQueryString'))->setQuery($params);
        }

        $students = [];
        foreach ($users as $user) {
            /** @var Model|null $userJwtId */
            $userJwtId  = ($tokens->offsetExists($user->getId()) && $selectedStudents->offsetExists($user->getId())) ? $tokens->offsetGet($user->getId()) : null;
            $students[] = [
                'id'       => $user->getId(),
                'fullname' => $user->getFullName(),
                'token'    => $userJwtId ? $this->tokenBuilder->generate(
                    $user,
                    $expiry,
                    $userJwtId->getJwtId()
                ) : null,
            ];
        }

        $payload = [
            'students' => $students,
            'loginUrl' => $url ? $url->toString() : '',
            'expiry'   => $expiry ? $expiry->getLocalShort() : null,
        ];

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }
}
