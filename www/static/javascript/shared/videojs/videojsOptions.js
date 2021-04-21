/*
 * The videojs options to pass through various recordings, 
 * kept here to ensure consistency
 * refer to https://collab-project.github.io/videojs-record/#/options and https://docs.videojs.com/ for options
 *
 */

import WaveSurfer from 'wavesurfer.js';
import MicrophonePlugin from 'wavesurfer.js/dist/plugin/wavesurfer.microphone.js';
WaveSurfer.microphone = MicrophonePlugin;
import videojs_wavesurfer_css from 'videojs-wavesurfer/dist/css/videojs.wavesurfer.css';
import Wavesurfer from 'videojs-wavesurfer/dist/videojs.wavesurfer.js';

/*
* set up a camera recording
* 
* @returns { Object }
*/ 
export const getRecordCameraOptions = () => {
    return {
        controls: true,
        autoplay: false,
        fluid: true,
        aspectRatio: '16:9',
        loop: false,
        bigPlayButton: false,
        controlBar: {
            volumePanel: false,
        },
        plugins: {
            record: {
                audio: true,
                video: {
                    width: { min: 640, ideal: 640, max: 1280 }, // video media constraints: set resolution of camera
                    height: { min: 360, ideal: 360, max: 720 }
                },
                screen: true,
                maxLength: 300, //five minutes
                convertEngine: 'ts-ebml', //to be able to have seekable webm files
                debug: true,
            }
        }
    }
}

/*
* set up a screen recording
* 
* @returns { Object }
*/ 
export const getRecordScreenOptions = () => {
    return {
        controls: true,
        autoplay: false,
        fluid: true,
        aspectRatio: '16:9',
        loop: false,
        bigPlayButton: false,
        controlBar: {
            volumePanel: false,
        },
        plugins: {
            record: {
                audio: true,
                screen: true,
                maxLength: 300, //five minutes
                convertEngine: 'ts-ebml', //to be able to have seekable webm files
                debug: true,
            }
        }
    }
}

/*
* Once a video has been recorded, need to pass to another videojs component to be able to use the progress controls
* 
* @returns { Object }
*/ 
export const getPlaybackOptions = (recordedData) => {
    return {
        controls: true,
        autoplay: false,
        fluid: true,
        aspectRatio: '16:9',
        loop: false,
        bigPlayButton: true,
        controlBar: {
            pictureInPictureToggle: false
        },
        sources: [
            {
                src: URL.createObjectURL(recordedData),
                type: recordedData.type
            }
        ]
    }
}

/*
* The options for an audio only videojs-record
* 
* @returns { Object }
*/ 
export const getAudioOnlyOptions = () => {
    return {
        controls: true,
        autoplay: false,
        fluid: true,
        aspectRatio: '16:9',
        loop: false,
        bigPlayButton: false,
        controlBar: {
            volumePanel: true,
        },
        plugins: {
            wavesurfer: { // this plugin is only needed when recording audio-only
                backend: 'WebAudio',
                waveColor: '#fff',
                progressColor: '#ccc',
                debug: true,
                cursorWidth: 1,
                displayMilliseconds: true,
                hideScrollbar: true,
                plugins: [
                    WaveSurfer.microphone.create({ // enable microphone plugin
                        bufferSize: 4096,
                        numberOfInputChannels: 1,
                        numberOfOutputChannels: 1,
                        constraints: {
                            video: false,
                            audio: true
                        }
                    })
                ]
            },
            record: {
                audio: true,
                video: false,
                maxLength: 300, //5 minutes
                audioEngine: 'vmsg',
                audioWebAssemblyURL: '/static/javascript/vendor/vmsg/vmsg.wasm',
                // audioWebAssemblyURL: 'https://unpkg.com/vmsg/vmsg.wasm', //currently issues with loading .wasm files...
                debug: true,
            }
        }
    }
}