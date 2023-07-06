/**
 * Tile resizer:
 * Resizes the title within grid tiles based on the width of the tile.
 * Also adjusts tile height.
 */
(function ($) {
    $.fn.resizeTile = function () {
        $(this).each(function () {
            const tile = this;
            const $tile = $(tile);

            /**
             * Using padding-bottom values such as 7.0175438596491% to provide appropriate height for
             * tiles can lead to display issues when there's one tile per row. Refer to: SA-15725.
             * To resolve we need to instead set a rounded height and remove the padding-bottom.
             * We get the padding-bottom from the parent as it's the easiest way to get percentage values.
             * Prior to setting the height, on each resize we need to first set it to auto and revert
             * the padding-bottom to ensure we can accurately calculate the height.
             */
            $tile.css({ height: "auto", paddingBottom: calculatePaddingBottom(tile) }); // Allow height to resize due to padding-bottom.
            $tile.css({ height: `${Math.round($tile.outerHeight())}px`, paddingBottom: 0 }); // Set height explicitly and remove padding-bottom.

            let fontSize = getFontSize(tile);

            if (fontSize === null) {
                // If we could not calculate the fontSize, there is nothing more we can do.
                // To work around the problem of the fontSize not being calculable when the tile is collapsed, this
                // function should be run when the tile is no longer hidden (after the expand event).
                // An event handler for this purpose is set up in library/Schoolbox/Tile/templates/sub/tile.twig.
                return;
            }

            const fontUnit = $tile.attr('data-font-size-unit');
            if (fontUnit === undefined) {
                throw new Error(
                    "Expected attribute 'data-font-size-unit' not set on tile with id '" + $tile.attr('id') + "'."
                );
            }

            const $tileTitle = $tile.find('.title');
            $tileTitle.css({
                fontSize: String(fontSize) + fontUnit,
                padding: String(fontSize / 4) + fontUnit,
            });

            // If the title is taller than the tile:
            // reduce the tile's top and bottom padding so it fits.
            const titleHeight = $tileTitle.outerHeight();
            const tileHeight = $tile.outerHeight();
            if (titleHeight > tileHeight) {
                const heightDifference = tileHeight - $tileTitle.height();
                const newPadding = String(heightDifference / 2) + "px";
                $tileTitle.css({
                    paddingTop: newPadding,
                    paddingBottom: newPadding,
                });
            }
        });

        /**
         * @param { HTMLElement } tile
         * @returns { string } paddingBottomCss
         */
        function calculatePaddingBottom(tile) {
            const $tile = $(tile);
            const $tileListUl = $tile.parent();
            const layoutType = $tileListUl.attr('data-layout-type');
            const columnCount = $tileListUl.attr('data-column-count');
            const tileId = $tile.attr('id');

            if (layoutType === undefined || columnCount === undefined) {
                throw new Error(
                    "Expected attribute 'data-layout-type' and/or 'data-column-count' not set on tile with id '" + tileId + "'."
                );
            }

            const aspectRatio = getAspectRatioForTile(tile);

            let paddingBottomCss = null;

            switch (layoutType) {
                case 'grid':
                    const gridWidth = $tileListUl.attr('data-grid-width');
                    if (gridWidth === undefined) {
                        throw new Error(
                            "Expected attribute 'data-grid-width' not set on tile with id '" + tileId + "'."
                        );
                    }
                    // The below line matches the calculation in tileList.twig: {% set paddingBottom = gridWidth * aspectRatio ~ "px" %}
                    paddingBottomCss = gridWidth * aspectRatio + 'px';
                    break;
                case 'column':
                    const paddingPercentage = $tileListUl.attr('data-padding-percentage');
                    if (paddingPercentage === undefined) {
                        throw new Error(
                            "Expected attribute 'data-padding-percentage' not set on tile with id '" + tileId + "'."
                        );
                    }
                    const baseColumnWidthPercentage = 100 / columnCount;
                    const tilePaddingPercentage = baseColumnWidthPercentage * paddingPercentage;
                    const tileWidthPercentage = baseColumnWidthPercentage - tilePaddingPercentage;
                    // The below line matches the calculation in tileList.twig: {% set paddingBottom = tileWidth * aspectRatio ~ "%" %}
                    paddingBottomCss = tileWidthPercentage * aspectRatio + '%';
                    break;
                default:
                    throw new Error(
                        "Unexpected value '" + layoutType + "' for data-layout-type on tile with id '" + tileId + "'."
                    );
            }

            return paddingBottomCss;
        }

        /**
         * @param { HTMLElement } tile
         * @returns { Number|null } fontSize in rem based on tile width, or null if could not calculate.
         */
        function getFontSize(tile) {
            const $tile = $(tile);
            const tileWidth = $tile.width();

            if (tileWidth === 0) {
                // Jquery could not calculate tile width, so we cannot calculate font size.
                // This happens when the tile has been collapsed (when css 'display' is 'none').
                return null;
            }

            // Notes on typical tile widths and font sizes
            // -------------------------------------------
            // Typical values of tileWidth range from 200px - 1500px.
            // So typical values of (tileWidth / 120) will range from 1.67 - 12.5.
            // Default min and max for newly created tiles are 0.75, 1.75.
            // So if the default settings are used, the font-size returned by this function will typically be beyond the
            // max end of the range, and scaling of font will typically only happen at the narrowest tile widths.
            let fontSize = tileWidth / 120; // Units 'rem'.

            const minFontSize = $tile.attr('data-min-font-size');
            const maxFontSize = $tile.attr('data-max-font-size');
            if (minFontSize === undefined || maxFontSize === undefined) {
                throw new Error(
                    "Expected attribute 'data-min-font-size' and/or 'data-max-font-size' not set" +
                    " on tile with id '" + $tile.attr('id') + "'."
                );
            }

            if (fontSize < minFontSize) {
                fontSize = minFontSize;
            }

            if (fontSize > maxFontSize) {
                fontSize = maxFontSize;
            }

            return fontSize;
        }

        /**
         * @param { HTMLElement } tile
         * @returns { Number } aspectRatio
         */
        function getAspectRatioForTile(tile) {
            const $aspectRatioXInput = $('#aspect_ratio_x');
            const $aspectRatioYInput = $('#aspect_ratio_y');

            let aspectRatio = null;

            if ($aspectRatioXInput.length === 1 && $aspectRatioYInput.length === 1) {
                // The inputs will be present on the Modify Tile Layout page (route GET /tile/layout/{layoutId}).
                aspectRatio = $aspectRatioYInput.val() / $aspectRatioXInput.val();
            } else {
                const $tileListUl = $(tile).parent();
                aspectRatio = $tileListUl.attr('data-aspect-ratio');
            }

            return aspectRatio;
        }
    };
})(jQuery);
