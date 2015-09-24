/**
 * Curriculum Importer
 */
(function() {
    "use strict";

    /**
     * Curriculum import object
     *
     * @param element
     * @param options
     * @constructor
     */
    var CurriculumImport = function(element, options)
    {
        this.configure(element, options);
        //this.initTree();
        //this.initDialogs();
        //this.initResize();
        //this.initEvents();
    };

    /**
     * Does configuration of curriculum import object.
     *
     * @param element
     * @param options
     */
    CurriculumImport.prototype.configure = function(element, options)
    {
        var defaults = {};
        this.config  = $.extend({}, defaults, options);
        this.element = element;
    };

    /**
     * Curriculum Import
     *
     * @param options If a string: call this method (with arguments) on the import already on this element
     *                If an object: setup the import
     */
    $.fn.curriculumImport = function(options) {
        if (typeof options === 'string') {
            var method = options;
            var object = this.data('curriculumImport');
            if (object instanceof CurriculumImport) {
                if (typeof object[method] === 'function') {
                    object[method].call(object, $.makeArray(arguments).slice(1));
                }
            }
        } else {
            this.data('curriculumImport', new CurriculumImport(this, options || {}));
            return this;
        }
    }
})(jQuery);