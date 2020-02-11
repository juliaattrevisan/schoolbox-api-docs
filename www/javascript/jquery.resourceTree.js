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
            case 'home':
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

        // locales and options aren't supported by garbage browsers like IE < 11 and Safari,
        // they will just ignore these parameters.
        return jstree.get_text(a).localeCompare(
            jstree.get_text(b),
            window.navigator.language || 'en',
            {
                usage: 'sort',
                sensitivity: 'accent',
                numeric: true
            }
        );
    };

    /**
     * @param element The jQuery element to tie this resource tree to.
     * @param nodeTypeIconMap Maps nodeTypes to CSS class
     * @param options
     *   data:
     *     cache:            A set of preloaded nodes (see jstree.data)
     *     ajaxParams:       Parameters passed to jQuery.ajax to load child nodes
     *     ajaxParams.token: Replace this string in ajaxParams.url to load a particular child node
     *   jstree: Any and all jstree parameters
     * @constructor
     */
    var ResourceTree = function(element, nodeTypeIconMap, options) {
        var self     = this;
        var defaults = {
            data: {
                cache: [],
                ajaxParams: {
                    url:      '/resources/{folderId}/nodes',
                    type:     'GET',
                    data:     {},
                    dataType: 'json',
                    token:    '{folderId}',
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

                            // Cannot move a file to root
                            if("file" == node.type && 0 == node_parent.id){
                                return false;
                            }

                            // also check that you're not trying to drag onto a file
                            if (node_parent.original.type == 'file' || node_parent.original.type == 'link'  ) {
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
                plugins : ['dnd', 'sort', 'types', 'wholerow', 'conditionalselect'],
                dnd: {
                    touch: false, // no dragging on touch screen thanks
                    copy: false,
                    is_draggable: function(nodes) {
                        var isDraggable = true,
                            jstree      = this;
                        $.each(nodes, function(i, node) {
                            // Check both the file and its parent
                            var permission       = node.li_attr['data-permission'];
                            var parent           = jstree.get_node(node.parent);

                            if (parent['id'] != '#') {
                                var parentPermission = parent.li_attr['data-permission'];
                                if (!(permission == 'write' || permission == 'full')
                                    || !(parentPermission == 'write' || parentPermission == 'full')
                                ) {
                                    isDraggable = false;
                                    return false;
                                }
                            } else {
                                isDraggable = false;
                                return false;
                            }
                        });
                        return isDraggable;
                    }
                },
                conditionalselect: function (node, e) {
                    return typeof node.li_attr['data-id'] != 'undefined' && node.li_attr['data-type'] != 'noaccess';
                },
                types: {
                    root: {
                        icon: nodeTypeIconMap.root
                    },
                    'default': {
                        icon: nodeTypeIconMap.default
                    },
                    file: {
                        icon: nodeTypeIconMap.file
                    },
                    link: {
                        icon: nodeTypeIconMap.link
                    },
                    folder: {
                        icon: nodeTypeIconMap.folder
                    },
                    home: {
                        icon: nodeTypeIconMap.home
                    },
                    homepage: {
                        icon: nodeTypeIconMap.homepage
                    },
                    course: {
                        icon: nodeTypeIconMap.course
                    },
                    unit: {
                        icon: nodeTypeIconMap.unit
                    },
                    noaccess: {
                        icon: nodeTypeIconMap.noaccess
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

    $.fn.resourceTree = function(nodeTypeIconMap, options) {
        this.each(function() {
            var element = $(this);
            element.data('resourceTree', new ResourceTree(element, nodeTypeIconMap, options));
        })
        return this;
    };
})(jQuery);
