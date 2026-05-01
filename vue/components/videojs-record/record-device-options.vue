<script>
/* eslint-disable @eslint-community/eslint-comments/disable-enable-pair -- see below */
/* eslint-disable @eslint-community/eslint-comments/no-unlimited-disable -- see below */
/* eslint-disable -- Ignore this file for ESLint checks for now (SA-18376) */
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
                            <input
                                type="radio"
                                name="video-device"
                                :id="`${device.deviceId}`"
                                :checked="device.deviceId === currentDeviceId"
                                @click.prevent="onDeviceSelected(device.deviceId)"
                            /><label :for="`${device.deviceId}`">{{ device.label }}</label>
                        </li>
                    </ul>
                </fieldset>
            </div>
        </transition>
    </div>
</template>

<style lang="scss" scoped>
.device-options {
    background: rgba(43, 51, 63, 0.85);
    border: 2px solid var(--sbx-color-foreground-primary, white);
    border-radius: var(--sbx-config-field-roundness, 0);
    color: var(--sbx-color-foreground-primary, white);
    left: 10px;
    max-width: 50%;
    padding: var(--spacing-s) var(--spacing-m);
    position: absolute;
    top: 10px;
    z-index: calc(var(--sbx-depth-default) + 1);
    label {
        color: var(--sbx-color-foreground-primary, white);
    }
}

.legend {
    margin-bottom: 10px;
}

.fieldset {
    border: 0;
    margin: 0;
    padding: 0;
}

.option-list {
    margin: 0;
    padding: 0;
}

.slide-enter-active,
.slide-leave-active {
    transition: left 0.5s;
}
.slide-enter, .slide-leave-to /* .fade-leave-active below version 2.1.8 */ {
    left: -400px; //the video container can't be bigger then 700px, so enough to ensure it can't be shown
}
</style>
