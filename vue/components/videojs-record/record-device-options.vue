<script>

/**
 * When available, a device list will be presented for the user to choose which input device
 * they would like to record with
 */

export default {
    
}

</script>

<template>
    <div>
        <div class="device-options">
            <fieldset class="radiolist device-options-fieldset">
                <legend class="device-options-legend">Device Options</legend>
                <ul class="option-list device-options-list">
                    <li><input type="radio" value="Red" id="pokemonRed" checked><label class="device-options-label" for="pokemonRed">Red</label></li>
                    <li><input type="radio" value="Blue" id="pokemonBlue"><label class="device-options-label" for="pokemonBlue">Blue</label></li>
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