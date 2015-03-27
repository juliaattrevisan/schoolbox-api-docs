(function($){
    var PLUGIN_NAME = 'recorder';

    var defaultOptions = {
        userMedia: {
            audio: true,
            video: false
        },
        flash: {
            session: {
                name: '',
                id: ''
            },
            swfSrc: '/javascript/recorder/flash/recorder.swf'
        },
        webrtc: {
            preview: '',
            channels: 1,
            workerPath: '/javascript/recorder/webrtc/recorderWorker.js'
        },
        upload: {
            url: ''
        }
    };

    var Recorder = function(element, options) {
        this.element = element;
        this.options = $.extend(true, {}, defaultOptions, options);
        this.flash   = {};
        this.webrtc  = {};
        this.attemptWebRtcSetup();
        this.attemptFlashSetup();
        if (!(this.isWebRtcSupported() || this.isFlashSupported())) {
            throw new Error('Neither WebRTC nor Flash is supported');
        }

        // Set up the bound events.
        // onReady(this)

        // onRecordingReady(this)
        // onRecordingStart(this)
        // onRecordingStartFailed(this)
        // onRecordingStop(this)

        // onPlaybackReady(this)
        // onPlaybackStart(this)
        // onPlaybackStartFailed(this)
        // onPlaybackStop(this)

        // onUploadReady(this)
        // onUploadComplete(this, responseData)
        // onUploadFailed(this, responseData)
    };

    /**
     * Try to set up WebRTC recording.
     *
     * @returns {boolean}
     */
    Recorder.prototype.attemptWebRtcSetup = function() {
        // If audio context doesn't exist, there's no way we can use WebRTC
        var audioContextConstructor = window.AudioContext || window.webkitAudioContext;
        if (audioContextConstructor) {
            this.webrtc.supported    = true;
            this.webrtc.audioContext = new audioContextConstructor();
            this.webrtc.audioElement = $('<audio />');
        } else {
            this.webrtc.supported = false;
        }
        return this.isWebRtcSupported();
    };

    /**
     * Try to set up Flash recording.
     *
     * @returns {boolean}
     */
    Recorder.prototype.attemptFlashSetup = function() {
        // Is Flash even supported? I expect SWFObject to be present to determine that.
        this.flash.supported =
            swfobject
            && swfobject.hasFlashPlayerVersion
            && swfobject.hasFlashPlayerVersion('10.1')
        ;
        return this.isFlashSupported();
    };

    /**
     * Starts recording.
     */
    Recorder.prototype.startRecording = function() {
        var self = this;
        getUserMedia(
            self.options.userMedia,
            function(s) {
                // use RecorderJS
                self.mode            = 'webrtc';
                self.webrtc.stream   = s;
                self.webrtc.recorder = new WebRtcRecorder(
                    self.webrtc.audioContext.createMediaStreamSource(self.webrtc.stream),
                    {
                        workerPath: self.options.webrtc.workerPath,
                        numChannels: self.options.webrtc.channels
                    }
                );
                self.webrtc.recorder.record();
                // Trigger a start recording method
            },
            function (error) {
                // Try to fall back to Flash.
                if (self.isFlashSupported()) {
                    self.mode = 'flash';
                    self.flash.recorder = FlashRecorder;
                    self.flash.recorder.initialize({
                        swfSrc: self.options.flash.swfSrc
                    });
                    self.flash.recorder.record({
                        start: function() {
                            // Trigger a start recording method
                        }
                    });
                } else {
                    // Trigger an error event, which may just be a file upload box.
                }
            }
        );
    };

    /**
     * Plays back a recorded sound.
     */
    Recorder.prototype.playRecorded = function() {
        this.stopRecording();
        this.stopPlaying();
        if (this.isWebRtcUsed()) {
            this.webrtc.audioElement.play();
        } else if (this.isFlashUsed()) {
            this.flash.recorder.play();
        }
    };

    /**
     * Stops playing back whatever was recorded.
     */
    Recorder.prototype.stopPlaying = function() {
        if (this.isWebRtcUsed()) {
            this.webrtc.audioElement.stop();
        } else if (this.isFlashUsed()) {
            self.flash.recorder.stop();
        }
    };

    /**
     * Stops recording.
     */
    Recorder.prototype.stopRecording = function() {
        var self = this;
        if (self.isWebRtcUsed()) {
            self.webrtc.stream.stop();
            self.webrtc.recorder.stop();
            // At this point I have a blob. Put it in the audio element.
            self.webrtc.recorder.exportWAV(
                function(blob) {
                    // Overrides any previous blob
                    self.webrtc.blob = blob;
                    self.webrtc.audioElement
                        .attr('src', window.URL.createObjectURL(blob))
                    ;
                    // Trigger an event saying I'm ready for play and upload.
                }
            );
        } else if (self.isFlashUsed()) {
            self.flash.recorder.stop();
            // Trigger an event saying I'm ready for upload
        }
    };

    /**
     * Upload whatever has been recorded.
     *
     * @param filename A filename to upload with.
     */
    Recorder.prototype.uploadRecorded = function(filename) {
        var self = this;

        // Is this WebRTC? Post via AJAX if so
        if (self.isWebRtcUsed()) {
            var formData = new FormData();
            formData.append('upload', self.webrtc.blob, filename);
            $.ajax(
                {
                    url: self.upload.url,
                    type: 'post',
                    data: formData,
                    contentType: false,
                    processData: false
                }
            )
            .done(function(response) {
                // inform any listeners that a file was uploaded.
                // put it in the dropzone, and inform the dropzone opener
                // that a file was posted to it.
                var data = {
                    title:    filename,
                    mimeType: self.webrtc.blob.type,
                    filesize: self.webrtc.blob.size,
                    hash:     response.hash
                };
                // Replace with a uploadDone event
                window.top.postMessage(JSON.stringify({ data: [data] }), location.origin);
            })
            .fail(function() {
                // trigger a failed message
            });
        } else if (self.isFlashUsed()) {
            var params = {};
            params[self.flash.session.name] = self.flash.session.id;
            FlashRecorder.upload({
                url: self.upload.url,
                method: 'post',
                audioParam: 'upload',
                params: params,
                success: function(responseText) {
                    // inform listeners. Assume that response text is JSON for now
                },
                error: function() {
                    // trigger a failed message
                }
            })
        }
    };

    /**
     * Is the WebRTC recorder being used?
     *
     * @returns {boolean}
     */
    Recorder.prototype.isWebRtcUsed = function() {
        return this.mode == 'webrtc';
    };

    /**
     * Is the Flash recorder being used?
     *
     * @returns {boolean}
     */
    Recorder.prototype.isFlashUsed = function() {
        return this.mode == 'flash';
    };

    /**
     * Is the WebRTC recorder supported?
     *
     * @returns {boolean}
     */
    Recorder.prototype.isWebRtcSupported = function() {
        return this.webrtc.supported;
    };

    /**
     * Is the Flash recorder supported?
     *
     * @returns {boolean}
     */
    Recorder.prototype.isFlashSupported = function() {
        return this.flash.supported;
    };

    $.fn[PLUGIN_NAME] = function(options) {
        this.each(function(element) {
            var $element = $(element);
            $element.data(PLUGIN_NAME, new Recorder($element, options));
        });
        return this;
    };
})(jQuery);
