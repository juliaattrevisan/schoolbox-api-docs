<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\EmailDKIM;


use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\EmailDKIM\Mapper as EmailDKIMMapper;

/**
 * Class Controller
 *
 * Admin report export page
 *
 * @package Schoolbox\Admin\ReportExport
 */
class Controller extends Base
{
    private EmailDKIMMapper $emailDKIMMapper;


    public function __construct(
        EmailDKIMMapper $emailDKIMMapper
    ) {
        $this->emailDKIMMapper = $emailDKIMMapper;
    }

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

    public function getDomainJsonList()
    {
        $dkims = $this->emailDKIMMapper->findAll();
        $data  = [];
        /** @var Model $dkim */
        foreach ($dkims as $dkim) {
            $data[] = [
                'id'           => $dkim->getId(),
                'domain'       => $dkim->getDomain(),
                'dkimUrl'      => $this->router->generate('emailDKIM.getDKIMJson', ['emailDKIM' => $dkim, 'emailDKIMId' => $dkim->getId()]),
                'dateModified' => [
                    'timestamp' => $dkim->getUpdatedAt()->getUnixTimeStamp(),
                    'formatted' => $dkim->getUpdatedAt()->getShort(),
                ],
            ];
        }
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }

    public function postDomain()
    {
        $id     = $this->request->post->get('dkimId');
        $domain = trim($this->request->post->get('domain') ?? '');

        // TODO : validate

        if ($id) {
            $emailDKIM = $this->emailDKIMMapper->findById($id);
        } else {
            $emailDKIM = $this->emailDKIMMapper->createModel();
            $keyPair   = \Schoolbox\Auth\KeyPairGenerator::generate();
            $emailDKIM->setCreatedAt(\Alaress_Date_Format::now())
                      ->setPublic($keyPair['public'])
                      ->setPrivate($keyPair['private']);
        }
        $emailDKIM->setDomain(trim($this->request->post->get('domain')))
                  ->setUpdatedAt(\Alaress_Date_Format::now());

        $this->emailDKIMMapper->save($emailDKIM);
        $this->getResponse()->content->set($this->buildJsonOutput([]));
    }

    public function getDKIMJson(Model $emailDKIM)
    {
        $data[] = [
            'id'           => $emailDKIM->getId(),
            'domain'       => $emailDKIM->getDomain(),
            'publicKey'    => $emailDKIM->getPublic(),
            'dateModified' => [
                'timestamp' => $emailDKIM->getUpdatedAt()->getUnixTimeStamp(),
                'formatted' => $emailDKIM->getUpdatedAt()->getShort(),
            ],
        ];
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }

}
