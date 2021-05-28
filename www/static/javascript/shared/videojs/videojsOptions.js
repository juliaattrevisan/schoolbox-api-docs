/*
 * The videojs options to pass through various recordings, 
 * kept here to ensure consistency
 * Note that videojs-record extends of videojs to add the record features
 * refer to https://collab-project.github.io/videojs-record/#/options and https://docs.videojs.com/ for options
 *
 */

import WaveSurfer from 'wavesurfer.js';
import Wavesurfer from 'videojs-wavesurfer/dist/videojs.wavesurfer.js';
import MicrophonePlugin from 'wavesurfer.js/dist/plugin/wavesurfer.microphone.js';
WaveSurfer.microphone = MicrophonePlugin;


const RECORDING_LENGTH = 180; //3 minutes
const IS_DEBUG_MODE = true;


/**
* The videojs options all camera/screen setups share
* 
* @returns { Object }
*/ 
const getDefaultVideojsRecordOptions = () => {
    return {
        controls: true,
        autoplay: false,
        fluid: true,
        responsive: true,
        loop: false,
        bigPlayButton: false,
        controlBar: {
            liveDisplay: false,
            volumePanel: false,
            pictureInPictureToggle: false
        }
    }
}

/** 
* The options for an audio only videojs-record
* 
* @returns { Object }
*/ 
export const getAudioOnlyRecordOptions = (maxFileSize) => {
	const options = getDefaultVideojsRecordOptions();
    options.plugins = {
		wavesurfer: { // this plugin is only needed when recording audio-only
			backend: 'WebAudio',
			waveColor: '#ccc',
			progressColor: '#ccc',
			debug: IS_DEBUG_MODE,
			cursorWidth: 1,
			displayMilliseconds: false,
            hideScrollbar: true,
            responsive: true,
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
            maxLength: RECORDING_LENGTH, 
            audioMimeType: 'audio/webm;codecs=opus',
            convertEngine: 'ts-ebml', //to be able to have seekable webm files
			debug: IS_DEBUG_MODE,
            maxFileSize: maxFileSize,
            timeSlice: 1000, //to enable the maxFileSize restriction
		}
	}
	applyAudioWorkarounds(options);
	return options;
}

/** 
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
    }
}

/**
* set up a camera recording
* 
* @returns { Object }
*/ 
export const getRecordCameraOptions = (maxFileSize) => {
    const options = getDefaultVideojsRecordOptions();
    options.plugins = {
        record: {
            audio: true,
            video: {
                width: { min: 320, ideal: 720, max: 1280 }, // video media constraints: set resolution of camera
                height: { min: 180, ideal: 405, max: 720 }
            },
            videoMimeType: 'video/webm;codecs=vp8,opus', //enforce webm see https://www.webrtc-experiment.com/RecordRTC/simple-demos/isTypeSupported.html
            convertEngine: 'ts-ebml', //to be able to have seekable webm files
            maxLength: RECORDING_LENGTH, 
            debug: IS_DEBUG_MODE,
            maxFileSize: maxFileSize,
            timeSlice: 1000, //to enable the maxFileSize restriction
        }
    }
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    if (isSafari) { //ensure mp4 on Safari
        options.plugins.record.videoMimeType = 'video/mp4'; 
    }
    return options;
}

/** 
* set up a screen recording
* 
* @returns { Object }
*/ 
export const getRecordScreenOptions = (maxFileSize) => {
    const options = getDefaultVideojsRecordOptions();
    options.plugins = {
        record: {
            audio: true,
            screen: true,
            maxLength: RECORDING_LENGTH,
            videoMimeType: 'video/webm;codecs=vp8,opus', //enforce webm see https://www.webrtc-experiment.com/RecordRTC/simple-demos/isTypeSupported.html
            convertEngine: 'ts-ebml', //to be able to have seekable webm files
            debug: IS_DEBUG_MODE,
            maxFileSize: maxFileSize,
            timeSlice: 1000, //to enable the maxFileSize restriction
        }
    }
    const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
    if (isSafari) { //ensure mp4 on Safari
        options.plugins.record.videoMimeType = 'video/mp4'; 
    }
    return options;
}

/** 
* set up a screen recording
* 
* @returns { Object }
*/ 
export const getRecordPhotoOptions = () => {
    const options = getDefaultVideojsRecordOptions();
    options.width = 720,
    options.height = 540,
    options.plugins = {
        record: {
            imageOutputType: 'dataURL',
            imageOutputFormat: 'image/jpeg',
            imageOutputQuality: 0.8,
            image: {
                width: { min: 320, ideal: 720, max: 1280 }, // video media constraints: set resolution of camera
                height: { min: 240, ideal: 540, max: 920 }
            },
            debug: IS_DEBUG_MODE,
        }
    }
    return options;
}

/** 
* the default playback options
* 
* @returns { Object }
*/ 
const getVideoPlayerOptions = () => {
    return {
        controls: true,
        autoplay: false,
        fluid: true,
        responsive: true,
        loop: false,
        bigPlayButton: true,
        controlBar: {
            pictureInPictureToggle: false
        },
    }
}

/** 
* Once a video has been recorded, need to pass to another videojs component to be able to use the progress controls
* 
* @params { Blob }
*
* @returns { Object }
*/ 
export const getVideoPlayerOptionsWithBlob = (recordedData) => {
    const options = getVideoPlayerOptions();
    options.sources = [{
        src: URL.createObjectURL(recordedData),
        type: recordedData.type
    }];
    return options;
}

/**
* Show an existing video with src
* 
* @params { Object: src, type }
*
* @returns { Object }
*/ 
export const getVideoPlayerOptionsWithSrc = (recordedData) => {
    const options = getVideoPlayerOptions();
    options.sources = [{
        src: recordedData.src,
        type: recordedData.type,
        hash: recordedData.hash
    }];
    return options;
}


/**
* add in the wavesurfer to visulise the order
* 
* @returns { Object }
*/ 
const getAudioPlayerOptions = () => {
    const options = getVideoPlayerOptions();
    options.plugins = {
        wavesurfer: { 
            backend: 'MediaElement',
            waveColor: '#ccc',
            progressColor: '#ccc',
            debug: IS_DEBUG_MODE,
            cursorWidth: 1,
            displayMilliseconds: false,
            hideScrollbar: true,
            interact: false,
            fluid: true,
            responsive: true
        }
    }
    return options;
}

/**
* Once a audio has been recorded, need to pass to another videojs component to be able to use the progress controls
* 
* @params { Blob }
*
* @returns { Object }
*/ 
export const getAudioPlayerOptionsWithBlob = (recordedData) => {
    const options = getAudioPlayerOptions();
    options.audioPlayerSrc = URL.createObjectURL(recordedData);
    options.audioPlayerType = recordedData.type;
    return options;
}

/**
* Show an existing audio with src
* 
* @params { Object: src, type }
*
* @returns { Object }
*/ 
export const getAudioPlayerOptionsWithSrc = (recordedData) => {
    const options = getAudioPlayerOptions();
    options.audioPlayerSrc = recordedData.src;
    options.audioPlayerType = recordedData.type;
    options.audioPlayerHash = recordedData.hash;
    return options;
}
