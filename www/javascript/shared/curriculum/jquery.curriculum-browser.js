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
        this.init();
        this.initDialogs();
        this.initResize();
    };

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
                createUri: '',
                templates: {
                    view: '',
                    edit: ''
                }
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
        self.viewPanel
            .empty()
            .append(self.config.view.templates.view(data));
        // Add an edit existing curriculum event to the edit button here
        self.viewPanel.find('#edit-button').click(function(event) {
            event.preventDefault();
            self.buildForm(actions, data);
        });
        // Add child to existing curriculum
        self.viewPanel.find('#create-child-button').click(function(event) {
            event.preventDefault();
            var form = $(this).closest('form');
            self.buildForm({ save:  self.config.view.createUri }, { parent: form.data('nodeid') }, data);
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
        self.viewPanel
            .empty()
            .append(self.config.view.templates.edit(data));

        // Cancel editing event
        self.viewPanel.find('#cancel-button').click(function(event) {
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
     * Sets up the events and stuff on the curriculum browser
     */
    CurriculumBrowser.prototype.init = function()
    {
        var self   = this;
        var config = self.config;
        var formPanel = self.viewPanel;
        var tree      = self.treePanel;

        tree.jstree({
            core: {
                animation: 50,
                data: config.tree.data,
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
            plugins: ['sort', 'types'],
            types: {
                root: {
                    icon: '/images/tree/server.gif'
                },
                'default': {
                    icon: '/images/icons/22x22/folder.png'
                },
                folder: {
                    icon: '/images/icons/22x22/folder.png'
                },
                code: {
                    icon: '/images/famicons/star.png'
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
            })
            .on('select_node.jstree', function(event, jstree) {
                // Load this node in the node browser
                // read URL comes from the node I just clicked
                formPanel
                    .addClass('loading')
                    .find(':input').prop('disabled', true);
                $.getJSON(jstree.node.a_attr.href+'.json')
                    .done(function(data) {
                        self.buildView(
                            {
                                save:   jstree.node.a_attr['data-saveuri'],
                                delete: jstree.node.a_attr['data-deleteuri']
                            },
                            data.item
                        );
                    })
                    .fail(function() {
                        formPanel.addClass('failed');
                    })
                    .always(function() {
                        formPanel.removeClass('loading');
                        setTimeout(function() {
                            formPanel.removeClass('loaded failed');
                        }, 2000)
                    });
            });

        var jstree = $.jstree.reference('[data-role="curriculum-tree"]');

        // Triggers for adding new levels or areas
        formPanel.on('change', '#node-form select', function(event) {
            var option      = $(this).find(':selected');
            var interaction = option.data('interaction');
            switch (interaction) {
                case 'add-level-dialog-open':
                    event.preventDefault();
                    config.levels.dialog.foundation('reveal', 'open');
                    break;
                case 'add-area-dialog-open':
                    event.preventDefault();
                    config.areas.dialog.foundation('reveal', 'open');
                    break;
                default:
                    break;
            }
        });

        // Node save form submission
        formPanel.on('submit', '#node-form', function(event) {
            event.preventDefault();

            // Do it via AJAX instead
            var form     = $(this);
            var formData = form.serializeArray();
            formPanel
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
                    // jsTree has no comprehensive updateNode, so update what I can.
                    formPanel.addClass('loaded');
                    if (jstree.get_node(data.item.id)) {
                        jstree.rename_node(data.item.id, data.tree.text);
                    } else {
                        jstree.create_node(data.tree.parent, data.tree);
                        jstree.deselect_all(true);
                        jstree.select_node(data.tree.id);
                    }
                })
                .fail(function() {
                    formPanel.addClass('failed');
                })
                .always(function() {
                    formPanel.removeClass('loading')
                        .find(':input').prop('disabled', false);
                    setTimeout(function() {
                        formPanel.removeClass('loaded failed');
                    }, 2000)
                });
        });

        // IE9 doesn't support form attribute on inputs.
        // Add a click delete button polyfill.
        if (!('form' in $('<input>')[0])) {
            formPanel.on('click', '#delete-button', function(event) {
                event.preventDefault();
                formPanel.find('#node-delete-form').submit();
            });
        }

        // Delete node form
        formPanel.on('submit', '#node-delete-form', function(event) {
            event.preventDefault();
            formPanel
                .addClass('loading')
                .find(':input').prop('disabled', true);
            $.ajax({
                type:     this.method,
                dataType: 'json',
                url:      this.action
            })
                .done(function(data) {
                    formPanel.addClass('loaded');
                    jstree.delete_node(data.item.id);
                    jstree.select_node(data.tree.parent);
                })
                .fail(function() {
                    formPanel.addClass('failed');
                })
                .always(function() {
                    formPanel.removeClass('loading')
                        .find(':input').prop('disabled', false);
                });
        });

        // Create new curriculum
        $('[data-interaction="create-curriculum"]').on('click', function(event) {
            event.preventDefault();
            self.buildForm({ save: config.view.createUri }, {});
        });
    };



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
                    self.viewPanel.find('#node-form').find(selector).append(
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
     * Sets up resizing of the three panel browser
     *
     * TODO fix panes so this isn't needed
     */
    CurriculumBrowser.prototype.initResize = function()
    {
        var self = this;
        var resizePanels = function() {
            $().add(self.viewPanel).add(self.treePanel).outerHeight(
                $('body').height() - 50
            );
        };
        $(window).resize(resizePanels);
        resizePanels();
    };

    /**
     * Curriculum Browser
     *
     * @param options
     */
    $.fn.curriculumBrowser = function(options) {
        var element = $(this);
        element.data('curriculumBrowser', new CurriculumBrowser(element, options || {}));
    }
})(jQuery);
