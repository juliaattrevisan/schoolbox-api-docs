;(function ($, window, document, undefined) {
  'use strict';

  function isTouchDevice() {
      return (('ontouchstart' in window) ||
      (navigator.maxTouchPoints > 0) ||
      (navigator.msMaxTouchPoints > 0));
  }

  Foundation.libs.alert = {
    name : 'alert',

    version : '5.5.3',

    settings : {
      callback : function () {}
    },

    init : function (scope, method, options) {
      this.bindings(method, options);
    },

    events : function () {
      var self = this,
          S = this.S;

      $(this.scope).off('.alert').on('click.fndtn.alert', '[' + this.attr_name() + '] .close', function (e) {
        var alertBox = S(this).closest('[' + self.attr_name() + ']'),
            settings = alertBox.data(self.attr_name(true) + '-init') || self.settings;

        e.preventDefault();
        alertBox.addClass('alert-close');
        alertBox.on('transitionend webkitTransitionEnd oTransitionEnd', function (e) {
          S(this).trigger('close.fndtn.alert').remove();
          settings.callback();
        });

      });
    },

    reflow : function () {}
  };
}(jQuery, window, window.document));
