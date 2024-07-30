<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\OpenBadge;

use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Learning\Badge\Model as Badge;
use Schoolbox\Learning\Badge\Award\Model as Award;
use Schoolbox\Url\Factory as Url;
use Scrub;

/**
 * Controller for badge requests.
 */
class Controller extends Base
{
    private const CONTEXT = 'https://w3id.org/openbadges/v2';

    public function __construct(
        private PrefixGenerator $prefixGenerator,
    ) {
    }


    /**
     * Retrieve an issuer definition as per the openbadge 2.0 spec
     * https://www.imsglobal.org/sites/default/files/Badges/OBv2p0Final/index.html
     * @api   GET /learning/badge/api/issuer.json
     * @since 23.1
     */
    public function getIssuerJson(): void
    {
        $config = $this->getConfigLoader();
        $issuerUrl = (string)(new Url($config))->buildFullyQualified()->withPath('/learning/badge/api/issuer.json');


        $issuerJson = [
            '@context'  => self::CONTEXT,
            'id'        => $issuerUrl,
            'type'      => 'Issuer',
            'name'      => $config->get('school_name'),
            'url'       => $config->get('school_address'),
            'email'     => $config->get('mail_from_address'),
        ];

        $this->response = $this->responseAsJson($issuerJson);
    }

    /**
     * Retrieve a badge definition as per the openbadge 2.0 spec
     * https://www.imsglobal.org/sites/default/files/Badges/OBv2p0Final/index.html
     * @api   GET /learning/badge/api/badge/{badgeId}.json
     * @since 23.1
     */
    public function getBadgeJson(Badge $badge): void
    {
        $config = $this->getConfigLoader();
        $fqdn = (new Url($config))->buildFullyQualified();
        $issuerUrl = (string)$fqdn->withPath('/learning/badge/api/issuer.json');

        $image = null;
        if ($badge->getIcon()) {
            $image = $this->prefixGenerator->generateWithBaseUrl((string)$fqdn->withPath('')) . $badge->getIcon();
        }
        if ($badge->getImage() && $badge->getImage()->getMimetype()->isImage()) {
            $image = (string)$fqdn->withPath('/storage/image.php')->withQuery(
                http_build_query([
                                         'hash' => $badge->getImage()->getHash(),
                                         'size' => 'constrain200',
                                     ])
            );
        }

        $badgeJson = [
            '@context'  => self::CONTEXT,
            'id'        => $fqdn->withPath('/learning/badge/api/badge/' . $badge->getId() . '.json'),
            'type'      => 'BadgeClass',
            'name'      => $badge->getName(),
            'description' => Scrub::html($badge->getBody()),
            'criteria'  => '',
            'image'     => $image,
            'issuer'       => $issuerUrl,

        ];

        $this->response = $this->responseAsJson($badgeJson);
    }

    /**
     * Retrieve badge assertion as per the openbadge 2.0 spec, this is that a person has been award/achieved a badge
     * https://www.imsglobal.org/sites/default/files/Badges/OBv2p0Final/index.html
     * @api   GET /learning/badge/api/assertion/{awardId}.json
     * @since 23.1
     */
    public function getBadgeAssertion(Award $award): void
    {
        $config = $this->getConfigLoader();
        $fqdn = (new Url($config))->buildFullyQualified();

        if (!$award->isAchievement()) {
            $problem = (new ApiProblem('Assertion has yet to be achieved'))
                ->setStatus(404)
                ->asArray();
            $this->response = $this->responseAsJson($problem)
                                   ->withStatus(404);
        }

        $assertionJson = [
            '@context'      => self::CONTEXT,
            'type'          => 'Assertion',
            'id'            => $fqdn->withPath('/learning/badge/api/assertion/' . $award->getId() . '.json'),
            'recipient' => [
                'type'      => 'email',
                'hashed'    => true,
                'identity'  => 'sha256' . hash('sha256', $award->getRecipient()->getEmail()),
            ],
            'badge'         => $fqdn->withPath('/learning/badge/api/badge/' . $award->getBadge()->getId() . '.json'),
            'verification'  => [
                'type'      => 'hosted',
            ] ,
            'issuedOn'      => $award->getAwardedAt()->toIso8601String(),
        ];

        if ($award->getExpiresAt()) {
            $assertionJson['expires'] = $award->getExpiresAt()->toIso8601String();
        }

        $this->response = $this->responseAsJson($assertionJson);
    }
}
