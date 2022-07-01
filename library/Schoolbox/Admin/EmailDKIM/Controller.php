<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\EmailDKIM;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Aura\Web\Exception\InvalidStatusCode;
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
        $this->getResponse()->content->set(
            [
                'template' => '@emailDKIM/index.twig',
                'data'     => [
                    'pageCurrent'    => $this->router->generate('emailDKIM.getDomainListPage'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('emailDKIM.getDomainListPage')),
                ],
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
                'dkimUrl'      => $this->router->generate('emailDKIM.getDKIMJson', [
                    'emailDKIM'   => $dkim,
                    'emailDKIMId' => $dkim->getId(),
                ]),
                'deleteUrl'    => $this->router->generate('emailDKIM.deleteDKIM', [
                    'emailDKIM'   => $dkim,
                    'emailDKIMId' => $dkim->getId(),
                ]),
                'dateModified' => [
                    'timestamp' => $dkim->getUpdatedAt()->getUnixTimeStamp(),
                    'formatted' => $dkim->getUpdatedAt()->getShort(),
                ],
            ];
        }
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }

    /**
     * Create/update domain
     *
     * @return void
     * @throws InvalidStatusCode
     */
    public function postDomain()
    {
        $id     = $this->request->post->get('id');
        $domain = trim($this->request->post->get('domain') ?? '');
        if (empty($domain) || (!getmxrr($domain, $temp) && !checkdnsrr($domain, 'ANY'))) {
            $this->getResponse()->content->set($this->buildJsonOutput(['error' => 'Invalid domain name']));
            $this->getResponse()->status->setCode(404);
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
                $this->getResponse()->content->set($this->buildJsonOutput(['error' => 'The domain already exists']));
                $this->getResponse()->status->setCode(404);
                return;
            }

            $this->emailDKIMMapper->save($emailDKIM);
        } catch (MapperException $e) {
            $this->getResponse()->content->set($this->buildJsonOutput(['error' => $e->getMessage()]));
            $this->getResponse()->status->setCode(404);
            return;
        }
        $this->getResponse()->content->set($this->buildJsonOutput([]));
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
        $this->getResponse()->content->set($this->buildJsonOutput($data));
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
        $this->getResponse()->content->set($this->buildJsonOutput([]));
    }
}
