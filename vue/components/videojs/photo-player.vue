<script>

/*
 * To show an image that's beeen generated from videojs-record
 *
 */

import { gsap } from 'gsap/all';


export default {

    props: {
        imgSrc: { //will pass through a dataURL
            type: String,
            required: true,
            default: ''
        },
        isNewPhoto: { //will pass through a dataURL
            type: Boolean,
            required: true,
            default: false
        },
    },

    mounted () {
        this.displayPhoto();
    },

    methods: {

        /**
         * Determine whether to show flash, or just display
         *
         */
        displayPhoto () {
            this.$nextTick(() => {
                if (this.isNewPhoto) {
                    gsap.to(this.$refs.imgForeground, 0.5, { opacity: 0 } ); //say cheese! *FLASH*
                } else {
                    this.$refs.imgForeground.style.opacity = 0;
                }
            });
        }

    }

}
</script>

<template>
    <div>
        <img :src="imgSrc">
        <div class="img-foreground" ref='imgForeground'></div>
    </div>
</template>

<style lang="scss" scoped>

img {
    position: absolute;
    width: 100%;
    height: auto;
}

.img-foreground { //the white background to hide to animate 'photo taken' 
    position: absolute;
    width: 100%;
    height: 100%;
    background: white;
}

</style>
