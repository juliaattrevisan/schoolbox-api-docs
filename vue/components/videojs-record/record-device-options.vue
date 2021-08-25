<script>

import recordMediaEvents from '@js/record-media/recordMediaEvents';

/**
 * When there's more than one, a device list will be presented for the user to choose which input device
 * they would like to record with
 */

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
            required: true
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
        <div class="device-options">
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


</style>