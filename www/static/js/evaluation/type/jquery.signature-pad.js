/**
 * Attempt to pluginify the signature pad for our purposes.
 */
(function ($) {
    $.fn.alaressSignaturePad = function (options) {
        var defaults = {
            init: [],
            rescale: false,
            lineTop: 102,
            lineWidth: 0,
            lineMargin: 16,
            drawOnly: true,
            displayOnly: true,
            sig: 'canvas[data-role="signatureCanvas"]',
            clear: 'a[data-role="signatureClear"]',
            output: 'input[data-role="signatureInput"]',
        };

        var settings = $.extend(true, {}, defaults, options);
        var initial = settings.init;
        delete settings.init;

        var canvas = this.find(settings.sig || "canvas");
        var context = canvas.get(0).getContext("2d");

        // init pad
        var sigPad = this.signaturePad(settings);

        // Apply current signature
        var redrawSignature = function (signature) {
            if (settings.rescale) {
                var xScale = 1;
                if (signature) {
                    var maxPoint = signature.reduce(
                        function (carry, current) {
                            return {
                                x: Math.max(carry.x, current.lx, current.mx),
                                y: Math.max(carry.y, current.ly, current.my),
                            };
                        },
                        { x: 0, y: 0 },
                    );
                    xScale = Math.min(1, canvas.attr("width") / maxPoint.x);
                }
                context.scale(xScale, xScale);
            }
            sigPad.regenerate(signature);
        };
        if (initial.length) {
            redrawSignature(initial);
        }

        // Resize event
        // Assume that any canvas in the signature is a sig
        var container = canvas.parent();
        var resizeSignature = function () {
            var signature = sigPad.getSignature();
            var width = container.width();
            canvas.attr({ width: width });
            redrawSignature(signature);
        };
        $(window).resize(resizeSignature);
        resizeSignature();

        return this;
    };
})(jQuery);
