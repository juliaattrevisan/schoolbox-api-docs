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

    /** destinations for QR codes */
    private const DESTINATION_HOMEPAGE  = 'homepage';
    private const DESTINATION_DASHBOARD = 'dashboard';
    private const DESTINATION_PROFILE   = 'profile';

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
     * @throws MapperException
     * @since 22.0
     * @api   POST /qrcode/:folderId/generate
     */
    public function generate(Folder $folder): void
    {
        $destination = $this->request->post->get('destination') ?? null;
        $expiryMonth = $this->request->post->get('expiry') ?? null;
        $showCodes   =
            in_array($destination, [self::DESTINATION_DASHBOARD, self::DESTINATION_HOMEPAGE, self::DESTINATION_PROFILE])
            && in_array($expiryMonth, [1, 3, 6]);

        try {
            $users = $this->userAccessMapper
                ->findUsersInCategory($folder, Role::STUDENT, UserAccess::ACCESS_READ, true, false)
                ->sortByCallback((new SortUsersByName())->getCallable());
        } catch (MapperException $e) {
            $users = $this->userMapper->createCollection();
        }

        $redirects = [];
        if (!$showCodes) {
            // name list only
            $tokens           = $this->tokenMapper->createCollection();
            $selectedStudents = $this->userMapper->createCollection();
            $urlBase          = null;
            $expiry           = null;
        } else {
            $studentIds = $this->request->post->get('students') ?? [];

            $selectedStudents = $this->userMapper->findByColumn('id', array_map('intval', $studentIds))->indexBy('id');

            // Ignoring expiry on the db for now, as we're using what is provided in the request for now
            $tokens = $this->tokenMapper->findByColumnList(['user_id' => $users->getPropertyValues('id'), 'type' => Model::TYPE_QRCODE])
                                        ->indexByCallback(
                                            static function ($token) {
                                                /** @var Model $token */
                                                return $token->getUser()->getId();
                                            }
                                        );

            $redirect = null;
            if (!$destination || $destination === self::DESTINATION_HOMEPAGE) {
                $redirect = sprintf('/homepage/%d', $folder->getId());
            }
            $expiry  = (new \Alaress_Date_Format())->addMonths((int)$expiryMonth);
            foreach ($selectedStudents as $student) {
                // redirects
                if ($redirect) {
                    $redirects[$student->getId()] = $redirect;
                } elseif ($destination === self::DESTINATION_PROFILE) {
                    $redirects[$student->getId()] = sprintf('/search/user/%d', $student->getId());
                }
                // tokens
                if ($tokens->offsetExists($student->getId())) {
                    // already generated, update expiry
                    /** @var Model $token */
                    $token = $tokens->offsetGet($student->getId());
                    $token->setExpiry($expiry);
                    $this->tokenMapper->save($token);
                    continue;
                }
                try {
                    $newToken = $this->tokenMapper->generateForUser($student, $this->getUser(), $expiry, Model::TYPE_QRCODE);
                    $tokens->offsetSet($student->getId(), $newToken);
                } catch (MapperException $e) {
                    // skip for now
                }
            }

            $urlBase = (new Factory($this->getConfigLoader()))
                ->buildFullyQualified()
                ->setPath($this->router->generate('api.initSessionFromQueryString'));
        }

        $students = [];
        foreach ($users as $user) {
            $params = [];
            /** @var Model|null $userJwtId */
            $userJwtId = ($tokens->offsetExists($user->getId()) && $selectedStudents->offsetExists($user->getId())) ? $tokens->offsetGet($user->getId()) : null;
            if ($userJwtId) {
                if ($urlBase && isset($redirects[$user->getId()])) {
                    $params['redirect'] = $redirects[$user->getId()];
                }
                $params['jwt'] = $this->tokenBuilder->generate(
                    $user,
                    $expiry,
                    $userJwtId->getJwtId()
                );
            }
            $students[] = [
                'id'          => $user->getId(),
                'fullname'    => $user->getFullName(),
                'qrCodeValue' => ($userJwtId && $urlBase) ? $urlBase->setQuery($params)->toString() : null,
            ];
        }

        $payload = [
            'students' => $students,
            'expiry'   => $expiry ? $expiry->getLocalShort() : null,
        ];

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }
}
