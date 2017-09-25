<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami\Test;

use GuzzleHttp\Client;
use Schoolbox\Config\TestLoader;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\InlineGrader\Kami\Connector;
use Schoolbox\Learning\InlineGrader\Kami\MockConnector;
use Schoolbox\Resource\Storage;

class ConnectorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider providerDisabledConnector
     * @param IConfigLoader $configLoader
     */
    public function testDisabled(IConfigLoader $configLoader): void
    {
        $connector = new Connector($configLoader, new Client());
        $this->assertFalse($connector->isEnabled());
    }


    /**
     * @dataProvider providerEnabledConnector
     * @param IConfigLoader $configLoader
     */
    public function testEnabled(IConfigLoader $configLoader): void
    {
        $connector = new Connector($configLoader, new Client());
        $this->assertTrue($connector->isEnabled());
    }


    /**
     * @dataProvider providerDisabledConnector
     * @param IConfigLoader $configLoader
     */
    public function testOnline(IConfigLoader $configLoader): void
    {
        // TODO. The only way to really test this is to do an authenticated GET which
        //       requires us to hard-code the real API key here. Do we want to do that?
        $this->assertTrue(true);
    }


    /**
     * Test uploads against the mock API
     */
    public function testUpload(): void
    {
        $connector = new MockConnector(new Client());
        $document  = $connector->uploadDocument($this->getMockStorage());

        $this->assertEquals('2kmcksmndlkansdlk', $document->getId());
        $this->assertEquals('Blah Document', $document->getName());
        $this->assertEquals('processing', $document->getFileStatus());
        $this->assertNull($document->getFileErrorMessage());
    }


    /**
     * Test listing against the mock API
     */
    public function testListDocuments(): void
    {
        $connector = new MockConnector(new Client());

        /* TODO - Kami mock route is currently broken. I'll write to them:
         * * mock route fails if trailing slash. prod route tolerates trailing slash
         * * mock route returns array of documents, prod route returns object with
         *   single key 'documents', which is array of documents
         */
        $this->assertTrue(true);
//        $documents = $connector->listDocuments();
    }


    /**
     * Test getting a document from the Mock API
     */
    public function testGetDocument(): void
    {
        $connector = new MockConnector(new Client());
        $document  = $connector->getDocument('someRandomDocId');
        $this->assertEquals('2kmcksmndlkansdlk', $document->getId());
        $this->assertEquals('Blah Document', $document->getName());
        $this->assertEquals('done', $document->getFileStatus());
        $this->assertNull($document->getFileErrorMessage());
    }


    /**
     * Test deleting a document from the Mock API
     */
    public function testDeleteDocument(): void
    {
        $connector = new MockConnector(new Client());
        $this->assertTrue($connector->deleteDocument('2kmcksmndlkansdlk'));
    }


    public function providerDisabledConnector(): array
    {
        return [
            [new TestLoader([Connector::CONFIG_KEY_KAMI_API_KEY => null])],
            [new TestLoader([Connector::CONFIG_KEY_KAMI_API_KEY => ''])],
        ];
    }


    public function providerEnabledConnector(): array
    {
        return [
            [new TestLoader([Connector::CONFIG_KEY_KAMI_API_KEY => 'some invalid val'])],
            [new TestLoader([Connector::CONFIG_KEY_KAMI_API_KEY => '123'])],
            [new TestLoader([Connector::CONFIG_KEY_KAMI_API_KEY => 'Hisohslfd92382923'])],
        ];
    }


    private function getMockStorage()
    {
        $storage = new class() extends Storage\Model
        {

            public function readStream()
            {
                return fopen('data://text/plain,' . 'some random file contents', 'rb');
            }
        };
        $storage->setFilename('filename');
        $storage->setExtension('ext');
        return $storage;
    }
}
