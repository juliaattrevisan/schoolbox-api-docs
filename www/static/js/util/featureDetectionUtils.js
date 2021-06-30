/*
 * Check for a specific feature in the brower, typically returning true/false
 *
 */

/** 
* To use the devices native capture tools
* 
* @returns { Boolean } 
*/ 
export const hasDeviceCapture = () => {
    const inputElement = document.createElement('input')
	const isSupported = inputElement.capture != undefined;
	return isSupported;
}

/**
* Ensure can enumerate devices 
*
*/
export const isEnumerateDevicesSupported = () => {
    if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
        return false;
    } else {
        return true;
    }
}

/**
* Checks through the enumerateDevices list to ensure the device has a video input
*
* @param { Array }
*
* @returns { Boolean }
*/
export const supportsVideoRecording = (deviceList) => {
    const videoRecordingList = deviceList.filter((device) => device.kind === 'videoinput');
    if (videoRecordingList.length === 0) {
        return false;
    } else {
        return true;
    }
}

/**
*  Checks through the enumerateDevices list to ensure the device has a audio input
*
* @param { Array }
*
* @returns { Boolean }
*/
export const supportsAudioRecording = (deviceList) => {
    const audioRecordingList = deviceList.filter((device) => device.kind === 'audioinput');
    if (audioRecordingList.length === 0) {
        return false;
    } else {
        return true;    
    }
}