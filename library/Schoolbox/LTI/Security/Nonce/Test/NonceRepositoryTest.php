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

namespace Schoolbox\LTI\Security\Nonce\Test;

use Cache\Adapter\PHPArray\ArrayCachePool;
use OAT\Library\Lti1p3Core\Security\Nonce\Nonce;
use PHPUnit\Framework\TestCase;
use Psr\Cache\InvalidArgumentException;
use Schoolbox\LTI\Security\Nonce\NonceRepository;

/**
 * Tests:
 *  - Can the NonceRepository save and find a nonce whose value contains reserved characters?
 *  - Can the NonceRepository save and find a nonce whose value does not contain reserved characters?
 *
 * @noinspection PhpUnused
 */
final class NonceRepositoryTest extends TestCase
{
    /**
     * @throws InvalidArgumentException
     */
    public function testSaveAndFindANonceWithoutReservedCharacters(): void
    {
        #GIVEN
        $nonceRepo = new NonceRepository(new ArrayCachePool());
        $nonceValue = 'This nonce contains no reserved characters.';
        $nonce = new Nonce($nonceValue);

        #WHEN
        $nonceRepo->save($nonce);
        $returnedNonce = $nonceRepo->find($nonceValue);

        #THEN
        $this->assertEquals($nonceValue, $nonce->getValue());
        $this->assertEquals($nonce->getValue(), $returnedNonce->getValue());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testSaveAndFindANonceWithReservedCharacters(): void
    {
        #GIVEN
        $nonceRepo = new NonceRepository(new ArrayCachePool());
        $nonceValue = 'This nonce contains reserved characters ({}()\/@:).';
        $nonce = new Nonce($nonceValue);

        #WHEN
        $nonceRepo->save($nonce);
        $returnedNonce = $nonceRepo->find($nonceValue);

        #THEN
        $this->assertEquals($nonceValue, $nonce->getValue());
        $this->assertEquals($nonce->getValue(), $returnedNonce->getValue());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testSaveAndFindPotentiallyCollidingNonces(): void
    {
        #GIVEN
        $nonceRepo = new NonceRepository(new ArrayCachePool());
        $nonceValue1 = '(';
        $nonceValue2 = 'openbracket';
        $nonceValue3 = 'openbracket-';
        $nonce1 = new Nonce($nonceValue1);
        $nonce2 = new Nonce($nonceValue2);
        $nonce3 = new Nonce($nonceValue3);

        #WHEN
        $nonceRepo->save($nonce1);
        $nonceRepo->save($nonce2);
        $nonceRepo->save($nonce3);

        #THEN
        $this->assertEquals($nonceValue1, $nonce1->getValue());
        $this->assertEquals($nonceValue2, $nonce2->getValue());
        $this->assertEquals($nonceValue3, $nonce3->getValue());

        #WHEN
        $returnedNonce1 = $nonceRepo->find($nonceValue1);
        $returnedNonce2 = $nonceRepo->find($nonceValue2);
        $returnedNonce3 = $nonceRepo->find($nonceValue3);

        #THEN
        $this->assertEquals($nonceValue1, $returnedNonce1->getValue());
        $this->assertEquals($nonceValue2, $returnedNonce2->getValue());
        $this->assertEquals($nonceValue3, $returnedNonce3->getValue());
    }
}
