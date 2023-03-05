/**
 * A place to store Javascript properties that are needed in many places within Schoolbox.
 */

window.sbProps = {
    // For use with the JQuery spectrum colour picker (http://bgrins.github.io/spectrum/).
    // Spectrum config is here to enable visibility in .vue and .twig files as well as .js files.
    //
    // Gotchas:
    //  * If something needs to happen on the 'change' event, it probably also needs to happen on the 'hide' event.
    //    The reason is that if you open a colour picker, select a new colour, and then open another colour picker,
    //    the change event on the first colour picker will not fire, but the hide event will fire.
    //  * If you use a 'change' or 'hide' event handler and allowEmpty is true, remember to handle the case of a null
    //    value.
    //  * PreferredFormat 'hex' is compatible with showAlpha:true.  In that case when the alpha slider moves from zero
    //    to non-zero, the displayed format changes from hex to rgba.
    spectrum: {
        allowEmpty: false,
        clickoutFiresChange: true,
        preferredFormat: "hex",
        showAlpha: false,
        showButtons: false,
        showInitial: true,
        showInput: true,
    },
    editors: {}, // we still need to be able to access our CKEditor instances from outside bundle scope
};
