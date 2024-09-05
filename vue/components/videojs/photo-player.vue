<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/*
 * To show an image that's beeen generated from videojs-record
 *
 */

import { gsap } from "gsap/all";

export default {
    props: {
        imgSrc: {
            //will pass through a dataURL
            type: String,
            required: true,
            default: "",
        },
        isNewPhoto: {
            //to animate on
            type: Boolean,
            required: true,
            default: false,
        },
    },

    mounted() {
        this.displayPhoto();
    },

    methods: {
        /**
         * Determine whether to show flash, or just display
         *
         */
        displayPhoto() {
            this.$nextTick(() => {
                if (this.isNewPhoto) {
                    gsap.to(this.$refs.imgForeground, 0.5, { opacity: 0 }); //say cheese! *FLASH*
                } else {
                    this.$refs.imgForeground.style.opacity = 0;
                }
            });
        },

        /**
         * The image is loaded, so dispatch size to anyone listening
         *
         */
        onImgLoad() {
            this.$emit("on-dimensions-ready", this.$refs.img.width, this.$refs.img.height);
        },
    },
};
</script>

<template>
    <div>
        <img ref="img" :src="imgSrc" @load="onImgLoad" />
        <div class="img-foreground" ref="imgForeground"></div>
    </div>
</template>

<style lang="scss" scoped>
img {
    position: absolute;
    width: 100%;
    height: auto;
}

.img-foreground {
    //the white background to hide to animate 'photo taken'
    position: absolute;
    width: 100%;
    height: 100%;
    background: white;
}
</style>
