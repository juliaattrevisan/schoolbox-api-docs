/**
 * Bridging plugin between threePanel and resourceTree, it abstracts away 3panel
 * related events.
 */
(function($) {
    /**
     * @param element  the jQuery object to bind the threePanel to.
     * @param options
     *   threePanel: the ThreePanel object to bind to
     *   moveFolder:
     *     a callback that accept the following params:
     *       self:            This object
     *       folderNode:      The folder to move
     *       destinationNode: The folder to move it to
     *     and returns an object to use as the parameter to jQuery.ajax
     *   moveFile:
     *     a callback that accept the following params:
     *       self:            This object
     *       fileNode:        The file to move
     *       destinationNode: The folder to move it to
     *     and returns an object to use as the parameter to jQuery.ajax
     * @constructor
     */
    var ResourceTreeThreePanelBridge = function(element, options) {
        var self     = this;
        var defaults = {
            threePanel: null,
            moveFolder: function(self, folderNode, destinationNode) {
                return {
                    type: 'post',
                    url:  '/cms/categoryForm.php',
                    data: {
                        move:       folderNode.attr('id'),
                        categoryID: destinationNode.attr('id')
                    },
                    dataType: 'json'
                }
            },
            moveFile: function(self, fileNode, destinationNode) {
                var url = '/cms/fileForm.php';
                if (fileNode.hasClass('link')) {
                    url = '/cms/linkForm.php';
                }
                return {
                    type: 'post',
                    url:  url,
                    data: {
                        move:       fileNode.attr('id').substring(5),
                        categoryID: destinationNode.attr('id')
                    },
                    dataType: 'json'
                }
            },
            loadFolderFiles: function(self, folderId) {
                return {
                    type: 'post',
                    url:  '/ajax/file.php',
                    data: {categoryID : folderId},
                    dataType: 'json'
                }
            },
            savePosition: function(self, folderId) {
                return {
                    type: 'post',
                    url:  '/ajax/tree.php',
                    data: {resourcesID : folderId},
                    dataType: 'json'
                }
            },
            drawFiles: function(self, categoryId, data) {
                if (data.error) {
                    alert(data.error);
                }
                else if (data.length == 0) {
                    /* display empty message */
                    $('<tr class="category-' + categoryId + ' empty"><td>No files to display</td></tr>').appendTo($('#file-list'));
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
                        clone.addClass('category-' + categoryId);
                        clone.addClass(row.mimeClass);

                        if (row.url) {
                            clone.addClass('link');
                        }

                        if (row.hidden == 1) {
                            clone.addClass('hidden');
                        }

                        clone.appendTo($('#file-list')).show();
                    }
                }
            }
        }

        self.element = element;
        self.options = $.extend(true, {}, defaults, options);
        self.threePanel = self.options.threePanel;
        self.hoveredId  = null;
        self.selectedId = null;

        // Ensure callbacks are bound to this object, instead of to the DOM node
        // they were triggered on.
        element
            .on('redraw.jstree',       self.onRedraw.bind(self))
            .on('select_node.jstree',  self.onSelectNode.bind(self))
            .on('move_node.jstree',    self.onMoveNode.bind(self))
            .on('after_open.jstree',   self.onAfterOpen.bind(self))
            .on('hover_node.jstree',   self.onHoverNode.bind(self))
            .on('dehover_node.jstree', self.onDehoverNode.bind(self))
            .droppable({
                scope: 'resourceFile',
                hoverClass: "accept",
                greedy: true,
                tolerance: 'pointer'
            })
            .on('drop',                self.onDropOntoTreeNode.bind(self))
        ;

        // select_node doesn't trigger on initial tree load.
        // The best I can do is init files when I hover over the list.
        if (self.options.selected) {
            $('#file-window').one(
                'hover',
                'tr.category-' + self.options.selected + ':not(.empty)',
                function() {
                    self.initializeFiles(self.options.selected);
                }
            )
        }
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
     * @param event    The event object
     * @param jstree   The JsTree object
     * @param callback A callback to be executed when node selection is complete
     */
    ResourceTreeThreePanelBridge.prototype.onSelectNode = function(event, jstree, callback) {
        var self       = this,
            relationID = jstree.node.id,
            allFiles   = $('#file-list').find('tr'),
            files      = allFiles.filter('.category-' + relationID).not('.inactive'),
            ghosts     = allFiles.filter('.inactive.category-' + relationID),
            ajaxParams;

        /* deselect all files */
        // threePanel.clearSelection();
        allFiles.removeClass('selected');

        // threePanel.getFiles(relationId);

        self.selectedId = relationID;

        self.threePanel.getMeta(relationID);

        if (files.length > 0) {
            // threePanel.showFiles(relationId);
            allFiles.hide();
            /* files exist, show them */
            files.show();
            self.initializeFiles(relationID);

            /* re-apply on off */
            // TODO use CSS :even selector instead
//            $('#file-list tbody tr').removeClass('dark');
//            $('#file-list tbody tr:visible:odd:not(.empty)').addClass('dark');

            /* remember tree branch */
            // threePanel.on('selectBranch');
            ajaxParams = self.options.savePosition(self, relationID);
            if (ajaxParams) {
                $.ajax(ajaxParams).done(function () {
                    ghosts.detach();

                    if ($.isFunction(callback)) {
                        callback();
                    }
                });
            }
        }
        else {

            /* ajax load files */
            // threePanel.loadFiles(relationId)
            ajaxParams = self.options.loadFolderFiles(self, relationID);
            $.ajax(ajaxParams)
                .done(function(data) {
                    ghosts.detach();
                    allFiles.hide();

                    if ($.isFunction(callback)) {
                        callback();
                    }

                    // draw callback
                    self.options.drawFiles(self, relationID, data);

                    /* re-apply on off */
//                    $('#file-list tbody tr').removeClass('dark');
//                    $('#file-list tbody tr:visible:odd:not(.empty)').addClass('dark');
//
//                    threePanel.initializeFiles();
                    self.initializeFiles(relationID);
                })
        }
    };

    /**
     * Move node event for the JsTree plugin.
     *
     * @param event
     * @param data
     */
    ResourceTreeThreePanelBridge.prototype.onMoveNode = function(event, jstree) {

        var folderNode      = jstree.instance.get_node(jstree.node.id, true);
        var destinationNode = jstree.instance.get_node(jstree.parent,  true);

        // DOM node might not exist yet, if I dragged into a folder I just expanded.
        // Output a virtual node instead with that ID if so, so the interface is at least consistent
        if (folderNode.length === 0) {
            folderNode      = $('<div />').attr('id', jstree.node.id);
        }
        if (destinationNode.length === 0) {
            destinationNode = $('<div />').attr('id', jstree.parent);
        }

        var self = this;
        var failMessage = function(error) {
            var message = 'An error occurred during processing. Please reload your browser and try again.';
            if (error) {
                message += '\nError: ' + message;
            }
            alert(message);
        }

        // Fill out ajax params with callback
        var ajaxParams = self.options.moveFolder(this, folderNode, destinationNode);
        $.ajax(ajaxParams)
            .done(function(data) {
                if (data && data.error) {
                    failMessage(data.error)
                }
            })
    };

    /**
     * After open node event for the JsTree plugin - initializes files for this
     * event.
     *
     * @param event
     * @param tree  The JsTree object
     */
    ResourceTreeThreePanelBridge.prototype.onAfterOpen = function(event, tree) {
        this.initializeFiles(tree.node.id);
    };

    /**
     * Hover event for leaves within the JsTree plugin - sets hoveredId so that
     * drop event has access to it.
     *
     * @param event
     * @param tree  The JsTree object
     */
    ResourceTreeThreePanelBridge.prototype.onHoverNode = function(event, tree) {
        this.hoveredId = tree.node.id;
    };

    /**
     * Dehover event for leaves within the JsTree plugin - clears hoveredId.
     *
     * @param event
     * @param tree  The JsTree object
     */
    ResourceTreeThreePanelBridge.prototype.onDehoverNode = function(event, tree) {
        this.hoveredId = null;
    };

    /**
     * Drop event for leaves within the JsTree plugin.
     *
     * @param event
     * @param ui    The jQuery UI object
     */
    ResourceTreeThreePanelBridge.prototype.onDropOntoTreeNode = function(event, ui) {

        // For some unknown reason setting each leaf droppable doesn't
        // work - I can't drop onto a node with children.
        // Why not just set the whole tree as droppable and figure out
        // which node is selected?
        var self          = this,
            scope         = $(event.currentTarget).droppable('option', 'scope'),
            node          = $(ui.draggable).closest("tr"),
            destinationId = this.hoveredId,
            fileContainer = node.parent()
        if (scope != 'resourceFile') {
            return;
        }
        if (!destinationId) {
            return;
        }

        var destinationNode = $(event.currentTarget).find('#'+destinationId);

        // Move all files, not just the one directly selected
        var nodeList = node.add(fileContainer.find('tr.selected'));
        nodeList.each(function() {
            var node     = $(this),
                isLink   = node.hasClass('link'),
                id       = node.attr('id').substring(5),
                oldClass = this.className.match(/category-[\d]+/).toString(),
                newClass = 'category-'+String(destinationId);

            node.hide();

            // Fill out AJAX params
            var ajaxParams = self.options.moveFile(self, node, destinationNode);
            $.ajax(ajaxParams)
                .done(function(data) {
                    if (data && data.result != 'success') {
                        alert(data.result);
                        node.show();
                    }
                    else {
                        // Put this in a new class?
                        node.remove();
                        // Remove all files from the class I moved to. This
                        // triggers reload next time I view this folder's files.
                        fileContainer.find('.'+newClass).remove();
                    }
                })
                .fail(function(data) {
                    node.show();
                })
        });
    };

    /**
     * Initializes click events for files for the given relation ID.
     *
     * @param relationID
     */
    ResourceTreeThreePanelBridge.prototype.initializeFiles = function(relationID) {
        var newChildren  = $('tr.category-' + relationID + ':not(.empty)'),
            categoryNode = $('#' + String(relationID)),
            self         = this;

        // Unbind and rebind so that events aren't triggered multiple times

        /* double click files, default action */
        newChildren
            .unbind('dblclick.resourceTree.threePanelBridge')
            .bind('dblclick.resourceTree.threePanelBridge', function() {
                if ($(this).hasClass("link")) {
                    window.open("/redir.php?id="+$(this).attr("id").substring(5),"_blank");
                }
                else {
                    window.open("/send.php?id="+$(this).attr("id").substring(5),"_blank");
                }
            });

        /* select files */
        newChildren
            .unbind('click.resourceTree.threePanelBridge')
            .bind('click.resourceTree.threePanelBridge', function (event) {
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
        if (permission === 'full' || permission === 'write') {
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
