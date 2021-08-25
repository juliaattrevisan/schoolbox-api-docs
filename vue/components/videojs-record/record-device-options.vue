<script>

/**
 * When available, a device list will be presented for the user to choose which input device
 * they would like to record with
 */

export default {
    
    props: {
        deviceList: {
            type: Array,
            required: true
        }
    },

}

</script>

<template>
    <div>
        <div class="device-options">
            <fieldset class="radiolist device-options-fieldset">
                <legend class="device-options-legend">Device Options</legend>
                <ul class="option-list device-options-list">
                    <li v-for="device in deviceList" :key="device.id">
                        <input type="radio" value="Red" id="pokemonRed"><label class="device-options-label" for="pokemonRed">{{ device.label }}</label>
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