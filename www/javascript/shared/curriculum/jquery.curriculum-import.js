/**
 * Curriculum Importer
 */
(function() {
    "use strict";

    /**
     * Curriculum import object
     *
     * @param element
     * @param options
     * @constructor
     */
    var CurriculumImport = function(element, options)
    {
        this.configure(element, options);
        this.init();
        //this.initDialogs();
        //this.initResize();
        //this.initEvents();
    };

    /**
     * Does configuration of curriculum import object.
     *
     * @param element
     * @param options
     */
    CurriculumImport.prototype.configure = function(element, options)
    {
        var defaults = {
            element: element,
            formSelector: 'form',
            source: {
                tabSelector: '',
                sourceSelect: {
                    selector: 'select[name="source"]'
                }
            },
            curriculum: {
                tabSelector: '',
                levelSelect: {
                    selector: 'select[name="learningLevel"]'
                },
                areaSelect: {
                    selector: 'select[name="learningArea"]'
                },
                optionsUrl: ''
            },
            outcomes: {
                tabSelector: '',
                nameInput: {
                    selector: 'input[name="curriculumName"]'
                },
                nodeTemplate: '',
                outcomeUrl: '',
                returnUrl: ''
            }
        };
        this.config  = $.extend(true, {}, defaults, options);
        this.element = element;
    };

    /**
     * Does configuration of curriculum import object.
     */
    CurriculumImport.prototype.init = function()
    {
        var self = this;
        var element = self.element;
        var config  = self.config;
        var template         = doT.template(config.outcomes.nodeTemplate);
        var learningLevelUrl = config.curriculum.optionsUrl;
        var outcomeUrl       = config.outcomes.outcomeUrl;
        var browseUrl        = config.outcomes.returnUrl;

        var makeTree = function(nodes, destination) {
            nodes.forEach(function(node) {
                var subList = [];
                if (node.children.length) {
                    subList = $('<ul />');
                    makeTree(node.children, subList);
                }
                var listItem = $(template(node))
                    .data('reference', node.reference)
                    .append(subList);
                destination.append(listItem);
            })
        };

        var form         = element.find(config.formSelector);
        var sourceSelect = element.find(config.source.sourceSelect.selector);
        var levelSelect  = element.find(config.curriculum.levelSelect.selector);
        var areaSelect   = element.find(config.curriculum.areaSelect.selector);
        var nameInput    = element.find(config.outcomes.nameInput.selector);

        var sourceTab     = element.find(config.source.tabSelector);
        var curriculumTab = element.find(config.curriculum.tabSelector);
        var outcomesTab   = element.find(config.outcomes.tabSelector);

        sourceTab.on('click', ':submit', function(event) {
            event.preventDefault();
            var value = sourceSelect.val();
            sourceTab.addClass('loading').find(':input').prop('disabled', true);

            // Clear previous settings:
            // remember, if anything changes in a previous wizard step,
            // invalidate steps after it

            // Load the learning areas and levels for the selected source.
            // Do it asynchronously
            if (value) {
                // have some sort of ajax spinner or state change
                $.get(
                    learningLevelUrl,
                    {id: value},
                    $.noop,
                    'json'
                )
                    .done(function(data) {
                        // Remove the old levels, and add new ones
                        levelSelect
                            .prop('disabled', false)
                            .find('option')
                            .filter(function() { return this.value; })
                            .remove();
                        data.levels.forEach(function(level) {
                            $('<option />')
                                .attr('label', level.name)
                                .attr('value', level.name)
                                .data('level', level)
                                .text(level.name)
                                .appendTo(levelSelect);
                        });
                        areaSelect
                            .prop('disabled', false)
                            .find('option')
                            .filter(function() { return this.value; })
                            .remove();
                        data.areas.forEach(function(area) {
                            $('<option />')
                                .attr('label', area.name)
                                .attr('value', area.name)
                                .data('area',  area)
                                .text(area.name)
                                .appendTo(areaSelect);
                        });

                        // Enable the curriculum tab, and switch to it
                        curriculumTab.addClass('loaded');
                        self.enableTab(curriculumTab)
                            .switchToTab(curriculumTab);
    //                            form.tabs('enable', 1)
    //                                .tabs('option', 'active', 1)
                        // Animate next tab?
                    })
                    .fail(function() {
                        // do something else
                        self.disableTab(curriculumTab)
                            .disableTab(outcomesTab)
                            .switchToTab(sourceTab);
    //                            form.tabs('disable', 1)
    //                                .tabs('disable', 2)
    //                                .tabs('option', 'active', 0);
                        sourceTab.addClass('failed');
                    })
                    .always(function() {
                        sourceTab.removeClass('loading').find(':input').prop('disabled', false);
                    });
            } else {
                // Remove all filled in levels
                levelSelect
                    .prop('disabled', true)
                    .find('option')
                    .filter(function() { return this.value; })
                    .remove();
                areaSelect
                    .prop('disabled', true)
                    .find('option')
                    .filter(function() { return this.value; })
                    .remove();
            }
        });

        curriculumTab.on('click', ':submit', function(event) {
            event.preventDefault();
            curriculumTab.addClass('loading').find(':input').prop('disabled', true);
            $.get(
                outcomeUrl,
                {
                    id:    sourceSelect.val(),
                    area:  areaSelect.find(':selected').data('area').reference,
                    level: levelSelect.find(':selected').data('level').reference
                },
                $.noop,
                'json'
            )
                .done(function(data) {
                    // Create a default name
                    nameInput.val(
                        form.find('select[name="learningLevel"] :selected').text()
                        + ' '
                        + form.find('select[name="learningArea"] :selected').text()
                    );
                    // Shove all of this into a tree
                    makeTree(data.outcomes, form.find('#outcomes').empty());
                    curriculumTab.next('section').addClass('loaded');
                    form.find('input[data-role="wizard-step-3-submit"]').prop('disabled', false);
                    self.enableTab(outcomesTab)
                        .switchToTab(outcomesTab);
    //                        form.tabs('enable', 2)
    //                            .tabs('option', 'active', 2);
                    // Enable the submit button
                })
                .fail(function() {
                    // do something else
                    form.find('input[data-role="wizard-step-3-submit"]').prop('disabled', true);
                    self.disableTab(outcomesTab)
                        .switchToTab(curriculumTab);
    //                        form.tabs('disable', 2)
    //                            .tabs('option', 'active', 1);
                    curriculumTab.addClass('failed');
                })
                .always(function() {
                    curriculumTab.removeClass('loading').find(':input').prop('disabled', false);
                });
        });

        outcomesTab.on('click', ':submit', function(event) {
            event.preventDefault();
            // Save this box
            // Craft a request, consisting of:
            // Source
            // Learning area and level (with or without an ID)
            //  Use the reference instead?
            // A map of outcomes
            // (Allow editing after the fact)

            outcomesTab.addClass('loading').find(':input').prop('disabled', true);

            var formData = {
                id:     sourceSelect.val(),
                area:   areaSelect.find(':selected').data('area').reference,
                level:  levelSelect.find(':selected').data('level').reference,
                name:   nameInput.val()
            };

            $.ajax({
                url:       form.attr('action'),
                data:      formData,
                dataType: 'json',
                type:     'post'
            })
                .done(function(data) {
                    // Save succeeded, redirect somewhere
                    outcomesTab.addClass('loaded');
                    window.location.href = browseUrl+'#'+data.outcomes[0].id;
                })
                .fail(function() {
                    // do something else
                    outcomesTab.addClass('failed');
                })
                .always(function() {
                    outcomesTab.removeClass('loading').find(':input').prop('disabled', false);
                });
        });

        // Ensure that I can't submit without selecting a source
        sourceSelect.on('change', function() {
            sourceTab.find(':submit').prop('disabled', !this.value);
        });

        // Ensure that I can't submit without selecting a learning area and level
        $().add(levelSelect).add(areaSelect).on('change', function() {
            curriculumTab.find(':submit').prop('disabled', !(areaSelect.val() && levelSelect.val()));
        });
    };

    /**
     * Gets the title link for the given tab, so it can be enabled, disabled
     * or switched to.
     *
     * @param tab
     * @returns element
     * @private
     */
    CurriculumImport.prototype._findTabTitle = function(tab)
    {
        return this.element
            .find(this.config.formSelector)
            .find('[data-tab] [href="#'+tab.attr('id')+'"]');
    };

    /**
     * Enables this tab.
     *
     * @param tab
     * @returns {CurriculumImport}
     */
    CurriculumImport.prototype.enableTab = function(tab)
    {
        // All the ways that disabled could be set
        this._findTabTitle(tab)
            .removeClass('disabled')
            .removeAttr('disabled')
            .prop('disabled', false);
        return this;
    };

    /**
     * Disables this tab.
     *
     * @param tab
     * @returns {CurriculumImport}
     */
    CurriculumImport.prototype.disableTab = function(tab)
    {
        // All the ways that disabled could be set
        this._findTabTitle(tab)
            .addClass('disabled')
            .attr('disabled', 'disabled')
            .prop('disabled', true);
        return this;
    };

    /**
     * Switches to this tab. Also removes any loaded or failed classes.
     *
     * @param tab
     * @returns {CurriculumImport}
     */
    CurriculumImport.prototype.switchToTab = function(tab)
    {
        this._findTabTitle(tab).click();
        setTimeout(function() {
            tab.removeClass('loaded failed');
        }, 2000);
        return this;
    };

    /**
     * Curriculum Import
     *
     * @param options If a string: call this method (with arguments) on the import already on this element
     *                If an object: setup the import
     */
    $.fn.curriculumImport = function(options) {
        if (typeof options === 'string') {
            var method = options;
            var object = this.data('curriculumImport');
            if (object instanceof CurriculumImport) {
                if (typeof object[method] === 'function') {
                    object[method].call(object, $.makeArray(arguments).slice(1));
                }
            }
        } else {
            this.data('curriculumImport', new CurriculumImport(this, options || {}));
            return this;
        }
    }
})(jQuery);