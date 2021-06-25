<script>

/*
 * SCREEN capture is currently not supported, so just display note that this is so
 *
 */

import recordMediaEvents from '@js/record-media/recordMediaEvents';


export default {

    mounted () {
        this.$emit(recordMediaEvents.RESIZE, this.$refs.noScreenNote.offsetWidth, this.$refs.noScreenNote.offsetHeight); //just ensure that the entire message is visible
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
@import '@schoolbox/inputCapture/input-capture';
</style>