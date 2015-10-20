/**
 * Curriculum Browser
 */
(function() {
    "use strict";

    /**
     * Curriculum browser object
     *
     * @param element
     * @param options
     * @constructor
     */
    var CurriculumBrowser = function(element, options)
    {
        this.configure(element, options);
        this.initTree();
        this.initDialogs();
        this.initResize();
        this.initEvents();
    };

    /**
     * Does configuration of curriculum browser object.
     *
     * @param element
     * @param options
     */
    CurriculumBrowser.prototype.configure = function(element, options)
    {
        var defaults = {
            element: element,
            areas: {
                data: [],
                dialog: $()
            },
            levels: {
                data: [],
                dialog: $()
            },
            view: {
                readonly: false,
                selector: '[data-role="curriculum-editor"]',
                editFormSelector: '[data-role="node-form"]',
                createUri: '',
                templates: {
                    view: '',
                    edit: ''
                },
                dialog: $()
            },
            tree: {
                data: [],
                selector: '[data-role="curriculum-tree"]'
            }
        };
        var config   = $.extend({}, defaults, options);
        config.view.templates.view = doT.template(config.view.templates.view);
        config.view.templates.edit = doT.template(config.view.templates.edit);
        this.config  = config;
        this.element = element;
        this.viewPanel = config.element.find(config.view.selector);
        this.treePanel = config.element.find(config.tree.selector);
    };

    /**
     * Updates the contents of either the view panel or the dialog box,
     * depending on whether the view panel is visible.
     *
     * @param contents
     *
     * @return the panel/dialog that was updated, so that events may be attached to it.
     */
    CurriculumBrowser.prototype.updateViewPanel = function(contents)
    {
        // Empty both the dialog and view panel
        this.viewPanel.empty();
        this.config.view.dialog.children().not('.close-reveal-modal').remove();
        if (this.viewPanel.is(':visible')) {
            this.viewPanel.append(contents);
            return this.viewPanel;
        } else {
            // Don't reopen if already open, just replace
            if (!this.config.view.dialog.is(':visible')) {
                this.config.view.dialog.foundation('reveal', 'open');
            }
            this.config.view.dialog.append(contents);
            return this.config.view.dialog;
        }
    };

    /**
     * Builds a readonly view of the curriculum node
     *
     * @param actions
     * @param data
     */
    CurriculumBrowser.prototype.buildView = function(actions, data)
    {
        var self = this;

        // Update data
        data.actions  = actions;
        data.readonly = self.config.readonly;

        var viewPanel = self.updateViewPanel(self.config.view.templates.view(data));

        // Add an edit existing curriculum event to the edit button here
        viewPanel.find('[data-interaction="edit"]').click(function(event) {
            event.preventDefault();
            self.buildForm(actions, data);
        });
        // Add child to existing curriculum
        viewPanel.find('[data-interaction="add-subsection"]').click(function(event) {
            event.preventDefault();
            self.buildForm({ save:  self.config.view.createUri }, { parent: $(this).data('nodeid') }, data);
        });
    };

    /**
     * Builds a writable view of the curriculum node
     *
     * @param actions
     * @param data
     * @param previous If creating a subsection, fall back to this data
     */
    CurriculumBrowser.prototype.buildForm = function(actions, data, previous)
    {
        var self = this;
        tinyMCE.execCommand('mceRemoveControl', true, 'curriculumDescription');

        // Update data
        data.actions = actions;
        data.areas   = self.config.areas.data;
        data.levels  = self.config.levels.data;

        var viewPanel = self.updateViewPanel(self.config.view.templates.edit(data));

        // Cancel editing event
        viewPanel.find('[data-interaction="cancel"]').click(function(event) {
            event.preventDefault();
            // Is data empty? Load data from whatever the current node is.
            if (!data.id) {
                self.buildView(actions, previous);
            } else {
                self.buildView(actions, data);
            }
        });
        tinyMCE.execCommand('mceAddControl', true, 'curriculumDescription');
    };

    /**
     * Sets up the JSTree
     */
    CurriculumBrowser.prototype.initTree = function()
    {
        var self = this;

        // The select node event
        var selectNodeEvent = function(event, jstree) {
            // Load this node in the node browser
            // read URL comes from the node I just clicked
            self.viewPanel
                .addClass('loading')
                .find(':input').prop('disabled', true);

            $.getJSON(jstree.node.a_attr.href+'.json')
                .done(function(data) {
                    // On initial load: don't open this dialog!
                    self.buildView(
                        {
                            save:   jstree.node.a_attr['data-saveuri'],
                            delete: jstree.node.a_attr['data-deleteuri']
                        },
                        data.item
                    );
                })
                .fail(function() {
                    self.viewPanel.addClass('failed');
                })
                .always(function() {
                    self.viewPanel.removeClass('loading');
                    setTimeout(function() {
                        self.viewPanel.removeClass('loaded failed');
                    }, 2000)
                });
        };

        var loaded = false;
        self.treePanel.jstree({
            core: {
                animation: 50,
                data: self.config.tree.data,
                force_text: true,
                multiple: false,
                check_callback: true,
                themes: {
                    variant: "resourcetree",
                    dots: true,
                    icons: true,
                    responsive: false
                }
            },
            plugins : ['sort', 'types', 'wholerow'],
            types: {
                root: {
                    icon: 'icon-folder'
                },
                'default': {
                    icon: 'icon-unknown'
                },
                folder: {
                    icon: 'icon-folder'
                },
                code: {
                    icon: 'icon-favourite'
                }
            }
        })
            .on('select_node.jstree', function(event, jstree) {
                // Open a node when you select it
                jstree.instance.open_node(jstree.node.id);
            })
            .on('ready.jstree', function(event, jstree) {
                // Is there a hash? If so, open all nodes below that hash.
                // (but maybe not curriculum codes)
                var match = window.location.hash.match(/^#(.*)/);
                if (match) {
                    jstree.instance.select_node(match[1]);
                    jstree.instance.open_all(match[1]);
                }
                loaded = true;
            })
            .on('select_node.jstree', function(event, jstree) {
                if (self.viewPanel.is(':visible') || loaded) {
                    selectNodeEvent(event, jstree);
                }
            });
        self.tree = $.jstree.reference(self.config.tree.selector);
    };

    /**
     * Sets up dialog boxes
     */
    CurriculumBrowser.prototype.initDialogs = function()
    {
        var self = this;
        // Create new level/area submit event
        var allDialogs = $(self.config.areas.dialog).add(self.config.levels.dialog);
        allDialogs.on('submit', 'form', function(event) {
            event.preventDefault();
            self.viewPanel.addClass('loading');
            var form     = $(this);
            var selector = form.data('relation');
            var submit   = form.find(':submit').prop('disabled', true);
            var ajax     = $.ajax({
                method: this.method,
                url:    this.action,
                data:   form.serializeArray()
            });
            ajax
                .done(function(data) {
                    // Update what gets chosen
                    self.viewPanel.addClass('loaded');
                    var nameInput = form.find('input[name="name"]');
                    var name      = nameInput.val();
                    nameInput.val('');
                    self.viewPanel.find(self.config.view.editFormSelector).find(selector).append(
                        $('<option />')
                            .attr('value', data.item.id)
                            .attr('label', name)
                            .prop('selected', true)
                            .text(name)
                    );
                    allDialogs.foundation('reveal', 'close');
                })
                .fail(function() {
                    // Not sure?
                    self.viewPanel.addClass('failed');
                })
                .always(function() {
                    // Re-enable submit button no matter what
                    self.viewPanel.removeClass('loading');
                    submit.prop('disabled', false);
                });
        });
    };

    /**
     * Sets up events on the curriculum browser
     */
    CurriculumBrowser.prototype.initEvents = function()
    {
        var self = this;

        var viewPanels = $().add(self.viewPanel).add(self.config.view.dialog);

        // Triggers for adding new levels or areas
        viewPanels.on('change', self.config.view.editFormSelector+' select', function(event) {
            var option      = $(this).find(':selected');
            var interaction = option.data('interaction');
            switch (interaction) {
                case 'add-level-dialog-open':
                    event.preventDefault();
                    self.config.levels.dialog.foundation('reveal', 'open');
                    break;
                case 'add-area-dialog-open':
                    event.preventDefault();
                    self.config.areas.dialog.foundation('reveal', 'open');
                    break;
                default:
                    break;
            }
        });

        // Node save form submission
        viewPanels.on('submit', self.config.view.editFormSelector, function(event) {
            event.preventDefault();

            // Do it via AJAX instead
            var form     = $(this);
            var formData = form.serializeArray();
            viewPanels
                .addClass('loading')
                .find(':input').prop('disabled', true);
            $.ajax({
                type:     this.method,
                data:     formData,
                dataType: 'json',
                url:      this.action
            })
                .done(function(data) {
                    // Is this a newly created node? If so, add it in.
                    // self.tree has no comprehensive updateNode, so update what I can.
                    viewPanels.addClass('loaded');
                    if (self.tree.get_node(data.item.id)) {
                        self.tree.rename_node(data.item.id, data.tree.text);
                    } else {
                        self.tree.create_node(data.tree.parent, data.tree);
                        self.tree.deselect_all(true);
                        self.tree.select_node(data.tree.id);
                    }
                })
                .fail(function() {
                    viewPanels.addClass('failed');
                })
                .always(function() {
                    viewPanels.removeClass('loading')
                        .find(':input').prop('disabled', false);
                    setTimeout(function() {
                        viewPanels.removeClass('loaded failed');
                    }, 2000);
                    if (self.config.view.dialog.is(':visible')) {
                        self.config.view.dialog.foundation('reveal', 'close');
                    }
                });
        });
        
        // Delete node form
        viewPanels.on('click', '[data-interaction="delete"]', function(event) {
            event.preventDefault();
            var element = $(this);
            element.confirmDialog({
                text: 'Are you sure? This will delete ALL curriculum sections underneath this one, too.',
                confirmEvent: function() {
                    viewPanels
                        .addClass('loading')
                        .find(':input').prop('disabled', true);
                    $.ajax({
                        type:     'post',
                        dataType: 'json',
                        url:      element.data('action')
                    })
                        .done(function(data) {
                            viewPanels.addClass('loaded');
                            self.tree.delete_node(data.item.id);
                            self.tree.select_node(data.tree.parent);
                        })
                        .fail(function() {
                            viewPanels.addClass('failed');
                        })
                        .always(function() {
                            viewPanels.removeClass('loading')
                                .find(':input').prop('disabled', false);
                            setTimeout(function() {
                                viewPanels.removeClass('loaded failed');
                            }, 2000);
                            if (self.config.view.dialog.is(':visible')) {
                                self.config.view.dialog.foundation('reveal', 'close');
                            }
                        });
                }
            });
        });
    };

    /**
     * Sets up resizing of the three panel browser
     *
     * TODO include this in an external plugin somehow?
     */
    CurriculumBrowser.prototype.initResize = function()
    {
        var self = this;
        var resizePanels = function() {
            var calcd = $(window).height() - $('#hybrid-bar').height() - $('#footer').height() - self.viewPanel.height() - 120;
            self.treePanel.height(Math.max(200, calcd));
        };
        $(window).resize(resizePanels);
        resizePanels();
    };

    /**
     * Opens the create form for this curriculum browser.
     */
    CurriculumBrowser.prototype.openCreateForm = function()
    {
        this.buildForm({ save: this.config.view.createUri }, {});
    };

    /**
     * Curriculum Browser
     *
     * @param options If a string: call this method (with arguments) on the CB already on this item
     *                If an object: setup the CB
     */
    $.fn.curriculumBrowser = function(options) {
        if (typeof options === 'string') {
            var method  = options;
            var browser = this.data('curriculumBrowser');
            if (browser instanceof CurriculumBrowser) {
                if (typeof browser[method] === 'function') {
                    browser[method].call(browser, $.makeArray(arguments).slice(1));
                }
            }
        } else {
            this.data('curriculumBrowser', new CurriculumBrowser(this, options || {}));
            return this;
        }
    }
})(jQuery);
