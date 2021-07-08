
import { isEnumerateDevicesSupported, supportsVideoRecording, supportsAudioRecording, hasDeviceCapture } from '@js/util/featureDetectionUtils.js';

/**
* Hide share work buttons to record methods that the device doesn't support
*/
const checkShareWorkButtons = async () => {
    if (isEnumerateDevicesSupported()) { //if can't even enumerate, assumed there's no devices to access
        try {
            const deviceList = await navigator.mediaDevices.enumerateDevices(); //use the device list to check for video and audio input
            if (!supportsVideoRecording(deviceList) && !hasDeviceCapture()) { //allow phone/tablet devices to access with inputCapture
                hideVideoButtons();
            } 
            if (!supportsAudioRecording(deviceList)) {
                hideAudioButtons();
            }
        } catch (error) {
            console.warn(`${err.name}:${err.message}`);
        }
    } else {
        hideVideoButtons();
        hideAudioButtons();
    }
}

/**
* Hide video buttons
*/
const hideVideoButtons = () => {
    document.querySelector('[data-take-photo-button]').style.display = 'none';
    document.querySelector('[data-record-video-button]').style.display = 'none';
}

/**
* Hide audio buttons
*/
const hideAudioButtons = () => {
    document.querySelector('[data-record-audio-button]').style.display = 'none';
}

checkShareWorkButtons();