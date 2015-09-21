/**
 * Audio recorder plugin, with both Flash and WebRTC versions.
 *
 * This plugin provides no interface, but triggers events that an interface can
 * attach to.
 *
 * All events have the following signature: (event, recorder)
 * * event: the jQuery event that was raised
 * * recorder: the recorder object
 * Some events may have extra parameters.
 *
 * The plugin triggers the following events:
 *
 * Setup:
 * * setup: when the plugin is set up
 * * setupFailed: setup failed because neither Flash nor WebRTC are supported
 *
 * Recording:
 * * recordingReady: when recording is ready
 * * recordingStart: when recording starts
 * * recordingStartFailed: recording failed for some reason
 * * recordingProgress(seconds): raised as recording occurs, with extra parameter
 *   for the number of seconds recording has gone on for
 * * recordingStop: when recording stops
 *
 * Playback
 * * playbackReady: when playback is ready (a recording has been made)
 * * playbackStart: when playback starts
 * * playbackStartFailed: playback failed for some reason. not yet implemented.
 * * playbackProgress(seconds): raised as playback occurs, with extra parameter
 *   for the number of seconds into the recording playback has gone
 * * playbackStop: when playback stops
 *
 * Uploads
 * * uploadReady: when upload is ready (a recording has been made)
 * * uploadStart: when upload starts
 * * uploadComplete(filename, response): when upload is complete. Receives a
 *   filename, as well as an object response.
 * * uploadFailed: upload failed for some reason
 */
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
        media: {
            audioElement: null
        },
        webrtc: {
            preview: '',
            channels: 1,
            sampleRate: 11025,
            workerPath: '/javascript/recorder/webrtc/recorderWorker.js',
            audioElement: null
        },
        upload: {
            url: ''
        }
    };

    var RecorderPlugin = function(element, options) {
        this.element   = element;
        this.options   = $.extend(true, {}, defaultOptions, options);
        this.media     = {};
        this.flash     = {};
        this.webrtc    = {};
        this.recording = false;
        this.playing   = false;
        this.recordingStartTime = null;
        this.intervalMethod     = null;
        this._attemptMediaRecorderSetup();
        this._attemptWebRtcSetup();
        this._attemptFlashSetup();
        if (this.isMediaRecorderSupported() || this.isWebRtcSupported() || this.isFlashSupported()) {
            this.triggerEvent('setup', []);
            this.triggerEvent('recordingReady', []);
            this._bindInternalEvents();
        } else {
            this.triggerEvent('setupFailed', []);
        }
    };

    /**
     * Binds internal recording/playback start/stop events.
     *
     * @private
     *
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype._bindInternalEvents = function() {
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
        return this;
    };

    /**
     * Try to set up MediaRecorder recording.
     *
     * @private
     *
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype._attemptMediaRecorderSetup = function() {
        // If audio context doesn't exist, there's no way we can use WebRTC
        var self = this;
        if ('MediaRecorder' in window) {
            self.media.supported    = true;
            self.media.audioElement = self.options.media.audioElement || $('<audio />');
            self.media.audioElement.on('timeupdate.recorder', function() {
                self.triggerEvent('playbackProgress', [this.currentTime]);
            });
            self.media.audioElement.on('playing.recorder', function() {
                self.triggerEvent('playbackStart', []);
            });
            self.media.audioElement.on('ended.recorder', function() {
                self.triggerEvent('playbackStop', []);
            });
        } else {
            self.webrtc.supported = false;
        }
        return this;
    };

    /**
     * Try to set up WebRTC recording.
     *
     * @private
     *
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype._attemptWebRtcSetup = function() {
        // If audio context doesn't exist, there's no way we can use WebRTC
        var self = this;
        var audioContextConstructor = window.AudioContext || window.webkitAudioContext;
        if (audioContextConstructor) {
            self.webrtc.supported    = true;
            self.webrtc.audioContext = new audioContextConstructor();
            self.webrtc.audioElement = self.options.webrtc.audioElement || $('<audio />');
            self.webrtc.audioElement.on('timeupdate.recorder', function() {
                self.triggerEvent('playbackProgress', [this.currentTime]);
            });
            self.webrtc.audioElement.on('playing.recorder', function() {
                self.triggerEvent('playbackStart', []);
            });
            self.webrtc.audioElement.on('ended.recorder', function() {
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
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype._attemptFlashSetup = function() {
        // Is Flash even supported? I expect SWFObject to be present to determine that.
        this.flash.supported =
            swfobject
            && swfobject.hasFlashPlayerVersion
            && swfobject.hasFlashPlayerVersion('10.1')
        ;
        if (this.isFlashSupported()) {
            this.flash.recorder = Recorder;
            this.flash.recorder.initialize({
                swfSrc: this.options.flash.swfSrc,
                flashContainer: this.options.flash.flashContainer,
                onFlashSecurity: this.options.flash.onFlashSecurity
            });
        }
        return this;
    };

    /**
     * Method for raising playback progress events.
     *
     * @private
     */
    RecorderPlugin.prototype._pollRecordingTime = function() {
        this.triggerEvent(
            'recordingProgress',
            [(Date.now() - this.recordingStartTime) / 1000]
        );
    };

    /**
     * Start recording in MediaRecorder mode
     *
     * @private
     *
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype._startRecordingMediaRecorder = function(stream) {
        var self            = this;
        self.mode           = 'media';
        self.media.stream   = stream;
        self.media.recorder = new MediaRecorder(stream);

        // There's no mediarecorder event for recording progress,
        // so fake it with setInterval.
        self.recordingStartTime = Date.now();
        self.intervalMethod     = setInterval(self._pollRecordingTime.bind(self), 1000);

        self.media.recorder.start();
        self.triggerEvent('recordingStart', []);
        return this;
    };

    /**
     * Start recording in WebRTC mode
     *
     * @private
     *
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype._startRecordingWebRtc = function(stream) {
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

        // There's no webrtc event for recording progress,
        // so fake it with setInterval.
        self.recordingStartTime = Date.now();
        self.intervalMethod     = setInterval(self._pollRecordingTime.bind(self), 1000);

        self.webrtc.recorder.record();
        self.triggerEvent('recordingStart', []);
        return this;
    };

    /**
     * Start recording in Flash mode
     *
     * @private
     *
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype._startRecordingFlash = function() {
        var self  = this;
        self.mode = 'flash';
        self.flash.recorder.record({
            start: function() {
                self.triggerEvent('recordingStart', []);
            },
            progress: function(ms) {
                self.triggerEvent('recordingProgress', [ms/1000]);
            }
        });
        return this;
    };

    /**
     * Starts recording, in Flash or WebRTC mode depending on which setup
     * succeeded or failed.
     *
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype.startRecording = function() {
        // Starting recording stops any current recording and playing.
        var self = this;
        if (self.isRecording()) {
            self.stopRecording();
        }
        if (self.isPlaying()) {
            self.stopPlaying();
        }
        if (self.isMediaRecorderSupported()) {
            getUserMedia(
                self.options.userMedia,
                function(stream) {
                    self._startRecordingMediaRecorder(stream);
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
        } else if (self.isWebRtcSupported()) {
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
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype.startPlaying = function() {
        // Starting playing stops any current recording and playing.
        var self = this;
        if (self.isRecording()) {
            self.stopRecording();
        }
        if (self.isPlaying()) {
            self.stopPlaying();
        }
        if (self.isMediaRecorderUsed()) {
            self.media.audioElement[0].currentTime = 0;
            self.media.audioElement[0].play();
        } else if (self.isWebRtcUsed()) {
            self.webrtc.audioElement[0].currentTime = 0;
            self.webrtc.audioElement[0].play();
        } else if (self.isFlashUsed()) {
            self.flash.recorder.play({
                progress: function(ms) {
                    self.triggerEvent('playbackProgress', [ms/1000]);
                },
                finish: function(ms) {
                    self.triggerEvent('playbackStop', []);
                }
            });
            self.triggerEvent('playbackStart', []);
        }
    };

    /**
     * Stops playing back whatever was recorded.
     *
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype.stopPlaying = function() {
        this.triggerEvent('playbackStop', []);
        if (this.isMediaRecorderUsed()) {
            this.media.audioElement[0].pause();
        } else if (this.isWebRtcUsed()) {
            this.webrtc.audioElement[0].pause();
        } else if (this.isFlashUsed()) {
            this.flash.recorder.stop();
        }
    };

    /**
     * Stops recording.
     *
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype.stopRecording = function() {
        var self = this;
        if (self.isMediaRecorderUsed()) {
            clearInterval(self.intervalMethod);
            self.media.stream.stop();
            self.media.recorder.stop();
            // At this point I have a blob. Put it in the audio element.
            self.media.recorder.ondataavailable =
                function(e) {
                    // Overrides any previous blob
                    self.media.blob = e.data;
                    self.media.audioElement
                        .attr('src', window.URL.createObjectURL(e.data))
                    ;
                    // Trigger an event saying I'm ready for play and upload.
                    self.triggerEvent('playbackReady', []);
                    self.triggerEvent('uploadReady', []);
                }
            ;
            self.triggerEvent('recordingStop', []);
        } else if (self.isWebRtcUsed()) {
            clearInterval(self.intervalMethod);
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
    RecorderPlugin.prototype.isRecording = function() {
        return this.recording;
    };

    /**
     * @returns {boolean}
     */
    RecorderPlugin.prototype.isPlaying = function() {
        return this.playing;
    };

    /**
     * Upload whatever has been recorded.
     *
     * @param filename A filename to upload with.
     *
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype.uploadRecorded = function(filename) {
        var self = this;

        // Blob handling for WebRTC and MediaElement
        var uploadBlob = function(blob) {
            self.triggerEvent('uploadStart', []);
            var formData = new FormData();
            formData.append('upload', blob, filename);
            $.ajax(
                {
                    url: self.options.upload.url,
                    type: 'post',
                    data: formData,
                    contentType: false,
                    processData: false
                }
            )
                .done(function(response) {
                    self.triggerEvent('uploadComplete', [filename, response]);
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    self.triggerEvent('uploadFailed', [jqXHR, textStatus, errorThrown]);
                });
        };

        // Is this WebRTC? Post via AJAX if so
        if (self.isMediaRecorderUsed()) {
            uploadBlob(self.media.blob);
        } else if (self.isWebRtcUsed()) {
            uploadBlob(self.webrtc.blob);
        } else if (self.isFlashUsed()) {
            var params = {
                filename: filename
            };
            params[self.options.flash.session.name] = self.options.flash.session.id;
            self.triggerEvent('uploadStart', []);
            self.flash.recorder.upload({
                url: self.options.upload.url,
                method: 'post',
                audioParam: 'upload',
                params: params,
                success: function(response) {
                    self.triggerEvent('uploadComplete', [filename, JSON.parse(response)]);
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
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype.bindEvent = function(eventName, eventCallback) {
        this.element.on(eventName+'.'+PLUGIN_NAME, eventCallback);
        return this;
    };

    /**
     * Unbinds all events added outside of this plugin.
     *
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype.unbindUserEvents = function() {
        this.element.off('.'+PLUGIN_NAME);
        this._bindInternalEvents();
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
     * @returns {RecorderPlugin}
     */
    RecorderPlugin.prototype.triggerEvent = function(eventName, extraParams) {
        var params = [this];
        if (extraParams && extraParams.length) {
            $.each(extraParams, function(i, v) {
                params.push(v);
            });
        }
        var namespacedEventName = eventName+'.'+PLUGIN_NAME;
        console.log('Triggering event "'+namespacedEventName+"'");
        this.element.trigger(namespacedEventName, params);
        return this;
    };

    /**
     * Is the MediaRecorder recorder being used?
     *
     * @returns {boolean}
     */
    RecorderPlugin.prototype.isMediaRecorderUsed = function() {
        return this.mode == 'media';
    };

    /**
     * Is the WebRTC recorder being used?
     *
     * @returns {boolean}
     */
    RecorderPlugin.prototype.isWebRtcUsed = function() {
        return this.mode == 'webrtc';
    };

    /**
     * Is the Flash recorder being used?
     *
     * @returns {boolean}
     */
    RecorderPlugin.prototype.isFlashUsed = function() {
        return this.mode == 'flash';
    };

    /**
     * Is the MediaRecorder recorder supported?
     *
     * @returns {boolean}
     */
    RecorderPlugin.prototype.isMediaRecorderSupported = function() {
        return this.media.supported;
    };

    /**
     * Is the WebRTC recorder supported?
     *
     * @returns {boolean}
     */
    RecorderPlugin.prototype.isWebRtcSupported = function() {
        return this.webrtc.supported;
    };

    /**
     * Is the Flash recorder supported?
     *
     * @returns {boolean}
     */
    RecorderPlugin.prototype.isFlashSupported = function() {
        return this.flash.supported;
    };

    /**
     * Gets the file extension to use for an audio recording
     *
     * @returns {string}
     */
    RecorderPlugin.prototype.getFileExtension = function() {
        // Media or WebRTC: get from blob
        // Flash: assume it's wav
        var type = '';
        if (this.media.blob && this.media.blob.type) {
            type = this.media.blob.type;
        } else if (this.webrtc.blob && this.webrtc.blob.type) {
            type = this.webrtc.blob.type;
        }
        switch (type) {
            case 'audio/ogg':
                return 'ogg';
            case 'audio/mpeg':
                return 'mp3';
            case 'audio/wav':
            default:
                return 'wav';
        }
    };

    $.fn[PLUGIN_NAME] = function(options) {
        this.each(function(i, element) {
            var $element = $(element);
            $element.data(PLUGIN_NAME, new RecorderPlugin($element, options));
        });
        return this;
    };
})(jQuery);
