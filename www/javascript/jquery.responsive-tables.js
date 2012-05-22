(function($) {

    "use strict";

    $.fn.extend({

        //pass the options variable to the function
        tinyTables: function(options) {
            var defaults = {
                shrinkAt: 1400,
                tableClass: 'tiny-tables',
                tableWrapperClass: 'tiny-tables-wrap' ,
                scrollingTableClass: 'tiny-tables-scroll',
                pinnedTableClass: 'tiny-tables-pinned'
            };

            options =  $.extend(defaults, options);

            return this.each(function() {
                var obj = $(this);

                obj.addClass(options.tableClass);

                updateTables(obj);

                $(window).bind("resize", function(){
                    updateTables(obj);
                });
            });

            function updateTables(original) {
                if (($(window).width() < options.shrinkAt)) {
                    splitTable(original);
                } else if (($(window).width() > options.shrinkAt)) {
                    unsplitTable(original);
                }
            }

            function splitTable(sourceTable) {
                if (!sourceTable.parent().hasClass(options.scrollingTableClass)) {
                    sourceTable.wrap('<div class="' + options.tableWrapperClass + '" />');
                    var pinnedTable = sourceTable.clone();
                    pinnedTable.find("td:not(:first-child), th:not(:first-child)").hide();
                    sourceTable.closest('.' + options.tableWrapperClass).append(pinnedTable);
                    pinnedTable.wrap('<div class="' + options.pinnedTableClass + '" />');
                    sourceTable.wrap('<div class="' + options.scrollingTableClass + '" />');
                }
            }

            function unsplitTable(sourceTable) {
                if (sourceTable.parent().hasClass(options.scrollingTableClass)) {
                    $('.' + options.pinnedTableClass).remove();
                    sourceTable.unwrap();
                    sourceTable.unwrap();
                }
            }
        }
    });
})(jQuery);
