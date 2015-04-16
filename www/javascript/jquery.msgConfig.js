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
        toggleSelector: 'td[data-toggle]',
        muteSelector:   'td[data-mutable]',
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

        dictSimple:   'Switch to Simple',
        dictAdvanced: 'Switch to Advanced',

        // in these callbacks, `this` refers to the relevant `opts.sourceSelector`
        persist: noop,
        describe: noop,

        onShowAdvanced: noop,
        onShowSimple: noop,

        level: {
            off: 0,
            instant: 1,
            push: 2,
            email: 4,
            digest: 8
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

        // when a role is turned on/off
        if (self.options.toggleSelector) {
            $(this.element).on(change, this.sel('toggle', 'input'), function (e) {
                var val = parseInt($(this).val()),
                    src = $(this).closest(self.sel('source')),
                    chk = $(this).is(':checked'),
                    tds = $(this).closest(self.sel('role')).find([
                        self.sel('perm'), self.sel('mute'), self.sel('custom')
                    ].join(', '));

                if (chk) {
                    if (val) {
                        tds.removeClass(self.options.disabledClass);

                    } else {
                        tds.addClass(self.options.disabledClass);
                    }
                }

                // execute description callback
                self.options.describe.call(src);

                // do not persist if this is a forced change
                if (e.namespace != 'force') {
                    self.options.persist.call(src);
                }
            });
        }

        if (self.options.muteSelector) {
            $(this.element).on(change, this.sel('mute', 'input'), function (e) {
                var chk = $(this).is(':checked'),
                    non = $(this).closest(self.options.roleSelector)
                        .children(self.options.permSelector).eq(0)
                        .find('input');

                if (chk) {
                    non.removeAttr('disabled');
                } else {
                    non.prop('checked', false)
                        .attr('disabled', 'disabled')
                        .trigger('change.force');
                }

                // do not persist if this is a forced change
                if (e.namespace != 'force') {
                    self.options.persist.call($(this).closest(self.sel('source')));
                }
            });
        }

        if (self.options.customSelector) {
            $(this.element).on('click', this.sel('custom', 'a'), function (e) {
                var link = $(this);

                e.preventDefault();
                e.stopPropagation();

                if (link.hasClass(self.options.simpleClass)) {
                    link.removeClass(self.options.simpleClass)
                        .addClass(self.options.advancedClass)
                        .attr('title', self.options.dictSimple);

                    self.options.onShowAdvanced.call(link);

                } else if (link.hasClass(self.options.advancedClass)) {
                    link.removeClass(self.options.advancedClass)
                        .addClass(self.options.simpleClass)
                        .attr('title', self.options.dictAdvanced);

                    self.options.onShowSimple.call(link);
                }

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
                off   = '[data-perm=' + lvl.off + ']',
                inst  = '[data-perm=' + lvl.instant + ']',
                fallback;


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

                    if (role.find(self.sel('mute', 'input:checked')).length == 0) {
                        fallback = role.find(inst);
                    } else {
                        fallback = role.find(off);
                    }

                    fallback.prop('checked', true);
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
        $(this.element).find([ this.sel('mute', 'input'), this.sel('perm', 'input') ].join(', ')).trigger('change.force');

    }

    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new MsgConfig(this, options));
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