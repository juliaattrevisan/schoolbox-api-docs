<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\TaskDaemon\Request\DownloadPages;

use Alaress_Date_Format;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Instance;
use Schoolbox\TaskDaemon\Request\Message;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Factory for building download pages taskd requests.
 */
class MessageFactory
{
    /**
     * The identifier for this task.
     */
    private const TASK = 'download-pages';

    public const CONTENT_TYPE_SOURCE = 'source';

    public const CONTENT_TYPE_URL = 'url';

    /**
     * Callback URL, if this task invocation has one.
     */
    private ?string $callbackUrl = null;

    /**
     * Callback params, if this task invocation has any.
     */
    private ?array $callbackParams = null;

    /**
     * The user invoking the task, if there is one.
     */
    private ?User $effector = null;

    /**
     * Content to use to generate the report.
     *
     * @var array<array{filename: string, urls?: string[], source?: string}>
     */
    private array $content = [];

    /**
     * @var string|null
     */
    private ?string $gotenbergFooter = null;

    /**
     * Constructor
     *
     * @param Instance    $instance            Information about the Schoolbox instance.
     * @param Jwt         $tokenBuilder        Used to generate a JWT for use by taskd when:
     *                                         * calling the callback URL
     *                                         * initiating a session for Gotenberg when generating PDFs from Schoolbox
     * @param string|null $gotenbergUrl        The URL of the Gotenberg container.
     * @param string|null $gotenbergKey        The API key which the Gotenberg container requires, if any.
     * @param string|null $gotenbergHttpHeader The header in which `$gotenbergKey` will be passed.
     */
    public function __construct(
        private Instance $instance,
        private Jwt $tokenBuilder,
        private ?string $gotenbergUrl,
        private ?string $gotenbergKey,
        private ?string $gotenbergHttpHeader,
    ) {
    }

    public function withCallbackUrl(?string $callbackUrl): self
    {
        $clone = (clone $this);
        $clone->callbackUrl = $callbackUrl;
        return $clone;
    }

    public function withCallbackParams(?array $callbackParams): self
    {
        $clone = (clone $this);
        $clone->callbackParams = $callbackParams;
        return $clone;
    }

    public function withEffector(?User $effector): self
    {
        $clone = (clone $this);
        $clone->effector = $effector;
        return $clone;
    }

    public function withGotenbergFooter(?string $footer): self
    {
        $clone = (clone $this);
        $clone->gotenbergFooter = $footer;
        return $clone;
    }

    public function withContent(string $filename, string $contentType, string ...$urls): self
    {
        $clone = (clone $this);
        $clone->content = [$this->buildContent($filename, $contentType, ...$urls)];
        return $clone;
    }

    public function withAddedContent(string $filename, string $contentType, string ...$urls): self
    {
        $clone = (clone $this);
        $clone->content[] = $this->buildContent($filename, $contentType, ...$urls);
        return $clone;
    }

    public function build(string $outfile): Message
    {
        $params = [
            'outfile' => $outfile,
            'content' => $this->content,
            'gotenberg' => null,
        ];
        $token = $this->effector ? $this->tokenBuilder->generate($this->effector) : null;
        if (isset($token)) {
            $params['token'] = $token;
        }
        if (isset($this->gotenbergUrl)) {
            $params['gotenberg'] = [
                'url'  => $this->gotenbergUrl,
                'auth' => isset($this->gotenbergKey, $this->gotenbergHttpHeader)
                    ? ['key' => $this->gotenbergKey, 'header' => $this->gotenbergHttpHeader]
                    : null,
                'footer' => $this->gotenbergFooter,
            ];
        }
        return new Message(
            self::TASK,
            $params,
            Alaress_Date_Format::now()->getDateTimeObject(),
            $this->instance->getId(),
            $this->instance->getHostname(),
            $this->instance->getDomain(),
            $this->instance->getVersion(),
            $this->instance->getType(),
            $this->instance->getSchoolCode(),
            $this->callbackUrl,
            $this->callbackParams,
            $token,
        );
    }


    /**
     * Helper for building content.
     *
     * @param string $filename
     * @param string $contentType
     * @param string ...$urls
     * @return array{filename: string, urls?: string[], source?: string}
     */
    private function buildContent(string $filename, string $contentType, string ...$urls): array
    {
        switch ($contentType) {
            case self::CONTENT_TYPE_SOURCE:
                return ['filename' => $filename, 'source' => $urls[0]];
            case self::CONTENT_TYPE_URL:
                return ['filename' => $filename, 'urls' => $urls];
            default:
                throw new UnexpectedValueException(sprintf('Content type "%s" not recognised', $contentType));
        }
    }
}
