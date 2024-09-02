/**
 * Fix tile height and tile text size.
 * Designed to be called on page load and then in response to change in tile width.
 */
(function ($) {
    $.fn.fixTileHeightAndFontSize = function () {
        $(this).each(function () {
            const tile = this;
            const $tile = $(tile);

            // Using padding-bottom percentage to provide appropriate height for tiles causes display
            // issues due to Chrome bug. Refer to: https://github.com/alaress/schoolbox/pull/28566.
            // To resolve we need to instead set a rounded height and remove the padding-bottom.
            // This line can be removed once the Chrome developers fix the display bug we found in SA-15725.
            // To test whether this code can be removed, follow the STR in SA-15725.
            $tile.css({
                height: Math.round($tile.outerWidth() * getAspectRatioForTile(tile)) + "px",
                paddingBottom: 0,
            });

            let fontSize = getFontSize(tile);

            if (fontSize === null) {
                // If we could not calculate the fontSize, there is nothing more we can do.
                // To work around the problem of the fontSize not being calculable when the tile is collapsed, this
                // function should be run when the tile is no longer hidden (after the expand event).
                // An event handler for this purpose is set up in library/Schoolbox/Tile/templates/sub/tile.twig.
                return;
            }

            const $tileTitle = $tile.find(".title");
            $tileTitle.css({
                fontSize: String(fontSize) + "rem",
                padding: String(fontSize / 4) + "rem",
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
            let minFontSize = $tile.attr("data-min-font-size");
            let maxFontSize = $tile.attr("data-max-font-size");

            if (minFontSize === undefined) {
                // This should never happen because minFontSize is a required field on the Modify Tile page.
                minFontSize = 0.75; // Same as default percentage value on Modify Tile page.
            }

            if (maxFontSize === undefined) {
                // This should never happen because maxFontSize is a required field on the Modify Tile page.
                maxFontSize = 1.75; // Same as default percentage value on Modify Tile page.
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
            const $aspectRatioXInput = $("#aspect_ratio_x");
            const $aspectRatioYInput = $("#aspect_ratio_y");

            let aspectRatio = null;

            if ($aspectRatioXInput.length === 1 && $aspectRatioYInput.length === 1) {
                // The inputs will be present on the Modify Tile Layout page (route GET /tile/layout/{layoutId}).
                aspectRatio = $aspectRatioYInput.val() / $aspectRatioXInput.val();
            } else {
                const $tileListUl = $(tile).parent();
                aspectRatio = $tileListUl.attr("data-aspect-ratio");
            }

            return aspectRatio;
        }
    };
})(jQuery);
