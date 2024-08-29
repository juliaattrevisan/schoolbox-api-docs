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
        $i->assertEquals($nonceValue, $nonce->getValue());
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
        $i->assertEquals($nonceValue, $nonce->getValue());
        $i->assertEquals($nonce->getValue(), $returnedNonce->getValue());
    }

    /**
     * @param IntegrationTester $i
     * @throws InvalidArgumentException
     */
    public function saveAndFindPotentiallyCollidingNonces(IntegrationTester $i): void
    {
        #GIVEN
        $nonceRepo = new NonceRepository(new ArrayCachePool());
        $nonceValue1 = '(';            // Stored with key 'lti1p3-nonce-openbracket'.
        $nonceValue2 = 'openbracket';  // Stored with key 'lti1p3-nonce-openbracket-'.
        $nonceValue3 = 'openbracket-'; // Stored with key 'lti1p3-nonce-openbracket--'.
        $nonce1 = new Nonce($nonceValue1);
        $nonce2 = new Nonce($nonceValue2);
        $nonce3 = new Nonce($nonceValue3);

        #WHEN
        $nonceRepo->save($nonce1);
        $nonceRepo->save($nonce2);
        $nonceRepo->save($nonce3);

        #THEN
        $i->assertEquals($nonceValue1, $nonce1->getValue());
        $i->assertEquals($nonceValue2, $nonce2->getValue());
        $i->assertEquals($nonceValue3, $nonce3->getValue());

        #WHEN
        $returnedNonce1 = $nonceRepo->find($nonceValue1);
        $returnedNonce2 = $nonceRepo->find($nonceValue2);
        $returnedNonce3 = $nonceRepo->find($nonceValue3);

        #THEN
        $i->assertEquals($nonceValue1, $returnedNonce1->getValue());
        $i->assertEquals($nonceValue2, $returnedNonce2->getValue());
        $i->assertEquals($nonceValue3, $returnedNonce3->getValue());
    }
}
