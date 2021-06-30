
import { isEnumerateDevicesSupported, supportsVideoRecording, supportsAudioRecording } from '@js/util/featureDetectionUtils.js';

/**
* Hide share work buttons to record methods that the device doesn't support
*/
const checkRecordButton = async () => {
    if (isEnumerateDevicesSupported()) { //if can't even enumerate, assumed there's no devices to access
        try {
            const deviceList = await navigator.mediaDevices.enumerateDevices(); //use the device list to check for video and audio input
            if (!supportsVideoRecording(deviceList) && supportsAudioRecording(deviceList)) {
                hideRecordButton();
            } 
        } catch (error) {
            console.warn(`${err.name}:${err.message}`);
        }
    } else {
        hideRecordButton();
    }
}

/**
* If doesn't support video or audio don't show the record button
* Can screen sharing still be available? Let's say it shouldn't be when audio can't also be recorded
*/
const hideRecordButton = () => {
    document.querySelector('[data-source="filesystem-usermedia"]').style.display = 'none';
}


checkRecordButton();