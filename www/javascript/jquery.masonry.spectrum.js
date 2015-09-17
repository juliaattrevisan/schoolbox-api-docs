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
        saveBtnSelector: null,
        saveLabelSelector: null
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

        // attach listeners if
        var saveLabel = $(this.options.saveLabelSelector);
        var useLabel  = saveLabel.length && saveLabel.labelActivate && saveLabel.labelTimeout;
        $(this.element)
            .on('click', this.options.columnSelectorSelector, function (event) {
                // Don't allow deselect of the only column selected
                if (mspec.getSelectedColumns().length < 1) {
                    event.preventDefault();
                } else {
                    mspec.loadColorpickerColors();
                }
            })
            .on('click', opt.restoreBtnSelector+", "+opt.cancelBtnSelector, function (event) {
                event.preventDefault();

                var colourSet, labelParams = {css: 'success'};

                // if it's the restore button
                if ($(this).filter(opt.restoreBtnSelector).length) {
                    colourSet        = opt.colours.defaults;
                    labelParams.text = 'Colours restored to defaults'
                } else if ($(this).filter(opt.cancelBtnSelector).length) {
                    colourSet        = opt.colours.original;
                    labelParams.text = 'Colour changes undone'
                } else {
                    throw Error("Invalid selector");
                }

                mspec.resetWidth();
                mspec.resetColours(colourSet);

                // copy values from defaults into pickers and reload
                $.each(colourSet, function(key, value) {
                    opt.colours.pickers[key] = value;
                });
                mspec.loadColorpickerColors();
                if (useLabel) {
                    saveLabel.labelActivate(labelParams);
                    saveLabel.labelTimeout(labelParams);
                }
            })
            .on('click', this.options.saveBtnSelector, function (event) {
                event.preventDefault();

                // if there's no callback, don't try to fire a request at it
                if (!opt.url) {
                    return;
                }

                // This saves everything, not just colors
                var data = {
                    colors: mspec.options.colours.pickers,
                    width: masonry.slider.attr('data-slider')
                };

                data = $.extend({}, data, mspec.masonry.options.context);

                var ajaxRequest = $.post(opt.url, data, opt.complete);
                if (useLabel) {
                    saveLabel.labelActivate({
                        text: 'Saving...'
                    });
                    ajaxRequest
                        .done(function() {
                            saveLabel.labelTimeout({
                                text: 'Columns Saved',
                                css: 'success'
                            });
                        })
                        .fail(function() {
                            saveLabel.labelTimeout({
                                text: 'Error!',
                                css: 'alert'
                            });
                        });
                }
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
     * @param {object} colours
     * @returns {boolean}
     */
    MasonrySpectrum.prototype.resetColours = function (colours) {
        //noinspection AssignmentToFunctionParameterJS
        var masonry = this;
        colours = colours || this.options.colours.defaults;
        $.each(colours, function(i, v) {
            masonry.updateColor(i, v);
        });
        return false;
    };

    /**
     * @returns {boolean}
     */
    MasonrySpectrum.prototype.resetWidth = function () {
        this.masonry.slider.foundation(
            'slider',
            'set_value',
            this.masonry.slider.data('original-value')
        );
        this.masonry.updateWidth(this.masonry.slider.data('original-value'));
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
        // 1: column, 2: element to update
        var matches = element.match(/^(.*?)_(.*)/);
        if (matches) {
            var column = this.masonry.columns.filter('[data-position="'+matches[1]+'"]');
            switch (matches[2]) {
                case 'font':
                    column.find('.island section').css({color: color});
                    break;
                case 'bg_primary':
                    column.find('.island section').css({backgroundColor: color});
                    break;
                case 'head_font':
                    column.find('.component.card, .component.card a').css({color: color});
                    break;
                case 'head_bg':
                    column.find('.component.card, .component.card a').css({backgroundColor: color});
                    break;
            }
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
                        mspec.resetColours(mspec.options.colours.original);
                        break;

                    case 'reapplyColors':
                        mspec.resetColours(mspec.options.colours.pickers);
                        break;

                    default:
                        break;
                }
            }
        });
    }

})(jQuery, window, document);