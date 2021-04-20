/*
 * The videojs options to pass through various recordings, 
 * kept here to ensure consistency
 * refer to https://collab-project.github.io/videojs-record/#/options and https://docs.videojs.com/ for options
 *
 */

import WaveSurfer from 'wavesurfer.js';
import MicrophonePlugin from 'wavesurfer.js/dist/plugin/wavesurfer.microphone.js';
WaveSurfer.microphone = MicrophonePlugin;
import 'videojs-wavesurfer/dist/css/videojs.wavesurfer.css';
import Wavesurfer from 'videojs-wavesurfer/dist/videojs.wavesurfer.js';

/*
* the VideoJS/VideoJS-record params to set up
* 
* @returns { Object }
*/ 
export const getVideoJsRecordOptions = () => {
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
export const getVideoJsPlaybackOptions = (recordedData) => {
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
export const getVideoJsAudioOptions = () => {
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