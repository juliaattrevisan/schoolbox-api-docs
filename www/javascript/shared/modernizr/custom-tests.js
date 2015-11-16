/**
 * Modernizr custom tests.
 */
(function(m) {
    "use strict";
    // Detection for whether rem units for line-height of :before or :after content work.
    // They work in most browsers except IE 11 and below for Win7.
    // @see https://connect.microsoft.com/IE/feedback/details/776744
    m.testStyles('#modernizr{font:0/0 a}#modernizr:after{content:":)";visibility:hidden;font:7px/1 a; line-height: 5rem}', function(node) {
        m.addTest('pseudoelementlineheightinrems', node.offsetHeight >= 10);
    });
})(Modernizr);
