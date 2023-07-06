/**
 * Tile resizer:
 * Resizes the title within grid tiles based on the width of the tile.
 * Also adjusts tile height.
 *
 * Settings:
 * * minFontSize, maxFontSize: the font size bounds for the title
 *                     Defaults to a range of 1-6 em
 * * unit:             the unit minFontSize, maxFontSize and getSize are measured in.
 *                     Defaults to em.
 * * getFontSize:      a callback for retrieving the font size
 *                     Defaults to width of the tile in px, / 120
 * * titleSelector:    a selector for getting the title from the tile.
 *                     Defaults to ".title"
 */
(function ($) {
    $.fn.resizeTile = function (options) {
        const defaults = {
            minFontSize: 1,
            maxFontSize: 6,
            unit: "rem",
            /**
             * Override this function if you want the font size in a unit other than 'rem'.
             */
            getFontSize: function (tile, settings) {
                const tileWidth = tile.width();

                if (tileWidth === 0) {
                    // Jquery could not calculate tile width, so we cannot calculate font size.
                    // This happens when the tile has been collapsed (when css 'display' is 'none').
                    return null;
                }

                return tileWidth / 120; // Units 'rem'.
            },
            titleSelector: ".title",
        };

        const settings = $.extend({}, defaults, options);

        $(this).each(function () {
            const tile = $(this);
            const tileTitle = tile.find(settings.titleSelector);

            let fontSize = settings.getFontSize(tile, settings);

            if (fontSize === null) {
                // If we could not calculate the fontSize, there is nothing more we can do.
                // To work around the problem of the fontSize not being calculable when the tile is collapsed, this
                // function should be run when the tile is no longer hidden (after the expand event).
                // An event handler for this purpose is set up in library/Schoolbox/Tile/templates/sub/tile.twig.
                return;
            }

            if (fontSize < settings.minFontSize) {
                fontSize = settings.minFontSize;
            }

            if (fontSize > settings.maxFontSize) {
                fontSize = settings.maxFontSize;
            }

            tileTitle.css({
                fontSize: String(fontSize) + settings.unit,
                padding: String(fontSize / 4) + settings.unit,
            });

            // If the title is taller than the tile:
            // reduce the tile's top and bottom padding so it fits.
            const titleHeight = tileTitle.outerHeight();
            const tileHeight = tile.outerHeight();
            if (titleHeight > tileHeight) {
                const heightDifference = tileHeight - tileTitle.height();
                const newPadding = String(heightDifference / 2) + "px";
                tileTitle.css({
                    paddingTop: newPadding,
                    paddingBottom: newPadding,
                });
            }

            /**
             * Using padding-bottom values such as 7.0175438596491% to provide appropriate height for
             * tiles can lead to display issues when there's one tile per row. Refer to: SA-15725.
             * To resolve we need to instead set a rounded height and remove the padding-bottom.
             * We get the padding-bottom from the parent as it's the easiest way to get percentage values.
             * Prior to setting the height, on each resize we need to first set it to auto and revert
             * the padding-bottom to ensure we can accurately calculate the height.
             */
            tile.css({ height: "auto", paddingBottom: calculatePaddingBottom(tile) }); // Allow height to resize due to padding-bottom.
            tile.css({ height: `${Math.round(tile.outerHeight())}px`, paddingBottom: 0 }); // Set height explicitly and remove padding-bottom.
        });
    };
})(jQuery);

/**
 * @param { HTMLElement } tile
 * @returns { string } paddingBottomCss
 */
function calculatePaddingBottom(tile) {
    const $tileListUl = $(tile.parent());
    const layoutType = $tileListUl.attr('data-layout-type');
    const columnCount = $tileListUl.attr('data-column-count');
    const aspectRatio = getAspectRatioForTile(tile);

    let paddingBottomCss = null;

    switch (layoutType) {
        case 'grid':
            const gridWidth = $tileListUl.attr('data-grid-width');
            // The below line matches the calculation in tileList.twig: {% set paddingBottom = layout.gridWidth * aspectRatio ~ "px" %}
            paddingBottomCss = gridWidth * aspectRatio + 'px';
            break;
        case 'column':
            const baseColumnWidthPercent = 100 / columnCount;
            const paddingPercent = $tileListUl.attr('data-padding-percentage');
            const tilePaddingPercent = baseColumnWidthPercent * paddingPercent;
            const tileWidthPercent = baseColumnWidthPercent - tilePaddingPercent;
            // The below line matches the calculation in tileList.twig: {% set paddingBottom = tileWidth * aspectRatio ~ "%" %}
            paddingBottomCss = tileWidthPercent * aspectRatio + '%';
            break;
        default:
            throw new Error("Unexpected value '" + layoutType + "' for data-layout-type.");
    }

    return paddingBottomCss;
}

/**
 * @param { HTMLElement } tile
 * @returns { float } aspectRatio
 */
function getAspectRatioForTile(tile) {
    const $aspectRatioXInput = $('#aspect_ratio_x');
    const $aspectRatioYInput = $('#aspect_ratio_y');

    let aspectRatio = null;

    if ($aspectRatioXInput.length === 1 && $aspectRatioYInput.length === 1) {
        // The inputs will be present on the Modify Tile Layout page (route GET /tile/layout/{layoutId}).
        aspectRatio = $aspectRatioYInput.val() / $aspectRatioXInput.val();
    } else {
        const $tileListUl = $(tile.parent());
        aspectRatio = $tileListUl.attr('data-aspect-ratio');
    }

    return aspectRatio;
}
