(function($) {

	$.fn.reverse = function()
	{
		return this.pushStack(this.get().reverse(), arguments);
	};
	
	$.fn.tree = function(opts) {

		if (this.length > 1){
			this.each(function() { $(this).tree(opts) });
			return this;
		}

		/* options */
		var defaults = {
			url			 : '/ajax/tree.php',
			fileAjax	 : '/cms/fileForm.php',
			categoryAjax : '/cms/categoryForm.php',
			relationID	 : 'relationID',
			idPrefix	 : 'node-',
			childPrefix	 : 'child-of-',
			dragDrop	 : true,
			stripeRows   : true,
			sequencing 	 : true,
			
			/* selectors */
			type		 : 'ul',
			node		 : 'li',
			template	 : '#node-template',
			label		 : 'span.label',
			onOff		 : 'on'
			
		};
		
		var options = $.extend({}, defaults, opts);
		
		var object = this;
	
		/* methods */
		this.collapseRow = function (node) {
			
			$(options.node + '.' + options.childPrefix + node.attr('id')).each(function () {
				$(this).hide();
				if ($(this).hasClass('expanded'))
					object.collapseRow($(this));
			});
		};

		this.expandRow = function (node) {
			
			if (!node.hasClass('loaded'))
				object.loadChildren(node);
			else if (node.is('.expanded:visible')) {
				$(options.node + '.' + options.childPrefix + node.attr('id')).each(function () {
					$(this).show();
					if ($(this).hasClass('expanded'))
						object.expandRow($(this));
				});
			}
		};

		this.loadChildren = function (node) {
			var query = options.relationID + '=' + node.attr('id').substring(options.idPrefix.length) + '&', clone, lastNode = node;
			
			node.addClass('loading');
			
			$.post(options.url, query, function (data) {
				if (data.error) {
					alert(data.error);
					node.removeClass('loading expanded');	
					
				} else {
					
					var template = $(options.template), lastNode = node;
					for (i in data) {
						var row = data[i];
						
						clone = template.clone();	
						clone.attr('id', options.idPrefix + row.id);
						clone.removeClass('dir-template');
						clone.addClass(options.childPrefix + node.attr('id'));
						clone.addClass(row.permission);
						clone.find(options.label).text(row.name);
						
						if (row.isParent == 1) {
							clone.addClass('collapsed');
							makeExpandable(clone.find('span.elbow'));
						}
						
						if (row.template > 0)
							clone.addClass('homepage');
						
						clone.insertAfter(lastNode).show();
						
						object.indentRow(clone);
						lastNode = clone;
					}
					
					/* mark last row */
					clone.addClass('last');
					
					if (options.dragDrop) {
						var dir = $(options.node + '.' + options.childPrefix + node.attr('id') + '.full ' + options.label + ', ' +
								    options.node + '.' + options.childPrefix + node.attr('id') + '.write ' + options.label);
						
						makeDraggable(dir);
						makeDroppable(dir);
					}
					
					if (options.sequencing)	
						makeSequencable($(options.node + '.' + options.childPrefix + node.attr('id')));
					
					
					/* mark node complete */
					node.addClass('loaded');
					
					/* reapply stripes */
					if (options.stripeRows) 
						stripeRows(node.closest(options.type));
					
					node.removeClass('loading');
					
					/* continue */
					node.closest(options.type).triggerHandler('loaded');
				}
			}, 'json');
		};

		this.getParent = function (node) {
		
			var parentID = node[0].className.match(/node-[\d]+/);
			return $('#'+parentID);
		}
		
		this.getAncestors = function (node) {
			var ancestors = [];
			while(node = object.getParent(node)) {
				if (node.length == 0)
					break;
				ancestors[ancestors.length] = node[0];
			}
			return ancestors;
		};

		this.move = function (node, destination) {
			node.insertAfter(destination);
			object.indentRow(node);
			
			$(options.node + '.' + options.childPrefix + node.attr('id')).reverse().each(function() { 
				object.move($(this), node); 
			});
		};

		// Add an entire branch to +destination+
		this.appendBranch = function(node, destination) {
			var parent = object.getParent(node), ancestorNames = $.map(object.getAncestors($(destination)), function(a) { return a.id; });
				
			// Conditions:
			// 1: +node+ should not be inserted in a location in a branch if this would
			//    result in +node+ being an ancestor of itself.
			// 2: +node+ should not have a parent OR the destination should not be the
			//    same as +node+'s current parent (this last condition prevents +node+
			//    from being moved to the same location where it already is).
			// 3: +node+ should not be inserted as a child of +node+ itself.
			if($.inArray(node[0].id, ancestorNames) == -1 && (!parent[0] || (destination[0].id != parent[0].id)) && destination[0].id != node[0].id) {
				
				var parentID = parent.length > 0 ? parent.attr('id') : node[0].className.match(/node-[\d]+/);
				
				/* prepare destination */
				var children = $(options.node + '.' + options.childPrefix + destination.attr('id'));
				if (children.length > 0) {
					
					/* expand destination */
					if ($(destination).hasClass('collapsed'))
						$(destination).find('span.elbow').triggerHandler('click');
					
				} else {
					/* new parent */
					$(destination).addClass('expanded');
					makeExpandable($(destination).find('span.elbow'));
					destination.addClass('loaded');
				}
				
				/* update node classes */
				node.addClass(options.childPrefix + destination.attr('id'));
				node.removeClass(options.childPrefix + parentID + ' last');
				
				/* cleanup old parent */
				var parentChildren = $(options.node + '.' + options.childPrefix + parentID);
				if (parentChildren.length == 0)
					parent.removeClass('expanded collapsed');
				else {
					parentChildren.removeClass('last');
					parentChildren.filter(':last').addClass('last');
				}
				
				/* recursively move nodes to new destination */
				object.move(node, destination);
				
				/* mark last child, re-indent */
				$(options.node + '.' + options.childPrefix + destination.attr('id') + ':last').addClass('last');
				object.indentRow($(options.node + '.' + options.childPrefix + destination.attr('id')));
				
				
				/* highlight alternate rows */
				if (options.stripeRows) 
					stripeRows($(node).closest('table'));
				
				/* ajax move */
				$.post(options.categoryAjax, {move: node.attr('id').substring(5), categoryID : destination.attr('id').substring(5)}, function (data) {
					if (data.error) {
						alert('An error occurred during processing. Please reload your browser and try again.\nError: ' + data.error);
					} else {
						
						/* ajax sequence */
						if (options.sequencing) {
							var sequence = '', parent_id = destination[0].className.match(/node-[\d]+/);
							$(options.node + '.' + options.childPrefix + parent_id ).each(function () {
								sequence += $(this).attr('id').substring(5) + ',';
							});
							$.post('/admin/navigation/modifyForm.php', {reorder : sequence}, function (data) {
								if (data.error)
									alert('An error occurred while re-ordering. Please reload your browser and try again.\nError: ' + data.error);
							}, 'json');
						}
						
						
					}
				}, 'json');
				
				
			}
			
			return this;
		};

		// Add an entire branch to +destination+
		this.insertBranchNextTo = function(node, destination) {
			var parent = object.getParent(node), destinationParent = object.getParent(destination);
			var ancestorNames = $.map(object.getAncestors($(destination)), function(a) { return a.id; });

			// Conditions:
			// 1: +node+ should not be inserted in a location in a branch if this would
			//    result in +node+ being an ancestor of itself.
			// 2: +node+ should not have a parent OR the destination should not be the
			//    same as +node+'s current parent (this last condition prevents +node+
			//    from being moved to the same location where it already is).
			// 3: +node+ should not be inserted as a child of +node+ itself.
			if($.inArray(node[0].id, ancestorNames) == -1 && destination[0].id != node[0].id) {
				
				var parentID = parent.length > 0 ? parent.attr('id') : node[0].className.match(/node-[\d]+/);
				var nextSibling = destination.nextAll(options.node + '.' + options.childPrefix + destinationParent.attr('id')+':first'), nextTo;
				if (nextSibling.length > 0)
					nextTo = nextSibling.prev('tr');
				else
					nextTo = destination;
				
				/* update node classes */
				node.removeClass(options.childPrefix  + parentID + ' last');
				node.addClass(options.childPrefix + destinationParent.attr('id'));
				
				/* cleanup old parent */
				var parentChildren = $(options.node + '.' + options.childPrefix + parentID);
				if (parentChildren.length == 0)
					parent.removeClass('expanded collapsed');
				else {
					parentChildren.removeClass('last');
					parentChildren.filter(':last').addClass('last');
				}
				
				/* recursively move nodes to new destination */
				if (destination.hasClass('append-top') && node[0].id != destination.prev('tr')[0].id)
					object.move(node, destination.prev('tr'));
				else if (!destination.hasClass('append-top') && node[0].id != nextTo[0].id) {
					object.move(node, nextTo);
				}
				
				/* mark last child, re-indent */
				$(options.node + '.' + options.childPrefix + destinationParent.attr('id')).removeClass('last');
				$(options.node + '.' + options.childPrefix + destinationParent.attr('id') + ':last').addClass('last');
				object.indentRow($(options.node + '.' + options.childPrefix + destinationParent.attr('id')));

				/* highlight alternate rows */
				if (options.stripeRows) 
					stripeRows($(node).closest('table'));

				/* ajax move */
				var destParentID = destination[0].className.match(/node-[\d]+/);
				if (parentID != destinationParent.attr('id')) {
					$.post('/admin/navigation/modifyForm.php', {move: node.attr('id').substring(5), parentID : destParentID.toString().substring(5)}, function (data) {
						if (data.error) {
							alert('An error occurred during processing. Please reload your browser and try again.\nError: ' + data.error);
						} else {
							
							/* ajax sequence */
							var sequence = '';
							$(options.node + '.' + options.childPrefix + destParentID ).each(function () {
								sequence += $(this).attr('id').substring(5) + ',';
							});
							$.post('/admin/navigation/modifyForm.php', {reorder : sequence}, function (data) {
								if (data.error)
									alert('An error occurred while re-ordering. Please reload your browser and try again.\nError: ' + data.error);
							}, 'json');
							
						}
					}, 'json');
				} else {
		
					/* ajax sequence */
					var sequence = '';
					$(options.node + '.' + options.childPrefix + destParentID ).each(function () {
						sequence += $(this).attr('id').substring(5) + ',';
					});
					$.post('/admin/navigation/modifyForm.php', {reorder : sequence}, function (data) {
						if (data.error)
							alert('An error occurred while re-ordering. Please reload your browser and try again.\nError: ' + data.error);
					}, 'json');
					
				}
			
			}
			
			return this;
		};


		this.indentRow = function (node) {
			
			var parent = object.getParent(node), spacer = $('<span></span>');
			
			/* clear indent */
			node.find('span:not(.elbow,' + options.label + ')').remove();
			
			if (parent.attr('id') == 'node-0')
				return;
			
			/* copy parent spacers */
			parent.find('span:not(.elbow,' + options.label + ')').each(function (i) {
				var clone = $(this).clone();
				clone.insertBefore(node.find('span.elbow'));	
			});
			
			/* indent one more than parent */
			if (parent.hasClass('last'))
				spacer.addClass('blank');
			
			spacer.insertBefore(node.find('span.elbow'));
		
		};

		/* private functions */
		
		var initialize = function (node) {
			
			var lastBranch = false;
			
			/* mark last row */
			$(node).find(options.node + '.' + options.childPrefix + options.idPrefix + '0:last').addClass('last');
			
			/* cycle rows */
			$(node).find(options.node + ':not(.server)').each(function () {
																		
				var row = $(this), parent = object.getParent(row);
				
				/* check for children */
				var children = $(options.node + '.' + options.childPrefix + row.attr('id'));
				if (children.length > 0) {
					
					/* mark end nodes */
					children.filter(':last').addClass('last');
				}
				
				/* indent */
				if (parent.attr('id') != 'node-0')
					object.indentRow(row);
				
			});
			
			
			if (options.stripeRows) 
				stripeRows(node);
			
			makeExpandable(node.find(options.node + '.expanded span.elbow, ' + options.node + '.collapsed span.elbow'));
			
			if (options.dragDrop) {
				makeDraggable(node.find('tr:not(.site,.server).full ' + options.label + ', ' + 'tr:not(.site,.server).write ' + options.label));
				makeDroppable(node.find(options.node + ':not(.server).full ' + options.label + ', ' + options.node + ':not(.server).write ' + options.label));
				makeDroppable($('#node-0 span.server:not(.no-drop)'));
			}

			if (options.sequencing)
				makeSequencable(node.find('tbody tr:not(.site,.server)'));
			
		};

		var stripeRows = function (node) {
			/* highlight alternate rows */
			$(node).find('tbody tr').removeClass(options.onOff);
			$(node).find('tbody tr:visible:odd').addClass(options.onOff);
		};
			

		var makeExpandable = function (node) {
			/* make nodes expandable/collapsible */
			node.click(function (event) {
									
				/* this is why im not using live() */
				event.stopPropagation();
				
				var row = $(this).closest(options.node);
				
				if (row.hasClass('expanded')) {
					/* close */
					row.removeClass('expanded').addClass('collapsed');
					object.collapseRow(row);
				} else {
					/* open */
					row.addClass('expanded').removeClass('collapsed');
					object.expandRow(row);	
				}
				
				if (options.stripeRows)
					stripeRows(node.closest(options.type));
					
				return false;
			});			
		};

			
		var makeDraggable = function (node) {	
			/* make nodes draggable */
			node.draggable({
				cursor: 'move',
				containment: 'div.scroller',
				cursorAt: {top: 5, left: 5},
				helper: function (event) {
					return $(event.currentTarget).clone().addClass('accept');
				},
				opacity: 1,
				revert: "invalid",
				revertDuration: 300,
				scroll: true
			});
		};

		// todo: move drag + drop functions into their own plugin
		var makeDroppable = function (node) {
			
			var accept = options.label+',span.file';
			if (node.hasClass('server'))
				accept = options.label;
				
		
			node.droppable({
				accept: accept,
				hoverClass: "accept",
				greedy: true,
				tolerance: 'pointer',
				over: function(e, ui) {
					/*
					var node = $(ui.draggable).closest("tr"), destination = $(this).closest('tr');
					/* expand row if mouse hover > 1 second 
					if(destination.hasClass('collapsed') && destination.attr('id') != node.attr('id')) {
						var timeout = setTimeout(function() {				
							if (destination.find('span.file').hasClass("accept"))
								destination.find('span.elbow').triggerHandler('click');			 
						}, 1000);
						destination.data('timeout', timeout);
					}*/
				},
				out: function() {
					/* clearTimeout($(this).closest('tr').data('timeout')); */
				},
				drop: function(e, ui) { 
					var node = $(ui.draggable).closest("tr"), destination = $(this).closest('tr');

					if ($(ui.draggable).hasClass('file')) {
						
						var link = node.hasClass('link'), id = node.attr('id').substring(5);
						/* move file */		
						
						/* remove current category class */
						node.removeClass(node[0].className.match(/category-[\d]+/).toString());
						
						if ($('tr.category-' + destination.attr('id').substring(5) + ':not(.empty)').length > 0) {
							
							/* add new category class */
							node.addClass('category-' + destination.attr('id').substring(5));
							
							/* remove empty message */
							var empty = $(options.node + '.category-' + destination.attr('id').substring(5) + '.empty').remove();
							
						} 
						
						node.hide();
						
						if (options.stripeRows) 
							stripeRows(node.closest('table'));
						
						var url = options.fileAjax;
						if (link)
							url = '/cms/linkForm.php';
							
						$.post(url, {move: id, categoryID: destination.attr('id').substring(5)}, function (data) {
							if (data.result != 'success')
								alert(data.result);
						}, 'json');
						
					} else {
						/* append branch */
						if (destination.hasClass('collapsed') && !destination.hasClass('loaded')) {
							$(this).closest('tr').one('loaded', function () {
								object.appendBranch(node, destination);										  
							});
							destination.find('span.elbow').triggerHandler('click');	
						} else
							object.appendBranch(node, destination);
					}
				}
			});
			
		};
		
		var makeSequencable = function (node) {
			
			
			node.droppable({
				accept: options.label,
				hoverClass: "accept",
				tolerance: 'pointer',
				over: function(e, ui) {

					if (e.pageY < ($(this).offset().top + 14))
						$(this).addClass('append-top');
					else
						$(this).removeClass('append-top');
				},
				drop: function(e, ui) { 
					var node = $(ui.draggable).closest("tr"), destination = $(this);
					
					/* alter sequence */
					object.insertBranchNextTo(node, destination);
					
				}
			});	  

		
		};
		

		return initialize(this);
	};


})(jQuery);