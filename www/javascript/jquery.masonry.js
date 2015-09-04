/**
 *
 * This plugin handles the drag and drop of re-arrangable layouts,
 * as well as the palettes of items from which they can be instantiated.
 *
 * @require     jquery, jquery.qtip, tinyMCE v3.x
 * @optional    jquery.slider, jquery.sortable, jquery.spectrum, jquery.tabs
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
;
(function ($, window, document, tinyMCE, undefined) {

    var pluginName = 'masonry',

        noop = function () {},

        sortingClass      = 'ui-sortable-helper',
        sliderHandleClass = 'ui-slider-handle',
        emptyColumnClass  = 'empty-column',
        activeClass       = 'active',
        pendingClass      = 'pending',
        loadingClass      = 'loading',
        loadedClass       = 'loaded',
        showEmptyClass    = 'show-empty',
        failedClass       = 'failed';

    var defaults = {

        // gets tacked onto AJAX requests
        context: {},

        component: {
            selector: '[data-component]',
            handleSelector: 'h2',
            immovableFilter: '.permanent',
            controlPanelSelector: '[data-component-panel]',
            editBtnSelector: 'a.edit',
            removeBtnSelector: '[data-target=delete]',
            removeActionSelector: 'a[data-remove]',

            /* All the buttons that activate a panel - cog, close/delete
             */
            allButtons: '[data-target]',

            /* All the panels that can be opened by a button - settings, delete confirmation
             */
            allPanels: '[data-component-panel]',

            mceEditorClass: 'mceEditor',
            calcSequenceData: noop,
            url: null,
        },

        // default options for defining "columns" (includes top and bottom)
        column: {
            selector: '> .column',
            leftColumnWidth: 50,
            emptyClass: 'empty-column',
            placeholderTpl: '<p/>',
            position: {
                top: '.column[data-position=top]',
                left: '.column[data-position=left]',
                right: '.column[data-position=right]'
                // , bottom: '.column[data-position=bottom]'
            }
        },

        // Box that contains the options to drag into play: if `palette` or `palette.selector` is null, no create-new-on-drag JS will be loaded
        palette: {
            selector: null,
            containerSelector: 'div.components',
            handleSelector: 'div.add-component',
            cursor: 'move',
            disabledCursor: 'default',
            disabledClass: 'disabled',
            clickToInsert: true,
            dragToInsert: true,
            dragHelperInit: noop,
            refreshItem: noop,
            url: null
        },
        // Width control: if `slider` or `slider.selector` is null, no width-adjustment JS will be loaded
        slider: {
            selector: null,
            url: null,
            min: 30,
            max: 70,

            complete: function () {
                // TODO fix this
                $('.saved-layout').hide().slideDown(150).delay(2000).slideUp(150);
            }
        },
        // Colour control: if `spectrum` or `spectrum.selector` is null, no colour JS will be loaded
        spectrum: {
            selector: null,
            // remainder inherited from jquery.masonry.spectrum.js
            colours: {},
            url: null,
            inputSelector: 'input[data-colorpicker]',
            columnSelectorSelector: '[data-column-selector]',
            restoreBtnSelector: null,
            cancelBtnSelector: null,
            saveBtnSelector: null

        },
        persist: {
            url: null,
            success: noop,
            failure: noop
        },
        dict: {
            paletteDisabled: 'This component can only be used once',
            placeholder: 'You can drag components into this space',
            dropHere: 'This is the new position of your component'
        }
    };

    /**
     *
     * @param element
     * @param options
     * @constructor
     */
    function Masonry(element, options) {
        var masonry = this, com, col;

        this.element = element;
        this.options = $.extend(true, {}, defaults, options);

        com = this.options.component;
        col = this.options.column;

        if (!(com && com.selector)) {
            throw Error("Component definition incomplete: component.selector is required");
        }

        if (!(col && col.selector)) {
            throw Error("Column definition incomplete: column.selector is required")
        }

        this.columns  = $(this.element).find(this.options.column.selector);
        this.slider   = null;
        this.palette  = null;
        this.spectrum = null;

        this.initSortable();

        this.initControls();

        if (this.options.palette && this.options.palette.selector) {
            this.initPalette();
        }

        if (this.options.slider && this.options.slider.selector) {
            this.initSlider();
        }

        if (this.options.spectrum && this.options.spectrum.selector) {
            this.initSpectrum();
        }

        this.initForms();

        // set the appropriate classes on the columns
        this.refreshColumns();

        // inject the placeholder entity
        this.columns.each(function () {
            $(this).prepend(
                $(masonry.options.column.placeholderTpl).html(masonry.options.dict.placeholder)
            );
        });
    }


    Masonry.prototype.initSlider = function () {
        var opt     = this.options,
            masonry = this;

            // cache these here, so they don't have to be computed ever time the slider is moved
            columns = {
                left: this.columns.filter(opt.column.position.left),
                right: this.columns.filter(opt.column.position.right)
            };

        if (this.slider) {
            throw new Error("Slider is already initialised");
        }

        this.slider = $(this.options.slider.selector);

        this.slider.slider({
            min: opt.slider.min,
            max: opt.slider.max,
            value: opt.column.leftColumnWidth,

            slide: function (event, ui) {
                var width = ui.value - 0.5;

                columns.left.css('width', width + '%');
                columns.right.css('width', (99 - width) + '%')
            },

            create: function () {
                // magic string is acceptable here because it's put there by jquery.slider
                masonry.slider.find('.'+sliderHandleClass).append('<span/>');
            },

            stop: function (event, ui) {

                // why does this need to send both values? in all other cases, rWidth is calculated from lWidth...
                var data = {
                    leftColWidth: ui.value - 0.5,
                    rightColWidth: 99.5 - ui.value
                };

                data = $.extend({}, data, masonry.options.context);

                // don't attempt to fire a request an a non-existent URL
                if (!opt.slider.url) {
                    console.log('AJAX sliderStop', data);
                    return;
                }

                $.post(opt.slider.url, data, opt.slider.complete);
            }
        });
    };


    Masonry.prototype.initControls = function () {
        var masonry = this,
            opt     = this.options,
            page    = $(this.element),
            btns    = [], btnsSelector;

        try {
            btns.push(opt.component.editBtnSelector);
        } catch (e) {
            // noop
        }

        try {
            btns.push(opt.component.removeBtnSelector);
        } catch (e) {
            // noop
        }

        btnsSelector = btns.join(', ');

        if (!btns.length) {
            return;
        }

        // attach control panel button listener
        page.on('click', btnsSelector, function (event) {
            var btn    = $(this),
                state  = btn.attr('data-state'),
                box    = btn.closest(opt.component.selector),
                target = box.find('[data-component-panel='+btn.data('target')+']');

            event.preventDefault();

            /* Start hiding all open panels
             */
            box.find(opt.component.allPanels).stop().slideUp();
            box.find(opt.component.allButtons).attr('data-state', 'closed');

            /* Only start opening the one you choze
             */
            if (state == 'opened') {
                btn.attr('data-state', 'closed').removeClass(activeClass);

                target.off('click', opt.component.removeActionSelector);
                target.stop().slideUp();
            }
            else {
                btn.attr('data-state', 'opened').addClass(activeClass);

                target
                    .stop()
                    .slideDown()
                    .one('click', opt.component.removeActionSelector, function (event) {
                        var btn = $(this);

                        event.preventDefault();

                        // do different things depending on which action was clicked
                        if (btn.data('remove')) {

                            // send deletion request to server
                            $.post(opt.component.url, btn.closest('form').serialize());

                            // animate a disappearance (P.S. this is revolting)
                            btn.closest(opt.component.selector).animate({ opacity: 0 }, 300, function () {
                                $(this).slideUp(200, function () {
                                    $(this).remove();
                                    // if it was the last component in a column, reshow the placeholder
                                    masonry.refreshColumns();
                                    masonry.refreshPalette();
                                });
                            });

                        } else {
                            target.off('click', opt.component.removeActionSelector).slideUp();
                        }
                    });
            }
        });
    };

    /**
     * Initializes component configuration forms - ensures that forms are
     * saved via AJAX, and that the form is updated via AJAX.
     */
    Masonry.prototype.initForms = function () {
        var masonry = this,
            opt     = this.options,
            page    = $(this.element);
        page.on(
            'submit',
            opt.component.controlPanelSelector+' form',
            function(event) {
                var form       = $(this),
                    formData   = form.serializeArray(),
                    formInputs = form.find(':input'),
                    panel      = form.closest(opt.component.controlPanelSelector),
                    existing   = panel.closest(opt.component.selector),
                    existingId = existing.attr('id');
                event.preventDefault();
                existing.addClass('saving');
                formInputs.prop('disabled', true);
                $.ajax(
                    {
                        url:  this.action,
                        type: this.method,
                        data: formData
                    }
                )
                    .done(function(response) {
                        // AJAX in the new contents
                        // (Filter out everything that's not the component)
                        var $response  = $(response);
                        var newContent = $response.is('#'+existingId)
                            ? $response
                            : $response.find('#'+existingId)
                        ;
                        masonry.insertComponentInstance(newContent, existing);
                    })
                    .fail(function() {
                        existing.addClass('failed');
                        // Later on, remove "failed" class
                        formInputs.prop('disabled', false);
                    });
            }
        );
    };

    /**
     * Retrieve the column list for this page. Good to index this result.
     *
     * @returns {{}}
     */
    Masonry.prototype.getIndexedColumns = function () {
        // cache the columns
        var cols = {},
            page = $(this.element);

        $.each(this.options.column.position, function (k, v) {
            cols[k] = page.find(v);
        });

        return cols;
    };


    /**
     * Initialise sorting
     */
    Masonry.prototype.initSortable = function () {
        var comOpt  = this.options.component,
            palOpt  = this.options.palette,
            masonry = this,
            columns = this.getIndexedColumns();

        $(this.element).sortable({
            helper: function (event, ui) {
                var cl = ui.clone();
                ui.addClass(sortingClass);
                return cl;
            },
            cursorAt: { top: 16 },
            cursor: typeof palOpt.cursor == 'undefined' ? 'move' : palOpt.cursor,
            distance: 0,
            dropOnEmpty: true,
            handle: comOpt.handleSelector,
            items: this.getComponentSelector(),
            placeholder: 'marker',
            tolerance: 'pointer',
            zIndex: 2000,
            start: function () {
                // intentionally not cached, since we can AJAX components in/out
                $(masonry.element).find('.' + comOpt.mceEditorClass).each(function () {
                    tinyMCE.execCommand('mceRemoveControl', false, this.id);
                });

                masonry.refreshColumns();
            },
            stop: function (event, ui) {
                var element = ui.item,
                    data, cl;

                // check to see if this was dragged from the palette
                if (masonry.options.palette && element.is(masonry.options.palette.handleSelector)) {
                    // keep a reference we can ajax with in a sec
                    cl = element.clone();

                    // manipulate the placeholder
                    element
                        .removeAttr('title data-count data-multi')
                        .removeClass('ui-draggable')
                        .addClass(pendingClass);

                    masonry.refreshColumns();

                    // retrieve a new instance of this component
                    masonry.newComponentInstance(cl)
                        .done(function(response) {
                            masonry.insertComponentInstance($(response), ui.item);
                        })
                        .fail(function() {
                            ui.item.addClass(failedClass).slideUp(
                                1000,
                                function() {
                                    $(this).remove()
                                }
                            );
                        });
                } else {
                    ui.item.removeClass(sortingClass);
                    masonry.saveSorting();
                }

            },
            sort: function (event, ui) {

                var eX  = event.pageX,
                    eY  = event.pageY,
                    col = {},
                    anchor = null;

                // calculate each column's position
                $.each(columns, function (k, v) {
                    col[k]        = v.offset();
                    col[k].right  = col[k].left + v.width();
                    col[k].bottom = col[k].top + v.height();
                    col[k].empty  = v.hasClass(emptyColumnClass)
                });


                // I don't know that there's a way around hardcoding these in -_-

                if (eX < col.left.right && eY > col.left.bottom && col.left.empty) {
                    anchor = columns.left;
                } else if (eX > col.right.left && eY > col.right.bottom && col.right.empty) {
                    anchor = columns.right;
                } else if (eX < col.top.right && eY < col.top.bottom && col.top.empty) {
                    anchor = columns.top;
                }
                /*
                else if (eX < (col.bottom.left + dims.bottom.width) && eY < (col.bottom.top + dims.bottom.height)) {
                    anchor = columns.bottom;
                }
                 */

                if (anchor) {
                    // move the placeholder into the different column
                    ui.placeholder.appendTo(anchor);
                }
            }
        });
    };


    /**
     * Persists the current sorting layout
     */
    Masonry.prototype.saveSorting = function () {
        var page = $(this.element);

        // intentionally not cached, since we can AJAX components in/out
        page.find('.' + this.options.component.mceEditorClass).each(function () {
            tinyMCE.execCommand('mceAddControl', false, this.id);
        });

        page.sortable('refresh');

        this.refreshColumns();

        data = this.options.component.calcSequenceData.call(this, this.getIndexedColumns());

        // don't send an empty request, or to a blank resource
        if (data && this.options.component.url) {
            // append our context to the request and fire it off
            $.post(this.options.component.url, $.extend({}, data, this.options.context));
        }
    };


    /**
     * Takes the n selectors and m negators from the component config and runs them together as a single string
     *
     * @return {string}
     */
    Masonry.prototype.getComponentSelector = function () {
        var selectors    = this.options.component.selector.split(',').map(function (v) { return v.trim(); }),
            filters      = this.options.component.immovableFilter.split(',').map(function (v) { return v.trim(); }),
            filterString = filters.join(', ');

        if (filterString) {
            $.each(selectors, function (i, v) {
                selectors[i] += ':not("' + filterString + '")';
            });
        }

        return selectors.join(', ');
    };


    /**
     * Generates a new instance, based on the element that was clicked
     *
     * @param {$} element
     *
     * @return AJAX
     */
    Masonry.prototype.newComponentInstance = function (element) {

        var url = this.options.palette.url,
            // TODO remove this find(a)
            rel  = element.find('a').attr('rel').split(' '),
            data = { add: rel[0] };

        // eportfolio shenanigans or somesuch
        if (rel.length > 1) {
            data.from = rel[1];
        }

        data = $.extend({}, data, this.options.context);

        if (!url) {
            // callback not called
            return;
        }

        return $.ajax({
            type: 'POST',
            url: url,
            data: data,
            dataType: 'html'
        });

    };

    /**
     * Adds a new component to the page.
     *
     * @param response    The new HTML for the component
     * @param replacement The old HTML to replace
     */
    Masonry.prototype.insertComponentInstance = function(response, replacement)
    {
        var masonry = this;

        // If this component already exists: don't drop it invisibly onto the page.
        // That will cause issues with JS that depends on that component's ID.
        // (such as dropzones)
        var css = { opacity: 0 };
        if (response.attr('id') != replacement.attr('id')) {
            // nek level cheating for pretty animationz -_-
            response.css({
                visibility: 'hidden',
                position: 'fixed',
                width: replacement.width()
            });
            // drop it invisibly onto the page so that it has a height
            $('body').append(response);
            // grab its computed height
            css.height = response.height;
        }
        replacement.animate(css, 550, function () {

            /* Collapse content on components (if it exists)
             */
            var collapser = $(response).find('[data-collapser=true]');
            if (collapser.length) {
                collapser.collapsable();
            }

            replacement.empty().replaceWith(response.removeAttr('style'));
            masonry.saveSorting();
            masonry.refreshPalette();
        });
    };


    /**
     * Initialise the component palette
     */
    Masonry.prototype.initPalette = function () {
        var masonry  = this,
            opt      = this.options,
            palOpt   = opt.palette;

        if (this.palette) {
            throw new Error("Palette is already initialised");
        }

        this.palette = $(palOpt.selector).find(palOpt.containerSelector);

        //$(palOpt.selector).tabs();

        //// apply qtips to each element
        //this.applyQTip($(palOpt.handleSelector));

        if (!(palOpt.clickToInsert && palOpt.dragToInsert)) {
            throw new Error("One of palette.clickToInsert or palette.dragToInsert must be true");
        }

        // attach appropriate listeners

        if (palOpt.clickToInsert) {
            this.palette.on('click', palOpt.handleSelector, function (event) {
                var element = $(this);

                event.preventDefault();

                if (!element.hasClass('disabled')) {
                    element.addClass(loadingClass);
                    masonry.newComponentInstance(element)
                        .done(function(response) {
                            var leftColumn  = $(masonry.element).find(opt.column.position.left);

                            element.addClass(loadedClass);

                            $(response).hide().prependTo(leftColumn).css('opacity', 0)
                                .slideDown(400, function () {
                                    masonry.refreshColumns();

                                    // these 2 calls should happen together so there's no opacity flicker
                                    element.removeClass(loadingClass);
                                    masonry.refreshPalette();

                                    $(this).animate({opacity: 1}, 300, function () {
                                        element.removeClass(loadedClass);
                                    });

                                    // only go in for applying colours if you've got colours to set
                                    if (masonry.spectrum) {
                                        masonry.spectrum.masonrySpectrum('resetColours', leftColumn);
                                    }
                                });

                            /* Collapse content on components
                             * Really...I have to do it like this?
                             */
                            $('#' + $(response).attr('id')).find('[data-collapser=true]').collapsable();
                        })
                        .fail(function() {
                            masonry.refreshColumns();
                            element.removeClass(loadingClass);
                            element.animate({opacity: 1}, 300, function () {
                                element
                                    .addClass(failedClass)
                                    .removeClass(failedClass, 2000);
                            });
                            masonry.refreshPalette();
                        });
                }
            });
        }

        if (palOpt.dragToInsert) {
            this.palette.find(palOpt.handleSelector).draggable({
                connectToSortable: this.element,
                revert: 'invalid',
                helper: function () {
                    var cl = $(this).clone();

                    palOpt.dragHelperInit.apply(cl);
                    cl.appendTo(masonry.element).addClass(sortingClass);

                    return cl;
                }
            }).filter('.' + palOpt.disabledClass).draggable('disable');
        }
    };


    /**
     * Refresh the palette (if palette has been instantiated)
     */
    Masonry.prototype.refreshPalette = function () {
        if (!this.palette) {
            return;
        }

        var masonry = this,
            counts  = {},
            handles = this.palette.find(this.options.palette.handleSelector);

        $(this.element).find('input[data-defn-id]').each(function () {
            if (!counts[this.value]) {
                counts[this.value] = 0;
            }
            counts[this.value]++;
        });

        handles.each(function () {
            var item         = $(this),
                definitionId = item.find('a').attr('rel').split(' ')[0],
                count        = typeof counts[definitionId] == 'undefined' ? 0 : counts[definitionId];

            // only allowed one
            if (item.hasClass('one-only') && count) {
                if (masonry.options.palette.dragToInsert) {
                    try {
                        item.draggable('disable');
                    } catch (e) {
                        console.warn(item);
                    }
                }
                item.addClass(masonry.options.palette.disabledClass);
            } else {
                if (masonry.options.palette.dragToInsert) {
                    try {
                        item.draggable('enable');
                    } catch (e) {
                        console.warn(item);
                    }
                }
                item.removeClass(masonry.options.palette.disabledClass);
            }

            item.data('count', count);
            masonry.options.palette.refreshItem.call(item);
        });

        //// reapply <s>every 4 hours</s>never
        //this.applyQTip(handles);
    };



    ///**
    // * Apply QTips to the given elements
    // *
    // * @param {$} elements
    // */
    //Masonry.prototype.applyQTip = function (elements) {
    //    var masonry = this;
    //
    //    elements.each(function () {
    //        var item    = $(this),
    //            c       = item.data('count'),
    //            string  = '<strong>' + (c == 0 ? 'no' : c) + '</strong> instance';
    //            string += c == 1 ? '' : 's';
    //
    //        item.find('strong').qtip({
    //            content: 'You have ' + string + ' of this component currently active',
    //            style: {
    //                classes: 'qtip-green qtip-shadow qtip-rounded'
    //            },
    //            position: {
    //                my: 'bottom left',
    //                at: 'top right'
    //            }
    //        });
    //
    //        masonry.options.palette.refreshItem.call(item);
    //    });
    //};


    /**
     * Initialise the Spectrum sub-library
     */
    Masonry.prototype.initSpectrum = function () {
        if (this.spectrum) {
            throw new Error("Spectrum is already initialised");
        }

        // this sub-library is too big and complicated to live inside here, so it's been split out
        this.spectrum = $(this.options.spectrum.selector).masonrySpectrum(this);
    };


    /**
     * Refresh the empty/non-empty states of all columns
     * @return {Masonry}
     */
    Masonry.prototype.refreshColumns = function () {
        var masonry = this,
            opts    = masonry.options;

        opts.column.onToggle.apply(this);

        this.columns.each(function() {
            var column, components, dragging, markerHeight, componentSelector;

            try {
                componentSelector = opts.component.selector + ', ' + opts.palette.handleSelector;
            } catch (e) {
                componentSelector = opts.component.selector;
            }

            column       = $(this);
            components   = column.find(componentSelector).not('.' + sortingClass);
            dragging     = column.find('.' + sortingClass);
            markerHeight = 0;

            // filter out this component by id if it's being dragged
            if (dragging.length) {
                components   = components.not('#' + dragging.attr('id'));
                markerHeight = dragging.height();
            }

            if (components.length) {
                column.removeClass(opts.column.emptyClass)
            } else {
                column.addClass(opts.column.emptyClass);
            }

            column.children('.marker').html($('<span/>').text(opts.dict.dropHere)).css('height', markerHeight);
        });

        return this;
    };


    /**
     * Register plugin with jquery
     *
     * @param options
     * @returns {*}
     */
    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new Masonry(this, options));
            }
        });
    };

})(jQuery, window, document, tinymce);