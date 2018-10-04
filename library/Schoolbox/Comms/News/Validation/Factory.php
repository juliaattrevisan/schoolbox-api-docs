<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Validation;

use Alaress\Specification\Conjunction;
use Alaress\Specification\ISpecification;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use Schoolbox\User;

/**
 * Responsible for building valid news article specifications
 */
class Factory
{
    private $strategyFactory;

    public function __construct(StrategyFactory $strategyFactory)
    {
        $this->strategyFactory = $strategyFactory;
    }


    /**
     * Returns a spec that defines an article able to be drafted:
     * * Article cannot be audienced to segments where the user does not have READ+ perm
     *
     * @param User $effector
     * @return ISpecification
     */
    public function buildSpecValidAudience(User $effector): ISpecification
    {
        return new ValidUserCanAudienceTo($effector, $this->strategyFactory);
    }


    /**
     * Returns a spec that encapsulates rules:
     * * Article cannot be audienced to segments where the user does not have READ+ perm
     * * Article has a title
     * * Article has a publish date
     * * Article has an expiry date
     * * Publish is less than or equal to expiry date
     *
     * @param User $effector
     * @return ISpecification
     */
    public function buildSpecCanBeSubmitted(User $effector): ISpecification
    {
        return new Conjunction([
            new ValidArticleAttributes(),
            new ValidUserCanAudienceTo($effector, $this->strategyFactory),
        ]);
    }
}
