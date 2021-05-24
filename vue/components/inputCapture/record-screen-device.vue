<script>

/*
 * SCREEN capture is currently not supported, so just display note that this is so
 *
 */

import recordMediaEvents from '@shared/record-media/recordMediaEvents';


export default {

    mounted () {
        this.$emit(recordMediaEvents.RATIO_READY, this.$refs.noScreenNote.offsetWidth, this.$refs.noScreenNote.offsetHeight); //just ensure that the entire message is visible
    },

}
</script>

<template>

    <div class="container">

        <div class="media-container media-container-record">
            <div class="alert-box warn" ref="noScreenNote">Sorry, screen recording is not currently possible on this device. To record your screen, please use a desktop computer or laptop.</div>
        </div>

    </div>

</template>

<style lang="scss" scoped>

.container {
    position: absolute;
    width: 100%;
    height: 100%; //will be updated by the parent container, keeping it inline with the parent
    background: white;
    overflow: hidden;
}

.media-container {
    position: absolute;
    width: 100%;
    height: 100%;
    left: 0;
    top: 0;
}

    .media-container-record {
        z-index: 1;
    }

    .media-container-playback {
        z-index: 2;
    }

.file-input-container {
    position: absolute;
    width: 100%;
    height: 100%;
    text-align: center;
}    

.dzone::v-deep { //piggy back on the existing dropzone styles, and update as needed
    position: absolute;
    z-index: 1;
    width: 100%;
    height: 100%;
    font-size: 0px;
    text-indent: -999px; //hide the text, just leaving the dzone like border/display
}

.file-input-note {
    display: block;
    position: relative;
    z-index: 2;
    padding: 20px;
    font-size: 0.8rem;
    pointer-events: none;
}


</style>
