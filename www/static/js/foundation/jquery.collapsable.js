/**
 * Make homepage components collapsable
 */
(function() {

    /**
     * Compute dimensions dynamically
     *
     * @param options
     */
    $.fn.collapsable = function(options) {
        options = options || {};

        this.config = init(options, $(this));

        setupCollapsable.call(this);

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
                container: '[data-component]'
            };

            return $.extend(defaults, options);
        }

        /**
         * Set up collapsable toggle
         */
        function setupCollapsable() {
            var self = this;
            $(self.config.element).on('click.collapsable', function(event) {
                event.preventDefault();

                var component,
                    target = $(this).data('collapser'),
                    simple = target === true || target.length === undefined,
                    icon   = '';

                if (simple) {
                    // if the string "true" is used, or no value, target the container
                    component = $(this).closest(self.config.container).find('[data-collapsable="true"]');
                    icon      = $(this).closest('[data-collapser="true"]');
                } else {
                    // otherwise, treat the value as the data-collapsable target value
                    component = $('[data-collapsable=' + target + ']');
                    icon      = $(this);
                }

                $.fn.executeCollapsable.call(self, component, icon);
            });
        }
    };

    /**
     * Destroy a previously set up collapsable.
     */
    $.fn.destroyCollapsable = function (options) {
        // Mimic the element from the collapsable's init
        options = options || {};
        let config = $.extend({element: $(this)}, options);
        $(config.element).off('click.collapsable');
    }

    /**
     * Directly execute collapsable on demand
     *
     * component is the element which expands/collapses and contains
     * the data vars "data-collapsable" and "data-collapse-state"
     *
     * icon is the element which activates the expanding/collapsing
     * and contains the data var "data-collapser"
     *
     * @param component
     * @param icon
     */
    $.fn.executeCollapsable = function(component, icon) {

        /* When it's collapsed by default, it just renders with .hide
         * It's actually uncollapsed, just hidden.
         *
         * This slides it up real fast so it can slide back down gracefully
         */
        if (component.attr('data-collapse-state') == 'collapsed') {
            component.stop().removeClass('hide').slideUp(1).slideDown({
                start: function() {
                    component.trigger('expandStart.collapsable');
                },
                done:  function() {
                    component.trigger('expandStop.collapsable');
                }
            });
            component.attr('data-collapse-state', 'uncollapsed');
        }
        else {
            component.attr('data-collapse-state', 'collapsed');
            component.stop().slideUp({
                start: function() {
                    component.trigger('collapseStart.collapsable');
                },
                done:  function() {
                    component.trigger('collapseStop.collapsable');
                }
            });
        }

        $.fn.executeIconToggle.call(this, icon);
    };


    /**
     * Explicitly and abruptly shut an accordion and reset its state and arrow
     * Ignore all the ux animations and stuff; just slam it shut
     *
     * @param component
     */
    $.fn.executeCollapsableSnapShut = function(component) {
        component.stop().slideUp(1).attr('data-collapse-state', 'collapsed');

        let icon = $('[data-collapser="'+component.attr('data-collapsable')+'"]');

        icon.removeClass('icon-up-arrow').addClass('icon-down-arrow');
    }


    /**
     * Toggle expand/collapse icon state
     *
     * @param icon
     */
    $.fn.executeIconToggle = function(icon) {
        if (icon.hasClass('icon-up-arrow')) {
            icon.removeClass('icon-up-arrow').addClass('icon-down-arrow');
        } else {
            icon.removeClass('icon-down-arrow').addClass('icon-up-arrow');
        }
    }
}(jQuery));
