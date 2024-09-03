/**
 * Contains the functionality to toggle between devices when there is more than one available
 * Currently used by record-camera-browser.vue
 * record-photo-browser.vue will hopefully be using it as well as soon as an issue with
 * videojs-record is resolved
 */

import { moveArrayItem } from "@js/util/arrayUtils";

export default {
    data() {
        return {
            chooseDevice_hasEnumeratedDevices: false, //has checked through the device list
            chooseDevice_videoDeviceList: [], //list of video devices currently available to record with
            chooseDevice_currentVideoDeviceId: "", //the active video device, can change when multiple devices available
            chooseDevice_hasDeviceChangeError: false, //wasn't able to switch to the different device, will move back to the default
        };
    },

    methods: {
        /**
         * onEnumerateReady, the list of input devices is available
         */
        chooseDevice_onEnumerateReady() {
            this.chooseDevice_hasEnumeratedDevices = true;
            this.player.off("enumerateReady", this.chooseDevice_onEnumerateReady);
            this.chooseDevice_getVideoDeviceList();
        },

        /**
         * get a list of possible video input devices so the user can toggle between them when there's more than one
         */
        chooseDevice_getVideoDeviceList() {
            const allDeviceList = this.player.record().devices;
            this.chooseDevice_videoDeviceList = allDeviceList.filter((device) => device.kind === "videoinput");
            if (this.chooseDevice_currentVideoDeviceId !== this.chooseDevice_videoDeviceList[0].deviceId) {
                //ensure the top device list is the currently active one, bit weird if the default active is lower down the list
                const activeDeviceIndex = this.chooseDevice_videoDeviceList.findIndex(
                    (videoDevice) => this.chooseDevice_currentVideoDeviceId === videoDevice.deviceId,
                );
                this.chooseDevice_videoDeviceList = moveArrayItem(
                    this.chooseDevice_videoDeviceList,
                    activeDeviceIndex,
                    0,
                );
            }
        },

        /**
         * To toggle between different video devices
         *
         * @params { String }
         */
        chooseDevice_onVideoDeviceChange(videoDeviceId) {
            try {
                this.chooseDevice_currentVideoDeviceId = videoDeviceId;
                this.player.record().setVideoInput(this.chooseDevice_currentVideoDeviceId);
            } catch (error) {
                //if can't set the video input, move back to the default device, notifying so
                this.hasError = true;
                this.chooseDevice_onDeviceChangeError();
            }
        },

        /**
         * onDeviceChangeError, return to the default device, notifying so
         */
        chooseDevice_onDeviceChangeError() {
            this.chooseDevice_hasDeviceChangeError = true;
            this.chooseDevice_currentVideoDeviceId = this.chooseDevice_videoDeviceList[0].deviceId;
            this.player.record().setVideoInput(this.chooseDevice_currentVideoDeviceId);
        },
    },
};
