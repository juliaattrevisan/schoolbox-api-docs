<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Permission;

use Schoolbox\Discussion\Thread\Authorizer\IStrategy;
use Schoolbox\Discussion\Thread\Context\IContext;
use Schoolbox\Discussion\Thread\Model as ThreadModel;

/**
 * Base strategy for determining permissions on discussion threads which are
 * associated with comms articles.
 */
abstract class BaseStrategy implements IStrategy
{
    /**
     * @var IContext
     */
    private $context;


    /**
     * Constructor
     *
     * @param IContext $context
     */
    public function __construct(IContext $context)
    {
        $this->context = $context;
    }


    /**
     * @param ThreadModel $thread
     * @return iterable
     */
    public function retrieveAssociations(ThreadModel $thread): iterable
    {
        return $this->context->findAssociations($thread);
    }
}
