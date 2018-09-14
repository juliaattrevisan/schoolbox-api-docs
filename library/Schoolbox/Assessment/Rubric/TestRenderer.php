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
            <?php foreach ($grid->getCapabilityUsages() as $capabilityUsage): ?>
                <?php
                $capabilityMark = $markedGrid ? $markedGrid->retrieveCapabilityMark($capabilityUsage) : null;
                foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage):
                    ?>
                    <tr>
                        <th><?php echo $capabilityUsage->getCapability()->getName() ?>\<?php echo $indicatorUsage->getIndicator()->getName() ?></th>
                        <?php
                        // Is this indicator in use?
                        $indicatorMark      = $markedGrid ? $markedGrid->retrieveIndicatorMark($indicatorUsage) : null;
                        $selectedDescriptor = $indicatorMark ? $indicatorMark->getDescriptor() : null;
                        foreach ($grid->getLevels() as $levelIndex => $level):
                            ?>
                            <td>
                                <?php
                                // What's the descriptor here?
                                $descriptor = $indicatorUsage->retrieveDescriptor($levelIndex);
                                if (isset($descriptor)):
                                    echo $descriptor->getDescription();
                                    if (null !== $descriptor->getMaxValue()):
                                        printf('<br/><strong>%0.2f</strong>', $descriptor->getMaxValue());
                                    endif;

                                    if ($selectedDescriptor === $descriptor):
                                        printf('<br/><em>selected (%0.2f)</em>', $indicatorMark->getValue());
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
                        if ($capabilityMark):
                            printf('%s (%0.2f)', $capabilityMark->retrieveHumanReadableMark(), $capabilityMark->retrieveNormalizedMark());
                        else:
                            printf('<em>N/A</em>');
                        endif;
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if ($markedGrid): ?>
            <tr>
                <td colspan="<?php echo count($grid->getLevels()) + 2 ?>">
                    <?php
                    printf('%s (%0.2f)', $markedGrid->retrieveHumanReadableMark(), $markedGrid->retrieveNormalizedMark());
                    ?>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
}
