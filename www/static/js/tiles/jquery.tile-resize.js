/**
 * Tile resizer:
 * Resizes the title within grid tiles based on the width of the tile.
 *
 * Settings:
 * * minSize, maxSize: the font size bounds for the title
 *                     Defaults to a range of 1-6 em
 * * unit:             the unit minSize, maxSize and getSize are measured in.
 *                     Defaults to em.
 * * titleSelector:    a selector for getting the title from the tile.
 *                     Defaults to ".title"
 */
(function($) {
    $.fn.tileResize = function(options) {
        var defaults = {
            minSize: 1,
            maxSize: 6,
            unit:    'rem',
            titleSelector: '.title'
        };
        var settings = $.extend({}, defaults, options);
        $(this).each(function() {
            
            const tile = $(this)[0];
            const tileTitle = tile.querySelector(settings.titleSelector);
            
            if (tileTitle && tileTitle.innerText !== '') { //ignore those tiles without any text
                
                if (tile.getBoundingClientRect().height <= 60) { //prevent the aspect ratio of the tile from going to small
                    tile.style.height = `60px`;
                }

                /**
                 * Set up some reasonable min/max window constraints for the min/max text size to move between 
                 */
                const minWindowSize = 400;
                const maxWindowSize = 1800;
                const windowSizeVariation = maxWindowSize - minWindowSize;
                
                /**
                 * Get and set the base font size for possible resizing
                 */
                let baseFontSize;
                if (tileTitle.hasAttribute('data-base-font-size')) {
                    baseFontSize = tileTitle.getAttribute('data-base-font-size');
                } else {
                    baseFontSize = parseInt(window.getComputedStyle(tileTitle).fontSize);
                    tileTitle.setAttribute('data-base-font-size', baseFontSize);
                }

                /**
                 * To move between the min/max font size range
                 */
                const minFontSize = baseFontSize * settings.minSize;
                const maxFontSize = baseFontSize * settings.maxSize;
                const fontSizeVariation = maxFontSize - minFontSize;
                const fontScaleFactor = fontSizeVariation / windowSizeVariation; //the factor that fits between the min/max range
                
                /**
                 * Consider the tiles width in addition to factoring in the windows width, 
                 * so the smaller the tile, the more the text size will be decreased
                 */
                const tileSizeFactor = window.innerWidth - ( minWindowSize + ( window.innerWidth - tile.getBoundingClientRect().width ) );

                /**
                 * Ensure we don't go below or above our min/max text ranges
                 */
                let fontSize = minFontSize + (fontScaleFactor * tileSizeFactor);
                if (fontSize < minFontSize) {
                    fontSize = minFontSize;
                } else if (fontSize > maxFontSize) {
                    fontSize = maxFontSize;
                }

                tileTitle.style.fontSize = `${fontSize}px`;

            }

        })
    };
})(jQuery);
