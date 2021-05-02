/*
 * The videojs options to pass through various recordings, 
 * kept here to ensure consistency
 * Note that videojs-record extends of videojs to add the record features
 * refer to https://collab-project.github.io/videojs-record/#/options and https://docs.videojs.com/ for options
 *
 */

import videojs_wavesurfer_css from 'videojs-wavesurfer/dist/css/videojs.wavesurfer.css';
import WaveSurfer from 'wavesurfer.js';
import Wavesurfer from 'videojs-wavesurfer/dist/videojs.wavesurfer.js';
import MicrophonePlugin from 'wavesurfer.js/dist/plugin/wavesurfer.microphone.js';
WaveSurfer.microphone = MicrophonePlugin;
import recordMediaTypes from '@shared/record-media/recordMediaTypes';

/*
* set up a recording
* 
* @returns { Object }
*/ 
export const getRecordOptions = (recordMediaType) => {
	const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
	if (recordMediaType === recordMediaTypes.AUDIO_ONLY) {
		return getAudioOnlyRecordOptions();
	} else if (recordMediaType === recordMediaTypes.CAMERA && isSafari) {
		return getRecordCameraOptionsForSafari();
	} else if (recordMediaType === recordMediaTypes.CAMERA && !isSafari) {
		return getRecordCameraOptions();
	} else if (recordMediaType === recordMediaTypes.SCREEN && isSafari) {
		return getRecordScreenOptionsForSafari();
	} else if (recordMediaType === recordMediaTypes.SCREEN && !isSafari) {
		return getRecordScreenOptions();
	}
}

/*
* The videojs options all camera/screen setups share
* 
* @returns { Object }
*/ 
const getDefaultVideojsRecordOptions = () => {
	return {
		controls: true,
        autoplay: false,
        fluid: true,
        aspectRatio: '16:9',
        loop: false,
        bigPlayButton: false,
        controlBar: {
            volumePanel: true,
        }
	}
}

/*
* The options for an audio only videojs-record
* 
* @returns { Object }
*/ 
const getAudioOnlyRecordOptions = () => {
	const options = getDefaultVideojsRecordOptions();
    options.plugins = {
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
			debug: true,
		}
	}
	applyAudioWorkarounds(options);
	return options;
}

/*
* few audio fixes for safari/edge audio
* see https://github.com/collab-project/videojs-record/blob/master/examples/browser-workarounds.js
* 
*/ 
const applyAudioWorkarounds = (options) => {
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
	const isEdge = /Edge/.test(navigator.userAgent);
	if (isSafari || isEdge) {
        if (isSafari && window.MediaRecorder !== undefined) {
            // this version of Safari has MediaRecorder
            // but use the only supported mime type
            options.plugins.record.audioMimeType = 'audio/mp4';
        } else {
            // support recording in safari 11/12
            // see https://github.com/collab-project/videojs-record/issues/295
            options.plugins.record.audioRecorderType = StereoAudioRecorder;
            options.plugins.record.audioSampleRate = 44100;
            options.plugins.record.audioBufferSize = 4096;
            options.plugins.record.audioChannels = 2;
        }
        console.log('applied audio workarounds for this browser');
    }
}

/*
* set up a camera recording
* 
* @returns { Object }
*/ 
const getRecordCameraOptions = () => {
	const options = getDefaultVideojsRecordOptions();
	options.plugins = {
		record: {
			audio: true,
			video: {
				width: { min: 320, ideal: 640, max: 1280 }, // video media constraints: set resolution of camera
				height: { min: 240, ideal: 360, max: 720 }
			},
			convertEngine: 'ts-ebml', //to be able to have seekable webm files
			maxLength: 300, //five minutes
			debug: true,
		}
	}
	return options;
}

/*
* set up a camera recording for safari, which uses mp4 rather than default webm
* 
* @returns { Object }
*/ 
const getRecordCameraOptionsForSafari = () => {
    const options = getDefaultVideojsRecordOptions();
    options.plugins = {
		record: {
			audio: true,
			video: {
				width: { min: 320, ideal: 640, max: 1280 }, // video media constraints: set resolution of camera
				height: { min: 240, ideal: 360, max: 720 }
			},
			videoMimeType: 'video/mp4', //all supported excluding Safari use webm
			maxLength: 300, //five minutes
			debug: true,
		}
	}
	return options;
}

/*
* set up a screen recording
* 
* @returns { Object }
*/ 
const getRecordScreenOptions = () => {
    const options = getDefaultVideojsRecordOptions();
    options.plugins = {
		record: {
			audio: true,
			screen: true,
			maxLength: 300, //five minutes
			convertEngine: 'ts-ebml', //to be able to have seekable webm files
			frameWidth: 640,
			frameHeight: 480,
			debug: true,
		}
	}
	return options;
}


/*
* set up a screen recording for Safari
* 
* @returns { Object }
*/ 
const getRecordScreenOptionsForSafari = () => {
    const options = getDefaultVideojsRecordOptions();
    options.plugins = {
		record: {
			audio: true,
			screen: true,
			videoMimeType: 'video/mp4', //all supported excluding Safari use webm
			maxLength: 300, //five minutes
			frameWidth: 640,
			frameHeight: 480,
			debug: true,
		}
	}
	return options;
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

