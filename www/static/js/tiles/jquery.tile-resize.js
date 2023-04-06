/**
 * Tile resizer:
 * Resizes the title within grid tiles based on the width of the tile.
 *
 * Settings:
 * * minFontSize, maxFontSize: the font size bounds for the title
 *                     Defaults to a range of 1-6 em
 * * unit:             the unit minFontSize, maxFontSize and getSize are measured in.
 *                     Defaults to em.
 * * getFontSize:          a callback for retrieving the font size
 *                     Defaults to width of the tile in px, / 120
 * * titleSelector:    a selector for getting the title from the tile.
 *                     Defaults to ".title"
 */
(function ($) {
    $.fn.resizeTileTitle = function (options) {
        const defaults = {
            minFontSize: 1,
            maxFontSize: 6,
            unit: "em",
            getFontSize: function (tile, settings) {
                const tileWidth = tile.width();

                if (tileWidth === 0) {
                    // Jquery could not calculate tile width, so we cannot calculate font size.
                    // This happens when the tile has been collapsed (hidden).
                    return null;
                }

                return tileWidth / 120;
            },
            titleSelector: ".title",
        };
        const settings = $.extend({}, defaults, options);
        $(this).each(function () {
            const tile = $(this);
            const tileTitle = tile.find(settings.titleSelector);
            const fontSize = Math.max(
                settings.minFontSize,
                Math.min(settings.getFontSize(tile, settings), settings.maxFontSize)
            );

            if (fontSize === null) {
                // If we could not calculate the fontSize, there is nothing more we can do.
                // To work around the problem of the fontSize not being calculable when the tile is collapsed, this
                // function should be run when the tile is no longer hidden (after the expand event).
                // An event handler for this purpose is set up in library/Schoolbox/Tile/templates/sub/tile.twig.
                return;
            }

            tileTitle.css({
                fontSize: String(fontSize) + settings.unit,
                padding: String(fontSize / 4) + settings.unit,
            });

            // If the title is taller than the tile:
            // reduce the tile's top and bottom padding so it fits.
            var titleHeight = tileTitle.outerHeight();
            var tileHeight = tile.outerHeight();
            if (titleHeight > tileHeight) {
                var heightDifference = tileHeight - tileTitle.height();
                var newPadding = String(heightDifference / 2) + "px";
                tileTitle.css({
                    paddingTop: newPadding,
                    paddingBottom: newPadding,
                });
            }
        });
    };
})(jQuery);
