/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
;
(function ($, window, document, undefined) {

    var pluginName = 'masonrySpectrum',
        interactionState = 'interaction-state';

    var defaults = {
        colours: {
            original: {},
            pickers: {},
            defaults: {}
        },
        url: null,
        complete: function() {
            // TODO fix this
            $('.saved-colours').slideDown(150).delay(2000).slideUp(150);
        },
        inputSelector: 'input',
        columnSelectorSelector: '[data-column-selector]',
        restoreBtnSelector: null,
        cancelBtnSelector: null,
        saveBtnSelector: null
    };

    /**
     * @param {*}       element
     * @param {Masonry} masonry
     * @constructor
     */
    function MasonrySpectrum(element, masonry) {
        var mspec = this, opt;

        this.element = element;
        this.masonry = masonry;
        this.options = $.extend(true, {}, defaults, masonry.options.spectrum);

        opt = this.options;

        $(this.element).find(this.options.inputSelector).spectrum({
            showPalette: false,
            showSelectionPalette: false,
            showButtons: false,
            showAlpha: false,
            showInitial: true,
            showInput: true,
            clickoutFiresChange: true,
            preferredFormat: 'hex',
            replacerClassName: 'colorBox',
            allowEmpty: true,
            change: function (colorObject) { mspec.onColorChange(this, colorObject) },
            move: function (colorObject) { mspec.onColorChange(this, colorObject) }
        });

        // setup colour selectors
        this.loadColorpickerColors();

        // attach listeners

        $(this.element)
            .on('click', this.options.columnSelectorSelector, function (event) {
                event.preventDefault();
                // Don't allow deselect of the only column selected
                var element  = $(this);
                var deselect = element.data(interactionState) === 'selected';
                if (!(deselect && mspec.getSelectedColumns().length <= 1)) {
                    element
                        .toggleClass('selected', !deselect)
                        .data(interactionState, deselect ? '' : 'selected');

                    mspec.loadColorpickerColors();
                }
            })
            .on('click', opt.restoreBtnSelector+", "+opt.cancelBtnSelector, function (event) {
                event.preventDefault();

                var colourSet;

                // if it's the restore button
                if ($(this).filter(opt.restoreBtnSelector).length) {
                    colourSet = opt.colours.defaults;
                } else if ($(this).filter(opt.cancelBtnSelector).length) {
                    colourSet = opt.colours.original;
                } else {
                    throw Error("Invalid selector");
                }

                mspec.resetColours(mspec.masonry.columns, colourSet);

                // copy values from defaults into pickers and reload
                $.each(colourSet, function(key, value) {
                    opt.colours.pickers[key] = value;
                });
                mspec.loadColorpickerColors();
            })
            .on('click', this.options.saveBtnSelector, function (event) {
                event.preventDefault();

                // if there's no callback, don't try to fire a request at it
                if (!opt.url) {
                    return;
                }

                var data = {
                    colors: mspec.options.colours.pickers
                };

                data = $.extend({}, data, mspec.masonry.options.context);

                $.post(opt.url, data, opt.complete);
            });


    }


    /**
     * To be fired whenever
     *
     * @param {*} element
     * @param {tinycolor} colorObject
     */
    MasonrySpectrum.prototype.onColorChange = function (element, colorObject) {
        // Change the colors for the selected items
        var color   = '#' + colorObject.toHex(),
            id      = element.id,
            pickers = this.options.colours.pickers,
            mspec   = this;

        if (!(colorObject instanceof tinycolor)) {
            return;
        }

        this.getSelectedColumns().forEach(function (column) {
            var colorId = column + '_' + id;
            pickers[colorId] = color;
            mspec.updateColor(colorId, color)
        });
    };


    /**
     * Loads picked colors for selected columns into colorpickers.
     * If multiple columns are selected, and their colors are different,
     * the colorpicker will be blank.
     */
    MasonrySpectrum.prototype.loadColorpickerColors = function () {
        var columns = this.getSelectedColumns(),
            pickers = this.options.colours.pickers;

        $(this.element).find(this.options.inputSelector).each(function () {
            var element = $(this),
                id      = this.id;

            var colors = columns.map(function(column) {
                return pickers[column + '_' + id];
            });

            // If this isn't the first instance of color in colors:
            // filter it out (thus getting unique array)
            var uniqueColors = colors.filter(function(color, index) {
                return colors.indexOf(color) === index;
            });

            if (uniqueColors.length === 1) {
                element.spectrum('set', uniqueColors.pop());
            } else {
                element.spectrum('set', null);
            }
        });
    };


    /**
     * Read DOM to find out what is currently selected
     *
     * @returns {*}
     */
    MasonrySpectrum.prototype.getSelectedColumns = function () {
        return $(this.options.columnSelectorSelector)
            .filter(':checked')
            .map(function() { return this.id; })
            .toArray();
    };


    /**
     * @param {$} columns - the columns to be reset. Array of JQuery DOM references
     * @param {object} colours
     * @returns {boolean}
     */
    MasonrySpectrum.prototype.resetColours = function (columns, colours) {
        //noinspection AssignmentToFunctionParameterJS
        colours = colours || this.options.colours.defaults;
        $.each(colours, this.updateColor);
        return false;
    };

    /**
     * TODO: refactor hardcoding out of this method
     * Updates colors for the given element
     *
     * @param element Element to update
     * @param color   Color to update to
     * @returns {*}
     */
    MasonrySpectrum.prototype.updateColor = function (element, color) {
        switch (element) {
            case 'top_bg_primary':
                return $('.top .text, .top .listing, .top .information-list li').css('background-color', color);
                break;
            case 'top_bg_secondary':
                return $('.top .dark').css('background-color', color);
                break;
            case 'top_head_font':
                return $('.top h2').css('color', color);
                break;
            case 'top_head_bg':
                return $('.top h2').css('background-color', color);
                break;
            case 'top_font':
                return $('.top, .top p').css('color', color);
                break;
            case 'top_link':
                return $('.top a').css('color', color);
                break;
            case 'left_bg_primary':
                return $('.left .text, .left .listing, .left .information-list li').css('background-color', color);
                break;
            case 'left_bg_secondary':
                return $('.left .dark').css('background-color', color);
                break;
            case 'left_head_font':
                return $('.left h2').css('color', color);
                break;
            case 'left_head_bg':
                return $('.left h2').css('background-color', color);
                break;
            case 'left_font':
                return $('.left, .left p').css('color', color);
                break;
            case 'left_link':
                return $('.left a').css('color', color);
                break;
            case 'left_hover':
                return $('.right a:hover').css('color', color);
                break;
            case 'right_bg_primary':
                return $('.right .text, .right .listing, .right .information-list li').css('background-color', color);
                break;
            case 'right_bg_secondary':
                return $('.right .dark').css('background-color', color);
                break;
            case 'right_head_font':
                return $('.right h2').css('color', color);
                break;
            case 'right_head_bg':
                return $('.right h2').css('background-color', color);
                break;
            case 'right_font':
                return $('.right, .right p').css('color', color);
                break;
            case 'right_link':
                return $('.right a').css('color', color);
                break;
            case 'right_hover':
                return $('.right a:hover').css('color', color);
                break;
            default:
                return $('.left .text').css('background-color', color);
                break;
        }
    };


    /**
     *
     * @param {Masonry} masonry
     * @returns {$}
     */
    $.fn[pluginName] = function (masonry) {
        var args = arguments;
        return this.each(function () {
            var mspec;

            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new MasonrySpectrum(this, masonry));
            } else if (typeof masonry == 'string') {
                mspec = $.data(this, 'plugin_' + pluginName);

                switch (masonry) {
                    case 'resetColours':
                        mspec.resetColours(args[1], mspec.options.colours.original);
                        break;

                    default:
                        break;
                }
            }
        });
    }

})(jQuery, window, document);