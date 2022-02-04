<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Qrcode;

use Alaress\Web\Controller\Base;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\SortUsersByName;
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

    private TokenMapper $tokenMapper;

    private Jwt $tokenBuilder;

    private \External_Class $externalClass;


    /**
     * Controller constructor.
     */
    public function __construct(
        UserMapper $userMapper,
        TokenMapper $tokenMapper,
        Jwt $tokenBuilder,
        \External_Class $externalClass
    ) {
        $this->userMapper    = $userMapper;
        $this->tokenMapper   = $tokenMapper;
        $this->tokenBuilder  = $tokenBuilder;
        $this->externalClass = $externalClass;
    }


    /**
     * QR Code generation page
     *
     * @param Folder $folder
     * @throws \Aura\Router\Exception\RouteNotFound
     * @api   GET /qrcode/:folderId
     * @since 22.0
     */
    public function getListPage(Folder $folder): void
    {
        $destination = $this->request->query->get('destination') ?? null;
        $externalStudents = $this->externalClass->getStudents($folder->getCodeString());
        // Now convert those external user arrays into Schoolbox users
        $sortByNameComparator = new SortUsersByName();
        $users                = $this->userMapper->findByColumn(
            'synergy_id',
            array_column($externalStudents, 'synergy_id')
        )->sortByCallback($sortByNameComparator->getCallable());

        $tokens = $this->tokenMapper->findByColumn('user_id', $users->getPropertyValues('id'))
                                    ->indexByCallback(
                                        static function ($token) {
                                            /** @var Model $token */
                                            return $token->getUser()->getId();
                                        }
                                    );
        if (!$destination || $destination === 'homepage') {
            $params['redirect'] = sprintf('/homepage/%d', $folder->getId());
        }
        $params['jwt']      = '';

        $students = [];
        foreach ($users as $user) {
            /** @var Model|null $userJwtId */
            $userJwtId  = $tokens->offsetExists($user->getId()) ? $tokens->offsetGet($user->getId()) : null;
            $expired    = $userJwtId && $userJwtId->getExpiry() && !$userJwtId->getExpiry()->isNull() && $userJwtId->getExpiry()->isPast();
            $students[] = [
                'id'       => $user->getId(),
                'fullname' => $user->getFullName(),
                'token'    => $userJwtId && !$expired ? $this->tokenBuilder->generate(
                    $user,
                    $userJwtId->getExpiry(),
                    $userJwtId->getJwtId()
                ) : null,
                'expiry'   => $userJwtId && $userJwtId->getExpiry() && !$userJwtId->getExpiry()->isNull() ? $userJwtId->getExpiry()->getLocalShort() : null,
                'expired'  => $expired,
            ];
        }

        $url = sprintf(
            'https://%s.%s%s/?%s',
            $this->getConfigLoader()->get('site_hostname'),
            $this->getConfigLoader()->get('site_domain'),
            $this->getRouter()->generate('api.initSessionFromQueryString'),
            http_build_query($params)
        );

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@qrcode/list.twig',
                [
                    'folder'   => $folder,
                    'students' => $students,
                    'loginUrl' => $url,
                ],
                'QR Codes'
            )
        );
    }

    /**
     * @param Folder $folder
     * @api   POST /qrcode/:folderId/generate
     * @since 22.0
     */
    public function generate(Folder $folder): void
    {
        $studentIds = $this->request->post->get('students');
        $students   = $this->userMapper->findByColumn('id', array_map('intval', $studentIds));
        foreach ($students as $student) {
            try {
                $this->tokenMapper->generateForUser($student);
            } catch (Exception $e) {
                // skip for now
            }
        }
    }
}
