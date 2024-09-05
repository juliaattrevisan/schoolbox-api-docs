<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * When there's more than one, a device list will be presented for the user to choose which input device
 * they would like to record with
 */

import recordMediaEvents from "@js/record-media/recordMediaEvents";

export default {
    props: {
        title: {
            type: String,
            required: true,
        },
        currentDeviceId: {
            type: String,
            required: true,
        },
        deviceList: {
            type: Array,
            required: true,
        },
        isRecording: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        /**
         * only show if more than one device and not recording
         *
         * @returns { Boolean }
         */
        canDisplay() {
            return this.deviceList.length > 1 && !this.isRecording;
        },
    },

    methods: {
        /**
         * onDeviceSelected
         */
        onDeviceSelected(deviceId) {
            this.$emit(recordMediaEvents.VIDEO_DEVICE_CHANGE, deviceId);
        },
    },
};
</script>

<template>
    <div>
        <transition name="slide">
            <div v-if="canDisplay" class="device-options">
                <fieldset class="fieldset">
                    <legend class="legend">{{ title }}</legend>
                    <ul class="option-list">
                        <li v-for="device in deviceList" :key="device.deviceId">
                            <a
                                href="#toggle-device"
                                :class="`toggle-button ${
                                    device.deviceId === currentDeviceId ? 'toggle-button-active' : ''
                                }`"
                                @click.prevent="onDeviceSelected(device.deviceId)"
                            >
                                <span class="toggle-button-icon vjs-icon-photo-camera"></span>
                                <span class="toggle-button-text">{{ device.label }}</span>
                            </a>
                        </li>
                    </ul>
                </fieldset>
            </div>
        </transition>
    </div>
</template>

<style lang="scss" scoped>
.device-options {
    position: absolute;
    z-index: calc(var(--sbx-depth-default) + 1);
    top: 10px;
    left: 10px;
    max-width: 50%;
    border: 2px solid white;
    padding: 0.5rem 1rem;
    color: white;
    background: rgba(43, 51, 63, 0.85);
}

.legend {
    margin-bottom: 10px;
    font-size: 0.8125rem;
}

.fieldset {
    margin: 0;
    border: 0;
    padding: 0;
}

.option-list {
    margin: 0;
    padding: 0;
}

.toggle-button {
    display: block;
    margin: 0 0 5px 0;
    padding: 3px 0;
    color: white;
    background: none;
    text-align: left;
    font-size: 0.8125rem;
    opacity: 0.6;
}

.toggle-button-active {
    opacity: 1;
}

.toggle-button-icon {
    position: absolute;
}

.toggle-button-text {
    display: inline-block;
    padding-left: 20px;
}

.slide-enter-active,
.slide-leave-active {
    transition: left 0.5s;
}
.slide-enter, .slide-leave-to /* .fade-leave-active below version 2.1.8 */ {
    left: -400px; //the video container can't be bigger then 700px, so enough to ensure it can't be shown
}
</style>
