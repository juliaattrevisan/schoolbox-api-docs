<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\EmailDKIM;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\EmailDKIM\Mapper as EmailDKIMMapper;
use Schoolbox\Auth\KeyPairGenerator;
use Schoolbox\Mapper\Exception as MapperException;
use Symfony\Component\Mime\Crypto\DkimSigner;

/**
 * Class Controller
 *
 * Admin Email DKIM
 *
 * @package Schoolbox\Admin\EmailDKIM
 */
class Controller extends Base
{
    private EmailDKIMMapper $emailDKIMMapper;

    /**
     * Constructor
     * @param Mapper $emailDKIMMapper
     */
    public function __construct(
        EmailDKIMMapper $emailDKIMMapper
    ) {
        $this->emailDKIMMapper = $emailDKIMMapper;
    }

    /**
     * Domain list page
     *
     * @return void
     * @throws RouteNotFound
     */
    public function getDomainListPage()
    {
        $this->response = $this->responseFromTemplate(
            '@emailDKIM/index.twig',
            [
                'pageCurrent'    => $this->routeGenerator->generate('emailDKIM.getDomainListPage'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getAuthorizer(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('emailDKIM.getDomainListPage')),
            ]
        );
    }

    /**
     * Get domain list data
     *
     * @return void
     * @throws RouteNotFound
     */
    public function getDomainJsonList()
    {
        $dkims = $this->emailDKIMMapper->findAll();
        $data  = [];
        /** @var Model $dkim */
        foreach ($dkims as $dkim) {
            $data[] = [
                'id'           => $dkim->getId(),
                'domain'       => $dkim->getDomain(),
                'dkimUrl'      => $this->routeGenerator->generate('emailDKIM.getDKIMJson', [
                    'emailDKIM'   => $dkim,
                    'emailDKIMId' => $dkim->getId(),
                ]),
                'deleteUrl'    => $this->routeGenerator->generate('emailDKIM.deleteDKIM', [
                    'emailDKIM'   => $dkim,
                    'emailDKIMId' => $dkim->getId(),
                ]),
                'dateModified' => [
                    'timestamp' => $dkim->getUpdatedAt()->getUnixTimeStamp(),
                    'formatted' => $dkim->getUpdatedAt()->getShort(),
                ],
            ];
        }
        $this->response = $this->responseAsJson($data);
    }

    /**
     * Create/update domain
     *
     * @return void
     */
    public function postDomain()
    {
        $id     = $this->requestPost->get('id');
        $domain = trim($this->requestPost->get('domain') ?? '');
        if (empty($domain) || (!getmxrr($domain, $temp) && !checkdnsrr($domain, 'ANY'))) {
            $this->response = $this->responseAsJson(['error' => 'Invalid domain name'])
                ->withStatus(404);
            return;
        }

        try {
            if ($id) {
                $emailDKIM = $this->emailDKIMMapper->findById($id);
            } else {
                $emailDKIM = $this->emailDKIMMapper->createModel();
                $keyPair   = KeyPairGenerator::generate(DkimSigner::ALGO_SHA256);
                $emailDKIM->setCreatedAt(\Alaress_Date_Format::now())
                          ->setPublic($keyPair['public'])
                          ->setPrivate($keyPair['private']);
            }
            $emailDKIM->setDomain($domain)
                      ->setUpdatedAt(\Alaress_Date_Format::now());

            if (!$this->emailDKIMMapper->isUnique(['domain' => $emailDKIM->getDomain()], $emailDKIM->getId())) {
                $this->response = $this->responseAsJson(['error' => 'The domain already exists'])
                    ->withStatus(404);
                return;
            }

            $this->emailDKIMMapper->save($emailDKIM);
        } catch (MapperException $e) {
            $this->response = $this->responseAsJson(['error' => $e->getMessage()])
                ->withStatus(404);
            return;
        }
        $this->response = $this->responseAsJson([]);
    }

    /**
     * Get DKIM data for a domain
     *
     * @param Model $emailDKIM
     * @return void
     */
    public function getDKIMJson(Model $emailDKIM)
    {
        $data[] = [
            'id'             => $emailDKIM->getId(),
            'domain'         => $emailDKIM->getDomain(),
            'dkimName'       => $emailDKIM->getDKIMName(),
            'dkimValue'      => $emailDKIM->getDKIMValue(),
            'dkimValueSplit' => $emailDKIM->getDKIMValueSplit(),
            'dateModified'   => [
                'timestamp' => $emailDKIM->getUpdatedAt()->getUnixTimeStamp(),
                'formatted' => $emailDKIM->getUpdatedAt()->getShort(),
            ],
        ];
        $this->response = $this->responseAsJson($data);
    }

    /**
     * Delete domain
     *
     * @param Model $emailDKIM
     * @return void
     * @throws MapperException
     */
    public function deleteDKIM(Model $emailDKIM)
    {
        $this->emailDKIMMapper->delete($emailDKIM);
        $this->response = $this->responseAsJson([]);
    }
}
