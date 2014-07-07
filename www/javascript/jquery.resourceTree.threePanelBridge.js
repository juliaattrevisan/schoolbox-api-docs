/**
 * Bridging plugin between threePanel and resourceTree, it abstracts away 3panel
 * related events.
 */
(function($) {
    /**
     * @param element  the jQuery object to bind the threePanel to.
     * @param options
     *   threePanel:   the ThreePanel object to bind to
     *   selectedNode: the initially selected node. This will be the ID of a
     *                 node in the JsTree tree.
     * @constructor
     */
    var ResourceTreeThreePanelBridge = function(element, options) {
        var self     = this;
        var defaults = {
            threePanel: null,
            selected:   null
        }

        self.element = element;
        self.options = $.extend(true, {}, defaults, options);
        self.threePanel = self.options.threePanel;

        // Ensure callbacks are bound to this object, instead of to the DOM node
        // they were triggered on.
        element
            .on('redraw.jstree',      self.onRedraw.bind(self))
            .on('select_node.jstree', self.onSelectNode.bind(self))
            .on('move_node.jstree',   self.onMoveNode.bind(self))
            .on('after_open.jstree',  self.onAfterOpen.bind(self))
            .on('drop', 'li[role="treeitem"]', self.onDropOntoTreeNode.bind(self))
    };

    /**
     * Redraw node event for the JsTree plugin - ensures that files are
     * initialized for all open nodes.
     *
     * @param event  The event object
     * @param jstree The JsTree object
     */
    ResourceTreeThreePanelBridge.prototype.onRedraw = function(event, jstree) {
        var self = this;
        jstree.nodes.forEach(function(node) {
            self.initializeFiles(node);
        })
    }

    /**
     * Select node event for the JsTree plugin.
     *
     * Fired on the trigger of a select node event.
     *
     * @param event  The event object
     * @param jstree The JsTree object
     */
    ResourceTreeThreePanelBridge.prototype.onSelectNode = function(event, jstree) {
        var self       = this;
        var relationID = jstree.node.id;

        /* hide and deselect all files */
        // threePanel.unselectFiles();
        $('#file-list tbody tr').removeClass('selected').hide();

        // threePanel.getFiles(relationId);
        var files = $('tr.category-' + relationID);

        self.threePanel.getMeta(relationID);

        if (files.length > 0) {
            // threePanel.showFiles(relationId);
            /* files exist, show them */
            files.show();
            self.initializeFiles(relationID);

            /* re-apply on off */
            // TODO use CSS :even selector instead
            $('#file-list tbody tr').removeClass('dark');
            $('#file-list tbody tr:visible:odd:not(.empty)').addClass('dark');

            /* remember tree branch */
            // threePanel.on('selectBranch');
            $.post('/ajax/tree.php', {resourcesID : relationID});

        }
        else {

            /* ajax load files */
            // threePanel.loadFiles(relationId)
            $.post('/ajax/file.php', {categoryID : relationID}, function (data) {
                if (data.error) {
                    alert(data.error);
                }
                else if (data.length == 0) {
                    /* display empty message */
                    $('<tr class="category-' + relationID + ' empty"><td>No files to display</td></tr>').appendTo($('#file-list'));

                }
                else {
                    var template = $('#file-template');
                    for (i in data) {
                        var row = data[i];

                        /* build new file (row) */
                        var clone = template.clone();
                        clone.attr('id', 'file-' + row.id);
                        clone.find('span.file').text(row.description);
                        clone.find('td:eq(1)').text(row.user);
                        clone.find('td:eq(2)').text(row.relative);
                        clone.addClass('category-' + relationID);
                        clone.addClass(row.mimeClass);

                        if (row.url)
                            clone.addClass('link');

                        if (row.hidden == 1)
                            clone.addClass('hidden');

                        clone.appendTo($('#file-list')).show();
                    }
                }

                /* re-apply on off */
                $('#file-list tbody tr').removeClass('dark');
                $('#file-list tbody tr:visible:odd:not(.empty)').addClass('dark');

//                threePanel.initializeFiles();
                self.initializeFiles(relationID);

            }, 'json');
        }
    };

    /**
     * Move node event for the JsTree plugin.
     *
     * @param event
     * @param data
     */
    ResourceTreeThreePanelBridge.prototype.onMoveNode = function(event, data) {
        var failMessage = function(error) {
            var message = 'An error occurred during processing. Please reload your browser and try again.';
            if (error) {
                message += '\nError: ' + message;
            }
            alert(message);
        }
        $.ajax({
            type: 'post',
            url:  '/cms/categoryForm.php',
            data: { move: data.node.id, categoryID: data.parent},
            dataType: 'json'
        })
        .done(function(data) {
            if (data && data.error) {
                failMessage(data.error)
            }
        })
    };

    /**
     * After open node event for the JsTree plugin.
     *
     * @param event
     * @param tree  The JsTree object
     */
    ResourceTreeThreePanelBridge.prototype.onAfterOpen = function(event, tree) {
        // Set up folders as drop targets for files
        $.each(tree.node.children, function(i,v) {
            tree.instance.get_node(v, true).droppable({
                scope: 'resourceFile'
            })
        });
    };

    /**
     * Drop event for leaves within the JsTree plugin.
     *
     * @param event
     * @param ui    The jQuery UI object
     */
    ResourceTreeThreePanelBridge.prototype.onDropOntoTreeNode = function(event, ui) {
        // Drop event for files
        // This drop event bubbles up the list of droppables, so trigger it
        // only if this.id and event.target.id match.
        var newCategoryId = this.id;
        if (newCategoryId == event.target.id) {
            ui.draggable
                .closest('tr')
                .add('tr.selected', ui.draggable.closest('table'))
                .each(function() {
                    var fileElement   = $(this);
                    var fileId        = fileElement.attr('id').replace(/\D/g, '');
                    var oldCategoryId = 0;
                    fileElement.attr('class').split(/\s+/).forEach(function(className) {
                        var matches = className.match(/^category-(\d+)$/);
                        if (matches) {
                            oldCategoryId = matches[1];
                        }
                    })
                    // Make sure every single file gets moved
                    $.post(
                        '/cms/fileForm.php',
                        { move: fileId, categoryID: newCategoryId },
                        function(data) {
                            // move the file from old category into new
                            // Clear all in new category, as it's changed
                            $('tr#file-' + fileId).remove();
                            $('tr.category-' + newCategoryId).remove();
                        },
                        'json'
                    );
                });
        }
    };

    ResourceTreeThreePanelBridge.prototype.initializeFiles = function(relationID) {
        var newChildren  = $('tr.category-' + relationID + ':not(.empty)'),
            categoryNode = $('#' + String(relationID)),
            self         = this;

        /* double click files, default action */
        newChildren.dblclick(function() {
            if ($(this).hasClass("link")) {
                window.open("/redir.php?id="+$(this).attr("id").substring(5),"_blank");
            }
            else {
                window.open("/send.php?id="+$(this).attr("id").substring(5),"_blank");
            }
        });

        /* select files */
        newChildren.click(function (event) {

            /* allow multiple select */
            if (!event.ctrlKey && !event.shiftKey)
                $('#file-list tr.selected').removeClass('selected');

            if (event.ctrlKey)
                $(this).toggleClass('selected');
            else
                $(this).addClass('selected');

            /* select range */
            if (event.shiftKey) {

                var node1 = $('#file-list tr').index($('#file-list tr.current'));
                var node2 = $('#file-list tr').index(this);

                if (node1 > node2)
                    $('#file-list tr').slice(node2, node1).addClass('selected');
                else
                    $('#file-list tr').slice(node1, node2).addClass('selected');
            }

            $('#file-list tr.current').removeClass('current');
            $(this).addClass('current');

            self.threePanel.getMeta(relationID, $('#file-list tr.selected'))

        });

        /* drag files */
        var permission = categoryNode.data('permission');
        if (permission === 'full' || categoryNode === 'write') {
            newChildren.find('span.file').draggable({
                scope: 'resourceFile',
                cursor: 'move',
                cursorAt: {top: 5, left: 5},
                helper: function (event) {
                    // How many items are selected?
                    // More than one - show the number of files, not the name.
                    var length = $('#file-list tr.selected').length;
                    var node   = $(event.currentTarget).clone().addClass('accept').css('z-index', 100);
                    if (length > 1) {
                        node.text('('+String(length)+' files)');
                    }
                    return node;
                },
                opacity: 1,
                revert: "invalid",
                revertDuration: 300,
                scroll: true
            });
        }
    };

    /**
     * Plugin for the bridge between threePanel and the resource tree.
     *
     * @param options
     *   threePanel:   the ThreePanel object to bind to
     *   selectedNode: the initially selected node. This will be the ID of a
     *                 node in the JsTree tree.
     * @returns this
     */
    $.fn.resourceTreeThreePanelBridge = function(options) {
        if (options.threePanel) {
            this.each(function() {
                var element = $(this);
                element.data(
                    'resourceTreeThreePanelBridge',
                    new ResourceTreeThreePanelBridge(element, options)
                );
            })
        }
        return this;
    };
})(jQuery);
