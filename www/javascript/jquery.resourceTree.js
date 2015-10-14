/**
 * Resource browser - depends on jsTree plugin.
 *
 * Can tie into three panel.
 */
(function($) {
    /**
     * Helper method for determining whether a node is a folder, for sort purposes.
     *
     * @param jstree
     * @param nodeId
     * @returns {boolean}
     */
    var isFolder = function(jstree, nodeId) {
        switch (jstree.get_type(nodeId)) {
            case 'folder':
            case 'homepage':
            case 'course':
                return true;
            case 'link':
            case 'file':
            default:
                return false;
        }
    };

    /**
     * Sort callback
     *
     * @param jstree
     * @param a
     * @param b
     * @returns {number}
     */
    var resourceTreeSort = function(jstree, a, b) {
        var isAFolder = isFolder(jstree, a);
        var isBFolder = isFolder(jstree, b);
        if (isAFolder && !isBFolder) {
            return -1;
        }
        if (isBFolder && !isAFolder) {
            return 1;
        }
        return jstree.get_text(a) > jstree.get_text(b) ? 1 : -1;
    };

    /**
     * @param element The jQuery element to tie this resource tree to.
     * @param options
     *   data:
     *     cache:         A set of preloaded nodes (see jstree.data)
     *     ajaxParams:    Parameters passed to jQuery.ajax to load child nodes
     *     ajaxDataParam: the parameter to put the node ID to load into
     *   jstree: Any and all jstree parameters
     * @constructor
     */
    var ResourceTree = function(element, options) {
        var self     = this;
        var defaults = {
            data: {
                cache: [],
                ajaxParams: {
                    url:      '/ajax/jstree.php',
                    type:     'GET',
                    data:     {},
                    dataType: 'json',
                    token:    null
                },
                ajaxDataParam: 'parent'
            },
            jstree: {
                animation: 50,
                core: {
                    data: function(node, callback) {
                        // Search the tree for the children of this node.
                        // This assumes that the data has been passed in flat.
                        var children = self.cache.filter(function(child) {
                            return child.parent == node.id;
                        })
                        if (children.length) {
                            callback(children);
                        }
                        else {
                            var ajaxParams = $.extend(true, {}, self.options.data.ajaxParams);

                            if (ajaxParams.token) {
                                ajaxParams.url = ajaxParams.url.replace(new RegExp(ajaxParams.token), node.id);
                            } else {
                                ajaxParams.data[self.options.data.ajaxDataParam] = node.id;
                            }

                            $.ajax(ajaxParams)
                                .done(function(data) {
                                    self.cache = self.cache.concat(data);
                                    callback(data);
                                })
                                .fail(function() {
                                    callback();
                                })
                        }
                    },
                    force_text: true,
                    multiple: false,
                    check_callback: function(operation, node, node_parent) {
                        // Ensure the node I'm dragging onto is one I have write access to
                        if (operation === 'move_node') {
                            var permission = node_parent.original.li_attr['data-permission'];
                            if (!(permission == 'write' || permission == 'full')) {
                                return false;
                            }

                            // also check that you're not trying to drag onto a file
                            if (node_parent.original.type == 'file') {
                                return false;
                            }
                        }
                        return true;
                    },
                    themes: {
                        variant: "resourcetree",
                        dots: true,
                        icons: true,
                        responsive: false
                    }
                },
                plugins : ['dnd', 'sort', 'types', 'wholerow'],
                dnd: {
                    touch: false, // no dragging on touch screen thanks
                    copy: false,
                    is_draggable: function(nodes) {
                        var isDraggable = true;
                        $.each(nodes, function(i, node) {
                            var permission = node.li_attr['data-permission'];
                            if (!(permission == 'write' || permission == 'full')) {
                                isDraggable = false;
                                return false;
                            }
                        });
                        return isDraggable;
                    }
                },
                types: {
                    root: {
                        icon: 'icon-folder'
                    },
                    'default': {
                        icon: 'icon-unknown'
                    },
                    file: {
                        icon: 'icon-document'
                    },
                    folder: {
                        icon: 'icon-folder'
                    },
                    homepage: {
                        icon: 'icon-home'
                    },
                    course: {
                        icon: 'icon-course-builder'
                    }
                },
                sort: function(a, b) {
                    return resourceTreeSort(this, a, b);
                }
            }
        }

        self.element = element;
        self.options = $.extend(true, {}, defaults, options);
        self.cache   = self.options.data.cache;

        element.jstree(self.options.jstree);
    }

    $.fn.resourceTree = function(options) {
        this.each(function() {
            var element = $(this);
            element.data('resourceTree', new ResourceTree(element, options));
        })
        return this;
    };
})(jQuery);