<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric;

use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;

/**
 * A test renderer.
 *
 * Should be able to render both a grid and a marked grid.
 */
class TestRenderer
{
    /**
     * Renders a grid.
     *
     * @param Grid|MarkedGrid $renderedGrid Either a grid (no marks) or a
     *                                      marked grid
     * @return string
     */
    public function render($renderedGrid): string
    {
        if ($renderedGrid instanceof Grid) {
            $grid       = $renderedGrid;
            $markedGrid = null;
        } elseif ($renderedGrid instanceof MarkedGrid) {
            $grid       = $renderedGrid->getGrid();
            $markedGrid = $renderedGrid;
        } else {
            return '';
        }


        ob_start();
        ?>
        <table>
            <thead>
            <tr>
                <th>&nbsp;</th>
                <?php foreach ($grid->getLevels() as $level): ?>
                    <th><?php echo $level->getName() ?></th>
                <?php endforeach; ?>
            </tr>
            </thead>

            <tbody>
            <?php foreach ($grid->getCapabilityUsages() as $groupUsage): ?>
                <?php
                $groupMark = $markedGrid ? $markedGrid->retrieveCapabilityMark($groupUsage) : null;
                foreach ($groupUsage->getIndicatorUsages() as $criterionUsage):
                    ?>
                    <tr>
                        <th><?php echo $groupUsage->getCapability()->getName() ?>\<?php echo $criterionUsage->getIndicator()->getName() ?></th>
                        <?php
                        // Is this criterion in use?
                        $criterionMark      = $markedGrid ? $markedGrid->retrieveIndicatorMark($criterionUsage) : null;
                        $selectedDescriptor = $criterionMark ? $criterionMark->getDescriptor() : null;
                        foreach ($grid->getLevels() as $levelIndex => $level):
                            ?>
                            <td>
                                <?php
                                // What's the descriptor here?
                                $descriptor = $criterionUsage->retrieveDescriptor($levelIndex);
                                if (isset($descriptor)):
                                    echo $descriptor->getDescription();
                                    if (null !== $descriptor->getMaxValue()):
                                        printf('<br/><strong>%0.2f</strong>', $descriptor->getMaxValue());
                                    endif;

                                    if ($selectedDescriptor === $descriptor):
                                        printf('<br/><em>selected (%0.2f)</em>', $criterionMark->getValue());
                                    endif;
                                else:
                                    printf('<em>N/A</em>');
                                endif;
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="<?php echo count($grid->getLevels()) + 2 ?>">
                        <?php
                        if ($groupMark):
                            printf('%0.2f', $groupMark->getValue());
                        else:
                            printf('<em>N/A</em>');
                        endif;
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
}
