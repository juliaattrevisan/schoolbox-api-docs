<script>

/**
 * When there's more than one, a device list will be presented for the user to choose which input device
 * they would like to record with
 */

import recordMediaEvents from '@js/record-media/recordMediaEvents';

export default {
    
    props: {
        isRecording: {
            type: Boolean,
            required: true,
        },
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
            required: true
        }
    },

    computed: {
        
        /**
         * only show if more than one device and not recording
         * 
         * @returns { Boolean }
         */
        canDisplay () {
            return this.deviceList.length > 1 && !this.isRecording;
        }

    },

    methods: {

        /**
         * onDeviceSelected
         */
        onDeviceSelected (event) {
            this.$emit(recordMediaEvents.VIDEO_DEVICE_CHANGE, event.target.value);
        },

    }

}

</script>

<template>
    <div>
        <transition name="slide">
            <div v-if="canDisplay" class="device-options">
                <fieldset class="radiolist device-options-fieldset">
                    <legend class="device-options-legend">{{ title }}</legend>
                    <ul class="option-list device-options-list">
                        <li v-for="device in deviceList" :key="device.deviceId">
                            <input type="radio" 
                                :value="device.deviceId" 
                                :id="device.deviceId" 
                                :checked="device.deviceId === currentDeviceId"
                                @click.prevent="onDeviceSelected" 
                            >
                            <label class="device-options-label" :for="device.deviceId">{{ device.label }}</label>
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
    z-index: 2;
    top: 10px;
    left: 10px; 
    max-width: 40%;
    border: 2px solid white;
    padding: 0.5rem 1rem;
    color: white;
    background: rgba(43, 51, 63, 0.7);
}

.device-options-legend {
    margin-bottom: 0.8rem;
    font-size: 0.8rem;
}

.device-options-fieldset {
    margin: 0;
    padding: 0;
}

.device-options-list {
    margin: 0;
    padding: 0;
}

.device-options-label {
    color: white;
}

.slide-enter-active, .slide-leave-active {
  transition: left .5s;
}
.slide-enter, .slide-leave-to /* .fade-leave-active below version 2.1.8 */ {
  left: -400px; //the video container can't be bigger then 700px, so enough to ensure it can't be shown
}

</style>