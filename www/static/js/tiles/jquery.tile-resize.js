/**
 * Tile resizer:
 * Resizes the title within grid tiles based on the width of the tile.
 *
 * Settings:
 * * minSize, maxSize: the font size bounds for the title
 *                     Defaults to a range of 1-6 em
 * * unit:             the unit minSize, maxSize and getSize are measured in.
 *                     Defaults to em.
 * * getSize:          a callback for retrieving the font size
 *                     Defaults to width of the tile in px, / 120
 * * titleSelector:    a selector for getting the title from the tile.
 *                     Defaults to ".title"
 */
(function ($) {
    $.fn.tileResize = function (options) {
        var defaults = {
            minSize: 1,
            maxSize: 6,
            unit: "em",
            getSize: function (tile, settings) {
                return tile.width() / 120;
            },
            titleSelector: ".title",
        };
        var settings = $.extend({}, defaults, options);
        $(this).each(function () {
            var tile = $(this);
            var tileTitle = tile.find(settings.titleSelector);
            var size = Math.max(settings.minSize, Math.min(settings.getSize(tile, settings), settings.maxSize));

            // Variable size will be zero for collapsed tiles if the default getSize() function is used.
            // The tile size is not actually zero, we just can't measure it if the tile is collapsed.
            // We cannot calculate the new font-size and padding if we do not know the tile size.
            // To work around this problem, this function should be run also after the tile is expanded.  An event
            // handler for this purpose is set up in library/Schoolbox/Tile/templates/sub/tile.twig.
            if (size !== 0) {
                tileTitle.css({
                    fontSize: String(size) + settings.unit,
                    padding: String(size / 4) + settings.unit,
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
            }
        });
    };
})(jQuery);
