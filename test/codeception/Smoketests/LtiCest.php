<?php

/**
 * This tests whether the replacement NonceRepository class allows save and find of nonces whose values contain
 * reserved characters.
 *
 * To test whether the replaced class passes this test:
 *  - Comment out 'use Schoolbox\LTI\Security\Nonce\NonceRepository;'
 *  - Uncomment 'use OAT\Library\Lti1p3Core\Security\Nonce\NonceRepository;'
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Tests\Smoketests;

use Cache\Adapter\PHPArray\ArrayCachePool;
use OAT\Library\Lti1p3Core\Security\Nonce\Nonce;
use Psr\Cache\InvalidArgumentException;
use Schoolbox\LTI\Security\Nonce\NonceRepository;
use Tests\Support\IntegrationTester;

/**
 * Tests:
 *  - Can the NonceRepository save and find a nonce whose value contains reserved characters?
 *  - Can the NonceRepository save and find a nonce whose value does not contain reserved characters?
 *
 * @noinspection PhpUnused
 */
class LtiCest
{
    /**
     * Hook method to execute prior to each test
     * @param IntegrationTester $i
     */
    public function _before(IntegrationTester $i): void
    {
    }

    /**
     * @param IntegrationTester $i
     * @throws InvalidArgumentException
     */
    public function saveAndFindANonceWithoutReservedCharacters(IntegrationTester $i): void
    {
        #GIVEN
        $nonceRepo = new NonceRepository(new ArrayCachePool());
        $nonceValue = 'This nonce contains no reserved characters.';
        $nonce = new Nonce($nonceValue);

        #WHEN
        $nonceRepo->save($nonce);
        $returnedNonce = $nonceRepo->find($nonceValue);

        #THEN
        $i->assertEquals($nonce->getValue(), $returnedNonce->getValue());
    }

    /**
     * @param IntegrationTester $i
     * @throws InvalidArgumentException
     */
    public function saveAndFindANonceWithReservedCharacters(IntegrationTester $i): void
    {
        #GIVEN
        $nonceRepo = new NonceRepository(new ArrayCachePool());
        $nonceValue = 'This nonce contains reserved characters ({}()\/@:).';
        $nonce = new Nonce($nonceValue);

        #WHEN
        $nonceRepo->save($nonce);
        $returnedNonce = $nonceRepo->find($nonceValue);

        #THEN
        $i->assertEquals($nonce->getValue(), $returnedNonce->getValue());
    }
}
