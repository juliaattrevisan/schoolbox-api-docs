<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\InlineGrader\Kami;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\User;
use Alaress_Date_Format;

/**
 * Kami Connector interface
 */
interface IConnector
{
    // Session options
    const SESSION_THEME_LIGHT = 'light';
    const SESSION_THEME_DARK = 'dark';


    /**
     * Is Kami enabled for this school? (Controlled by Puppet)
     *
     * @return bool
     */
    public function isEnabled(): bool;


    /**
     * Is Kami online?
     *
     * @return bool
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     */
    public function isOnline(): bool;


    /**
     * Upload a file into Kami and return the newly assigned Kami Document Identifier
     *
     * Note that this method cost 4c each time you hit the production or proxy server
     *
     * @param IStorage        $storage
     * @param FilesystemProxy $filesystemProxy
     * @return Document
     * @throws Exception\EmptyApiKeyException If no Kami API key configured
     * @throws Exception\ResponseDecodeException If cannot decode the Kami response
     */
    public function uploadDocument(IStorage $storage, FilesystemProxy $filesystemProxy): Document;


    /**
     * Get a list of all documents uploaded to Kami
     *
     * @return GenericCollection|Document[]
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws ClientException If cannot fetch documents for reasons other than simply not existing.
     */
    public function listDocuments(): GenericCollection;


    /**
     * Return the specified document from Kami. Throws exception if not found.
     *
     * @param string $documentId
     * @return Document
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws Exception\ApiException If cannot fetch a document for reasons other than simply not existing.
     * @throws Exception\NotFound If document does not exist in Kami
     * @throws ConnectException
     */
    public function getDocument(string $documentId): Document;


    /**
     * Delete an uploaded document. This will remove the file from Kami's cloud storage.
     *
     * @param string $documentId
     * @return bool Whether a mutation occurred. (will throw exception if not found)
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws Exception\ApiException If cannot fetch a document for reasons other than simply not existing.
     * @throws Exception\NotFound If document does not exist in Kami
     * @throws ConnectException
     */
    public function deleteDocument(string $documentId): bool;


    /**
     * Create a time-limited session and returns a URL you can embed in your page.
     *
     * Note that this implementation does not permit creation of a session that does not expire.
     *
     * @param string $documentId
     * @param User   $user
     * @param bool   $editable
     * @param string $theme     Sets the theme to use when viewing the document
     * @param bool   $showSave  Hides the save (download) button
     * @param bool   $showPrint Hides the print button
     * @param bool   $showHelp  Hides the help button
     * @param bool   $showMenu  Hides the menu button
     * @return Session
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws Exception\NotFound If document does not exist in Kami
     */
    public function viewSession(
        string $documentId,
        User $user,
        bool $editable = true,
        string $theme = IConnector::SESSION_THEME_LIGHT,
        bool $showSave = true,
        bool $showPrint = true,
        bool $showHelp = true,
        bool $showMenu = true
    ): Session;


    /**
     * Request generation of a PDF export with annotations embedded as PDF annotations
     *
     * @param string $documentId
     * @return Export
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws Exception\NotFound If document does not exist in Kami
     */
    public function createAnnotatedExport(string $documentId): Export;


    /**
     * Request generation of a PDF export suitable for printing
     *
     * @param string $documentId
     * @return Export
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws Exception\NotFound If document does not exist in Kami
     */
    public function createInlineExport(string $documentId): Export;


    /**
     * Get the current status of a previously requested document export.
     *
     * @param string $exportId
     * @return Export
     * @throws Exception\EmptyApiKeyException If API key not configured
     * @throws Exception\ResponseDecodeException If unable to decode a server response
     * @throws Exception\NotFound If export does not exist in Kami
     */
    public function getExport(string $exportId): Export;
}
