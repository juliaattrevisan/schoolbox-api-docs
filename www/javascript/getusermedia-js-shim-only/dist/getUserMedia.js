/*!
* getUserMedia
* v0.2.0 - 2014-08-16
* https://github.com/addyosmani/getUserMedia.js
* (c) Addy Osmani; MIT License
*
* Stripped by Alaress:
* * Removed inline video playback for both Flash and WebRTC
* * Removed flash callbacks
*/;(function (window, document) {
    "use strict";

    window.getUserMedia = function (options, successCallback, errorCallback) {

        // Options are required
        if (options !== undefined) {

            // getUserMedia() feature detection
            navigator.getUserMedia_ = (navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia);

            if ( !! navigator.getUserMedia_) {

                // constructing a getUserMedia config-object and
                // an string (we will try both)
                var option_object = {};
                var option_string = '';

                if (options.video === true) {
                    option_object.video = true;
                    option_string = 'video';
                }
                if (options.audio === true) {
                    option_object.audio = true;
                    if (option_string !== '') {
                        option_string = option_string + ', ';
                    }
                    option_string = option_string + 'audio';
                }

                // referenced for use in your applications
                options.context = 'webrtc';

                // first we try if getUserMedia supports the config object
                try {
                    // try object
                    navigator.getUserMedia_(option_object, successCallback, errorCallback);
                } catch (e) {
                    // option object fails
                    try {
                        // try string syntax
                        // if the config object failes, we try a config string
                        navigator.getUserMedia_(option_string, successCallback, errorCallback);
                    } catch (e2) {
                        // both failed
                        // neither object nor string works
                        return undefined;
                    }
                }
            } else {
                // error out. Try setting up Flash callback manually
                options.context = 'flash';
                errorCallback();
            }
        }
    };

}(this, document));
