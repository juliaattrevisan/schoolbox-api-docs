/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
;
(function ($, window, document, undefined) {

    var pluginName = 'msgconfig',
        noop       = function () {};

    var defaults = {
        sourceSelector: 'li[data-source]',
        tableSelector:  'table[data-settings]',
        roleSelector:   'tr',
        customSelector: 'td[data-custom]',
        permSelector:   '[data-perm]',

        expand: {
            selector: 'a.exp',
            duration: 200,
            down: {
                btnClass: 'darr',
                arrow: "&#9660;"
            },
            up: {
                btnClass: 'uarr',
                arrow: "&#9650;"
            },
            target: function () { return this.closest('form').siblings('form').eq(0); }
        },

        disabledClass:     'disable',
        simpleClass:       'modify',
        advancedClass:     'cross',
        showAdvancedClass: 'show-advanced',
        expandedClass:     'expanded',

        // in these callbacks, `this` refers to the relevant `opts.sourceSelector`
        persist: noop,
        describe: noop,
        advanced: noop,

        level: {
            off:     0,
            instant: 1,
            push:    2,
            email:   4,
            digest:  8
        },

        description: {}

    };

    // urgh, this is regrettably verbose
    defaults.description[defaults.level.off]     = 'off';
    defaults.description[defaults.level.digest]  = 'digest';
    defaults.description[defaults.level.instant] = 'instant';
    defaults.description[defaults.level.mobile]  = 'mobile';
    defaults.description[defaults.level.email]   = 'email';

    /**
     * @param {$}      element
     * @param {object} options
     * @constructor
     */
    function MsgConfig(element, options) {
        var change = 'change change.force',
            prev   = [],
            self   = this;

        this.element = element;
        this.options = $.extend(true, {}, defaults, options);

        this.tables  = $(this.element).find(this.sel('table'));
        this.sources = $(this.element).find(this.sel('source'));

        if (self.options.customSelector) {
            $(this.element).on('click', this.sel('custom', 'a'), function (e) {
                var link = $(this);

                e.preventDefault();
                e.stopPropagation();

                self.options.advanced.call(link);
            });
        }

        if (self.options.expand) {
            $(this.element).on('click', self.options.expand.selector, function (e) {
                var btn  = $(this),
                    form = self.options.expand.target.call(btn),
                    up   = self.options.expand.up,
                    down = self.options.expand.down;

                e.preventDefault();
                e.stopPropagation();

                if (btn.hasClass(down.btnClass)) {
                    // change to uarr, expand content
                    btn.removeClass(down.btnClass).addClass(up.btnClass).html(down.arrow);
                    form.show(self.options.expand.duration);

                } else {
                    // change to darr, contract content
                    btn.removeClass(up.btnClass).addClass(down.btnClass).html(up.arrow);
                    form.hide(self.options.expand.duration);
                }

            });
        }

        $(this.element).on(change, this.sel('perm', 'input'), function (e) {

            var input = $(this),
                lvl   = self.options.level,
                opt   = input.data('perm'),
                chk   = input.is(':checked'),
                role  = input.closest(self.sel('role')),
                src   = input.closest(self.sel('source')),
                inst  = '[data-perm=' + lvl.instant + ']';


            // TODO find out why this is double-tapping and remove this
            if (prev[0] == e.timeStamp && prev[1] == this.id) {
                return;
            } else {
                prev = [e.timeStamp, this.id];
            }

            if (chk) {
                // if i have been checked

                // select requisite options
                if (opt > lvl.off && opt < lvl.digest) {
                    role.find(inst).prop('checked', true);
                } else if (opt < lvl.instant || opt == lvl.digest) {
                    self.deselectInvalid(role);
                }

            } else {
                // if i have been unchecked

                // deselect subordinate options
                if (opt == lvl.instant) {
                    self.deselectInvalid(role);
                }

                // ensure *something* is set
                if (role.find(self.sel('perm', 'input:checked')).length == 0) {
                    role.find(inst).prop('checked', true);
                }
            }

            // execute description callback
            self.options.describe.call(src);

            // do not persist if this is a forced change
            if (e.namespace != 'force') {
                self.options.persist.call(src);
            }
        });

        // FORCE A CHANGE (now that we've got our listeners set up)
        $(this.element).find(this.sel('perm', 'input')).trigger('change.force');

    }

    $.fn[pluginName] = function (options) {

        var args = arguments;

        return this.each(function () {

            // parse object into construction (if no instance yet exists)
            if (typeof options == 'object') {
                if (!$.data(this, 'plugin_' + pluginName)) {
                    $.data(this, 'plugin_' + pluginName, new MsgConfig(this, options));
                }

            } else if (typeof options == 'string') {
                if (!$.data(this, 'plugin_' + pluginName)) {
                    throw "Cannot call functions, " + pluginName + " not instantiated";
                }

                if (options == 'describe') {
                    if (args.length < 2) {
                        throw "Cannot describe without a target element";
                    }

                    $.data(this, 'plugin_' + pluginName).options.describe.call(args[1]);
                }
            }
        });
    };


    //noinspection FunctionNamingConventionJS
    /**
     * @param {string} name
     * @param {string} subselect
     * @returns {string}
     */
    MsgConfig.prototype.sel = function (name, subselect) {

        var sel = name+'Selector',
            result;

        subselect = subselect || '';

        if (typeof this.options[sel] == 'undefined') {
            result = '';

        } else if ($.isFunction(this.options[sel])) {
            result = options[sel].call(this.element);

        } else {
            result = this.options[sel];
        }

        if (subselect) {
            if (subselect[0] == ':') {
                return result + subselect;

            } else {
                return result + ' ' + subselect;
            }
        }
        return result;
    };

    MsgConfig.prototype.deselectInvalid = function(role) {
        var self = this;

        role.find(this.sel('perm', 'input')).filter(
            function () {
                var perm = $(this).data('perm');
                return perm > self.options.level.instant && perm < self.options.level.digest;
            }
        ).prop('checked', false);
    };

})(jQuery, window, document);