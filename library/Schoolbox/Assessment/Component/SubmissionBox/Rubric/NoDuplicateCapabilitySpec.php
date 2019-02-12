<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Alaress\Specification\Base;
use Schoolbox\L10n\RosettaStone;

/**
 * Spec pattern for validating whether a set of capabilities is able to be
 * saved.
 */
class NoDuplicateCapabilitySpec extends Base
{
    /**
     * Translator, used for translating error messages
     *
     * @var RosettaStone
     */
    private $translator;


    /**
     * Constructor
     *
     * @param RosettaStone $translator
     */
    public function __construct(RosettaStone $translator)
    {
        $this->translator = $translator;
    }


    /**
     * @param mixed $candidate
     * @return bool
     * @throws \UnexpectedValueException if it isn't satisfied
     */
    public function isSatisfiedBy($candidate): bool
    {
        // Verify $candidate is a list of capabilities
        $namesInUse = [];
        if (!is_iterable($candidate)) {
            throw new \InvalidArgumentException('$candidate must be iterable');
        }

        // Try to save them
        foreach ($candidate as $item) {
            if (!$item instanceof Group) {
                // warning
                continue;
            }

            // Lowercase names for comparison as well
            $normalizedName = strtolower($item->retrieveNormalizedName());
            if (isset($namesInUse[$normalizedName])) {
                // throw error
                // Only throw error if their names are already in use though?
                // Should this not be fixed?
                $errorMessage = sprintf("%s '%s' cannot be saved because %s %s with the same name already exists in",
                    $this->translator->translate('rubricGroup', false, true),
                    $item->getName(),
                    $this->translator->indefiniteArticle('rubricGroup'),
                    $this->translator->translate('rubricGroup')
                );
                throw new \UnexpectedValueException($errorMessage);
            }
            $namesInUse[$item->retrieveNormalizedName()] = true;
        }
        return true;
    }
}
