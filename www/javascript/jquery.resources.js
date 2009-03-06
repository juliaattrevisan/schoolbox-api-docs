/* jQuery treeTable Plugin 2.2 - http://ludo.cubicphuse.nl/jquery-plugins/treeTable/ */
(function($) {
	// Helps to make options available to all functions
	// TODO: This gives problems when there are both expandable and non-expandable
	// trees on a page. The options shouldn't be global to all these instances!
	var options;

	$.fn.treeTable = function(opts) {
		options = $.extend({}, $.fn.treeTable.defaults, opts);
		var topSpan = $("span#top"), topRow = topSpan.parents("tr")[0];
		addDrop(topSpan);
		options.addContext(topSpan);
		addSelect(topRow);
		addOpen(topRow);
		jQuery(document.createElement("div")).attr("id", "simpleModal").appendTo(document.body);
		return this.each(function() {
			$(this).addClass("treeTable").find("tbody tr").each(function() {
				// Initialize root nodes only whenever possible
				if(!options.expandable || $(this)[0].className.search("child-of-") == -1) {
					initialize($(this));
				}
			});
		});
	};
  
	$.fn.treeTable.defaults = {
		childPrefix: "child-of-",
		expandable: true,
		indent: 22,
		
		initialState: "collapsed",
		treeColumn: 0,
		ajaxURL: "/resourcesForm.php",
		resourcesID: "resourcesID",
		ajaxLinkURL: "/cms/linkForm.php",
		ajaxFileURL: "/cms/fileForm.php",
		ajaxCategoryURL: "/cms/categoryForm.php"
	};
  
	// Recursively hide all node's children in a tree
	$.fn.collapse = function() {
		$(this).addClass("collapsed");
		
		childrenOf($(this)).each(function() {
			
			if(!$(this).hasClass("collapsed") && $(this).hasClass('parent')) {
				$(this).collapse();
			}
			
			$(this).hide();
		});
		
		return this;
	};
  
	// Recursively show all node's children in a tree
	$.fn.expand = function() {
		$(this).removeClass("collapsed").addClass("expanded");
		
		if (!$(this).hasClass("populated")) {
			$(this).loadFolders();
			$(this).addClass("populated");
		}
		
		childrenOf($(this)).each(function() {
			initialize($(this));
			
			if($(this).is(".expanded.parent")) {
				$(this).expand();
			}
			
			$(this).show();
		});
		
		return this;
	};
  
	$.fn.select = function() {
		$(".selected").removeClass("selected"); // Deselect currently selected rows
		$(this).addClass("selected");
	};

	// Add an entire branch to +destination+
	$.fn.appendBranchTo = function(destination) {
		var node = $(this);
		var parent = parentOf(node);
		
		var ancestorNames = $.map(ancestorsOf($(destination)), function(a) { return a.id; });
		
		// Conditions:
		// 1: +node+ should not be inserted in a location in a branch if this would
		//    result in +node+ being an ancestor of itself.
		// 2: +node+ should not have a parent OR the destination should not be the
		//    same as +node+'s current parent (this last condition prevents +node+
		//    from being moved to the same location where it already is).
		// 3: +node+ should not be inserted as a child of +node+ itself.
		if($.inArray(node[0].id, ancestorNames) == -1 && (!parent || (destination.id != parent[0].id)) && destination.id != node[0].id) {
			indent(node, ancestorsOf(node).length * options.indent * -1); // Remove indentation
			
			if(parent) { node.removeClass(options.childPrefix + parent[0].id); }
			
			node.addClass(options.childPrefix + destination.id);
			move(node, destination); // Recursively move nodes to new location
			indent(node, ancestorsOf(node).length * options.indent);
		
		
		}
		
		return this;
	};
  
	// Add reverse() function from JS Arrays
	$.fn.reverse = function() {
		return this.pushStack(this.get().reverse(), arguments);
	};

	// Toggle an entire branch
	$.fn.toggleBranch = function() {
		if($(this).hasClass("collapsed")) {
			$(this).expand();
		} else {
			$(this).removeClass("expanded").collapse();
		}
		
		return this;
	};

	$.fn.move = function(categoryID) {
		// matches file-, link-, node-
		var id = $($(this).parents("tr")[0]).attr("id").substring(5), ajaxURL;
		
		if ($(this).hasClass("link"))
			ajaxURL = options.ajaxLinkURL; 
		else if ($(this).hasClass("file"))
			ajaxURL = options.ajaxFileURL;
		else
			ajaxURL = options.ajaxCategoryURL;  
		
		$.ajax({
			type: "POST",
			url: ajaxURL,
			data: {"move": id, "categoryID": categoryID}
		}); 
		$("node-"+categoryID).removeClass("populated");
	};

	$.fn.delay = function(time,func){
		return this.each(function(){
			$(this).data("timeout", setTimeout(func,time));
		});
	};
 
	$.fn.clearDelay = function(id){
		return this.each(function(){
			clearTimeout($(this).data("timeout"));
		});
	};

	$.fn.saveID = function() {
		var id = $(this).attr("id").substring(5);
		$.ajax({
			url: options.ajaxURL,
			type: "POST",
			data: {saveID: id}
		});
	};

	$.fn.loadFolders = function() {
		var _node = $(this), parentID = $(this).attr("id").substring(5)
		// request folders
		$.ajax({
			url: options.ajaxURL, 
			type: "POST",
			data: {"folders": parentID}, 
			dataType: "json",
			beforeSend: function () { _node.find(".folder").addClass("loading"); },
			success: function (data) {
				data = data.reverse();
				var childIndent = (ancestorsOf($("#node-"+parentID)).length + 1) * options.indent;
				$.each(data, function (i, item) {
					// table row
					myRow = jQuery(document.createElement("tr"))
					.attr("id", "node-"+(item.id))
					.addClass("child-of-node-"+parentID)
					.insertAfter("#node-"+parentID);
					
					if (item.children > 0)
						myRow.addClass("parent");

					if (item.write == 'true')
						myRow.addClass('write');
					
					if (item.full == 'true')
						myRow.addClass('full');

					// folder span
					mySpan = jQuery(document.createElement("span"))
					.addClass("folder")
					.append(item.name)
					.attr({title: item.name})
					.appendTo(
					jQuery(document.createElement("td")).appendTo("#node-"+(item.id))
					);
					
					indent(myRow, childIndent); 
				});
				
				childrenOf(_node).each(function() {
					initialize($(this));
				});					
				
				_node.find(".folder").removeClass("loading");
			},
			error: function () {  _node.find(".folder").addClass("error"); }
		});
	};

	$.fn.loadFiles = function() {
		var _node = $(this), parentID = $(this).attr("id").substring(5);
		// hide all files
		$("#file-list tbody tr").each(function () {
			$(this).hide();
		});

		
		if (_node.hasClass("opened")) {

			// display selected files
			$("#file-list tr.category-"+parentID).each(function () {
				$(this).show();
			});   
			// remember this view
			$(this).saveID();	
		} else {
			_node.addClass("opened");
			// request files and build dom
			$.ajax({
				url: options.ajaxURL, 
				type: "POST",
				data: {"files": parentID}, 
				dataType: "json",
				beforeSend: function () { _node.find(".folder").addClass("loading"); },
				success: function (data) {
					$.each(data, function (i, item) {
						// table row
						if (item.link == 'true')
							newID = "link-"+(item.id);
						else
							newID = "file-"+(item.id);
						
						myRow = jQuery(document.createElement("tr"))
						.attr("id", newID)
						.addClass("category-"+parentID)
						.appendTo("#file-list tbody");
						
						// name column
						mySpan = jQuery(document.createElement("span"))
						.addClass("file")
						.addClass(item.icon)
						.append(item.name)
						.attr({title: item.name})
						.appendTo(
							jQuery(document.createElement("td")).appendTo(myRow)
						);
						
						if (item.link == 'true')
							mySpan.addClass('link');
						
						if (item.hidden == 'true')
							mySpan.addClass('hidden');
						
						if (item.write == 'true')
							mySpan.addClass('write');
						
						if (item.full == 'true')
							mySpan.addClass('full');
						
						// owner column
						jQuery(document.createElement("td"))
						.append(item.owner)
						.appendTo(myRow);
						
						// date column
						jQuery(document.createElement("td"))
						.append(item.modified)
						.appendTo(myRow);
						
						initialize(myRow); 
					});
					_node.find(".folder").removeClass("loading");
				},
				error: function (XMLHttpRequest, textStatus, errorThrown) { 
					
					if (textStatus == "parsererror")
						// most likely have lost session
						window.open(this.href,'_self','');
					else
						_node.find(".folder").removeClass("loading").addClass("error"); }
			});
		}

	};
  
  // === Private functions
  
	function ancestorsOf(node) {
		var ancestors = [];
		while(node = parentOf(node)) {
			ancestors[ancestors.length] = node[0];
		}
		return ancestors;
	};
  
	function childrenOf(node) {
		return $("table.treeTable tbody tr." + options.childPrefix + node[0].id);
	};

	function indent(node, value) {
		var cell = $(node.children("td")[options.treeColumn]);
		var padding = parseInt(cell.css("padding-left"), 10) + value;
		
		cell.css("padding-left", + padding + "px");
		
		childrenOf(node).each(function() {
			indent($(this), value);
		});
	};

	function initialize(node) {
		
		if(!node.hasClass("initialized")) {
			node.addClass("initialized");
			
			var childNodes = childrenOf(node);
			
			if(!node.hasClass("parent") && childNodes.length > 0) {
				node.addClass("parent");
			}
			
			var span = node.find(".folder, .file");
			
			// add extras
			if (span.hasClass(".folder")) {
				options.addContext(span);
				addDrag(span);
				addDrop(span);
				addSelect(node);
				addOpen(node);
			}
			else if (span.hasClass(".file")) {
				// store category id
				node.data("category", getCategory(node));
				options.addContext(span);
				addDrag(span);
				addSelect(node);
			}
			
			if(node.hasClass("parent")) {
				
				var cell = $(node.children("td")[options.treeColumn]);
				var padding = parseInt(cell.css("padding-left"), 10) + options.indent;
				
				childNodes.each(function() {
					$($(this).children("td")[options.treeColumn]).css("padding-left", padding + "px");
				});
				
				if(options.expandable) {
					cell.prepend('<span style="margin-left: -' + 19 + 'px; padding-left: ' + 19 + 'px" class="expander"></span>');
					$(cell[0].firstChild).click(function() { node.toggleBranch(); });
					
					node.dblclick(function() { node.toggleBranch(); });
					node.children("span").dblclick(function() { node.trigger("dblclick"); });	 		
					
					
					// Check for a class set explicitly by the user, otherwise set the default class
					if(!(node.hasClass("expanded") || node.hasClass("collapsed"))) {
						node.addClass(options.initialState);
					}
					
					if(node.hasClass("collapsed")) {
						node.collapse();
					} else if (node.hasClass("expanded")) {
						node.expand();
					}
				}
			}
		}
	};
  
	function move(node, destination) {
		node.insertAfter(destination);
		childrenOf(node).reverse().each(function() { move($(this), node[0]); });
	};
  
	function parentOf(node) {
		var classNames = node[0].className.split(' ');
		for(key in classNames) {
			if(classNames[key].match("child-of-")) {
				return $("#" + classNames[key].substring(9));
			}
		}
	};
  
	function getCategory(node) {
		var classNames = $(node).attr("class").split(' ');
		for(key in classNames) {
			if(classNames[key].match("category-")) {
				return classNames[key].substring(9);
			}
		}	  
	};


	function addDrag (node) {
		$(node).draggable({
			helper: "clone",
			opacity: .75,
			revert: "invalid",
			revertDuration: 300,
			scroll: true	  
		});
	};
  
	function addDrop (node) {
		$(node).each(function() { 
			$($(this).parents("tr")[0]).droppable({
				accept: ".file, .folder",
				hoverClass: "accept",
				over: function(e, ui) {
					if(this.id != ui.draggable.parents("tr")[0].id && !$(this).is(".expanded")) {
						var _row = $(this);
						$(this).delay(1000, function() {				
							if (_row.hasClass("accept"))
								_row.expand();					 
						}); 
					}
				},
				out: function() {
					$(this).clearDelay();
				},
				drop: function(e, ui) { 
					var targetID = $(this).attr("id").substring(5);
					var node = $($(ui.draggable).parents("tr")[0]);
					
					if ($(ui.draggable).hasClass("file")) {
						/* files */
						// ajax move request
						$(ui.draggable).move(targetID);
						
						if ($(this).hasClass("opened") && targetID != node.data("category")) {
							// move node if target is already loaded
							node.removeClass("category-"+node.data("category"));
							node.addClass("category-"+targetID);
							node.hide();
							node.removeClass("selected");
							node.data("category", targetID);
						} else if (targetID != node.data("category"))
							// destroy node, target not loaded
							node.delay(100, function () { node.remove(); });
					
					} else {
						/* folders */
						
						// move node and expand target
						node.appendBranchTo(this);
						$(this).expand();
					
						// ajax move request
						if (node.hasClass("child-of-node-"+targetID) && node.attr("id").substring(5) != targetID)
							$(ui.draggable).move(targetID);
						
					}
				}
			});
		});	  
	};

	function addSelect (node) {
		$(node).each(function () {
			$(this).click(function() {
				$(this).select();
			});
			$(this).children("span").click(function() {
				$($(this).parents("tr")[0]).trigger("click");
			});	  
			$(this).bind('contextmenu', function () {
				$(this).select();
			});
			$(this).children("span").bind('contextmenu', function () {
				$($(this).parents("tr")[0]).select();
			});			
			
		});
	};
 
	function addOpen (node) {
		$(node).each(function () {
			$(this).click(function() {
				$(this).loadFiles();
			});
			$(this).children("span").click(function() {
				$($(this).parents("tr")[0]).trigger("click");
			});	 		
		});
	};
  
})(jQuery);