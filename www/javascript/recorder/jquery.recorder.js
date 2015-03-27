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
            swfSrc: '/javascript/recorder/flash/recorder.swf',
            flashContainer: null,
            onFlashSecurity: $.noop
        },
        webrtc: {
            preview: '',
            channels: 1,
            workerPath: '/javascript/recorder/webrtc/recorderWorker.js',
            audioElement: null
        },
        upload: {
            url: ''
        }
    };

    var Recorder = function(element, options) {
        this.element   = element;
        this.options   = $.extend(true, {}, defaultOptions, options);
        this.flash     = {};
        this.webrtc    = {};
        this.recording = false;
        this.playing   = false;
        this._attemptWebRtcSetup();
        this._attemptFlashSetup();
        if (this.isWebRtcSupported() || this.isFlashSupported()) {
            this.triggerEvent('setup', []);
            this.triggerEvent('recordingReady', []);
            this.bindEvent('recordingStart', function(event, recorder) {
                recorder.recording = true;
            });
            this.bindEvent('recordingStop', function(event, recorder) {
                recorder.recording = false;
            });
            this.bindEvent('playbackStart', function(event, recorder) {
                recorder.playing = true;
            });
            this.bindEvent('playbackStop', function(event, recorder) {
                recorder.playing = false;
            });
        } else {
            this.triggerEvent('setupFailed', []);
        }
    };

    /**
     * Try to set up WebRTC recording.
     *
     * @private
     *
     * @returns {Recorder}
     */
    Recorder.prototype._attemptWebRtcSetup = function() {
        // If audio context doesn't exist, there's no way we can use WebRTC
        var self = this;
        var audioContextConstructor = window.AudioContext || window.webkitAudioContext;
        if (audioContextConstructor) {
            self.webrtc.supported    = true;
            self.webrtc.audioContext = new audioContextConstructor();
            self.webrtc.audioElement = self.options.webrtc.audioElement || $('<audio />');
            self.webrtc.audioElement.on('ended', function() {
                self.triggerEvent('playbackStop', []);
            });
        } else {
            self.webrtc.supported = false;
        }
        return this;
    };

    /**
     * Try to set up Flash recording.
     *
     * @private
     *
     * @returns {Recorder}
     */
    Recorder.prototype._attemptFlashSetup = function() {
        // Is Flash even supported? I expect SWFObject to be present to determine that.
        this.flash.supported =
            swfobject
            && swfobject.hasFlashPlayerVersion
            && swfobject.hasFlashPlayerVersion('10.1')
        ;
        if (this.isFlashSupported()) {
            this.flash.recorder = FlashRecorder;
            this.flash.recorder.initialize({
                swfSrc: this.options.flash.swfSrc,
                flashContainer: this.options.flash.flashContainer,
                onFlashSecurity: this.options.flash.onFlashSecurity
            });
        }
        return this;
    };

    /**
     * Start recording in WebRTC mode
     *
     * @private
     *
     * @returns {Recorder}
     */
    Recorder.prototype._startRecordingWebRtc = function(stream) {
        var self             = this;
        self.mode            = 'webrtc';
        self.webrtc.stream   = stream;
        self.webrtc.recorder = new WebRtcRecorder(
            self.webrtc.audioContext.createMediaStreamSource(self.webrtc.stream),
            {
                workerPath: self.options.webrtc.workerPath,
                numChannels: self.options.webrtc.channels
            }
        );
        self.webrtc.recorder.record();
        self.triggerEvent('recordingStart', []);
        return this;
    };

    /**
     * Start recording in Flash mode
     *
     * @private
     *
     * @returns {Recorder}
     */
    Recorder.prototype._startRecordingFlash = function() {
        var self            = this;
        self.mode           = 'flash';
        // start() never seems to trigger
        self.triggerEvent('recordingStart', []);
        self.flash.recorder.record();
        return this;
    };

    /**
     * Starts recording, in Flash or WebRTC mode depending on which setup
     * succeeded or failed.
     *
     * @returns {Recorder}
     */
    Recorder.prototype.startRecording = function() {
        // Starting recording stops any current recording and playing.
        if (this.isRecording()) {
            this.stopRecording();
        }
        if (this.isPlaying()) {
            this.stopPlaying();
        }
        var self = this;
        if (self.isWebRtcSupported()) {
            getUserMedia(
                self.options.userMedia,
                function(stream) {
                    self._startRecordingWebRtc(stream);
                },
                function(error) {
                    // Try to fall back to Flash.
                    if (self.isFlashSupported()) {
                        self._startRecordingFlash();
                    } else {
                        self.triggerEvent('recordingStartFailed', [error]);
                    }
                }
            );
        } else {
            self._startRecordingFlash();
        }
        return this;
    };

    /**
     * Plays back a recorded sound.
     *
     * @returns {Recorder}
     */
    Recorder.prototype.startPlaying = function() {
        // Starting playing stops any current recording and playing.
        if (this.isRecording()) {
            this.stopRecording();
        }
        if (this.isPlaying()) {
            this.stopPlaying();
        }
        if (this.isWebRtcUsed()) {
            this.webrtc.audioElement[0].currentTime = 0;
            this.webrtc.audioElement[0].play();
            this.triggerEvent('playbackStart', []);
        } else if (this.isFlashUsed()) {
            this.flash.recorder.play();
            this.triggerEvent('playbackStart', []);
        }
    };

    /**
     * Stops playing back whatever was recorded.
     *
     * @returns {Recorder}
     */
    Recorder.prototype.stopPlaying = function() {
        this.triggerEvent('playbackStop', []);
        if (this.isWebRtcUsed()) {
            this.webrtc.audioElement[0].pause();
        } else if (this.isFlashUsed()) {
            this.flash.recorder.stop();
        }
    };

    /**
     * Stops recording.
     *
     * @returns {Recorder}
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
                    self.triggerEvent('playbackReady', []);
                    self.triggerEvent('uploadReady', []);
                }
            );
            self.triggerEvent('recordingStop', []);
        } else if (self.isFlashUsed()) {
            self.flash.recorder.stop();
            self.triggerEvent('recordingStop', []);
            self.triggerEvent('playbackReady', []);
            self.triggerEvent('uploadReady', []);
        }
        return this;
    };

    /**
     * @returns {boolean}
     */
    Recorder.prototype.isRecording = function() {
        return this.recording;
    };

    /**
     * @returns {boolean}
     */
    Recorder.prototype.isPlaying = function() {
        return this.playing;
    };

    /**
     * Gets the MIME type of the uploaded file.
     *
     * Only works for WebRTC uploads.
     *
     * @returns {string}
     */
    Recorder.prototype.getUploadedFileMimeType = function() {
        return this.webrtc.blob.type;
    };

    /**
     * Gets the size of the uploaded file.
     *
     * Only works for WebRTC uploads.
     *
     * @returns {string}
     */
    Recorder.prototype.getUploadedFileSize = function() {
        return this.webrtc.blob.size;
    };

    /**
     * Upload whatever has been recorded.
     *
     * @param filename A filename to upload with.
     *
     * @returns {Recorder}
     */
    Recorder.prototype.uploadRecorded = function(filename) {
        var self = this;

        // Is this WebRTC? Post via AJAX if so
        if (self.isWebRtcUsed()) {
            var formData = new FormData();
            formData.append('upload', self.webrtc.blob, filename);
            $.ajax(
                {
                    url: self.options.upload.url,
                    type: 'post',
                    data: formData,
                    contentType: false,
                    processData: false
                }
            )
            .done(function(response, textStatus, jqXHR) {
                self.triggerEvent('uploadComplete', [filename, response, textStatus, jqXHR]);
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                self.triggerEvent('uploadFailed', [jqXHR, textStatus, errorThrown]);
            });
        } else if (self.isFlashUsed()) {
            var params = {
                filename: filename
            };
            params[self.options.flash.session.name] = self.options.flash.session.id;
            self.flash.recorder.upload({
                url: self.options.upload.url,
                method: 'post',
                audioParam: 'upload',
                params: params,
                success: function(response) {
                    self.triggerEvent('uploadComplete', [filename, response]);
                },
                error: function() {
                    // Don't know if there's any params that get passed here
                    self.triggerEvent('uploadFailed', []);
                }
            })
        }
        return this;
    };

    /**
     * Binds an event callback to an event name.
     *
     * @param eventName
     * @param eventCallback
     *
     * @returns {Recorder}
     */
    Recorder.prototype.bindEvent = function(eventName, eventCallback) {
        this.element.bind(PLUGIN_NAME+'.'+eventName, eventCallback);
        return this;
    };

    /**
     * Triggers an event on the element that this is a recorder for.
     *
     * This object is always passed as the first extra parameter to the event
     * listener.
     *
     * @param eventName
     * @param extraParams
     *
     * @returns {Recorder}
     */
    Recorder.prototype.triggerEvent = function(eventName, extraParams) {
        var params = [this];
        if (extraParams && extraParams.length) {
            $.each(extraParams, function(i, v) {
                params.push(v);
            });
        }
        this.element.trigger(PLUGIN_NAME+'.'+eventName, params);
        return this;
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
        this.each(function(i, element) {
            var $element = $(element);
            $element.data(PLUGIN_NAME, new Recorder($element, options));
        });
        return this;
    };
})(jQuery);
