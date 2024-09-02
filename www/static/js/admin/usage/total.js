(function ($) {
    /**
     * Constructor
     *
     * @param options
     */
    $.fn.usageTotal = function (options) {
        var config = init(options, this);

        graph();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element,

                /* Graph title
                 */
                title: "",

                /* The ajax url
                 */
                url: "",
            };

            var derived = {};

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Graph it
         */
        function graph() {
            $.ajax({
                url: config.url,
                method: "GET",
                dataType: "json",
                success: function (datasets) {
                    /* Progress bars
                     */
                    var used = datasets[0].data;
                    var total = datasets[1].data + used;
                    var percentage = Math.round((100 * used) / total);

                    var allTooltips = [$.fn.formatBytes(used) + " / " + $.fn.formatBytes(total)];
                    var allCategories = [""];
                    var allSeriesData = [percentage];

                    $(config.element).serverUsage({
                        title: "Total Usage",
                        height: 150,
                        categories: allCategories,
                        tooltip: {
                            enabled: true,
                            formatter: function () {
                                return allTooltips[this.point.x];
                            },
                        },
                        series: [
                            {
                                data: allSeriesData,
                            },
                        ],
                        formatter: function () {
                            var str = "";

                            if (this.y > 0) {
                                str = this.y + "%";
                            }

                            return str;
                        },
                    });
                },
            });
        }
    };

    /**
     * Constructor
     *
     * @param options
     */
    $.fn.usageTotalPlain = function (options) {
        var config = init(this, options);

        plot();

        /**
         * Init
         *
         * @param element
         * @param options
         * @returns {*}
         */
        function init(element, options) {
            var defaults = {
                url: "",
                title: "",
            };

            var derived = {
                element: element,
            };

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Plot graph
         */
        function plot() {
            $.ajax({
                url: config.url,
                method: "GET",
                dataType: "json",
                success: function (datasets) {
                    var html =
                        '\
						<div>\
							<h3>{TITLE}</h3>\
							<p class="amount">\
								{USED}\
							</p>\
						</div>\
					';

                    var html = html.replace("{TITLE}", config.title);
                    var html = html.replace("{USED}", $.fn.formatBytes(datasets.data));

                    config.element.html(html);
                },
            });
        }
    };
})(jQuery);
