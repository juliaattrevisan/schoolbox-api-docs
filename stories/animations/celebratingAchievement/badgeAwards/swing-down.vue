<script>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * Pop-up badge award animation component used within Storybook only
 */

import { clearCanvas } from "@js/util/canvasUtils";
import { init } from "../../../../library/Schoolbox/Learning/Badge/Award/js/animations/swingDown";

export default {
    data() {
        return {
            canvasWidth: 0,
            canvasHeight: 0,
        };
    },

    mounted() {
        this.getCanvasDimensions();
        this.animateBadgeImage();
    },

    methods: {
        /**
         * The <canvas> element (that shows the stars) requires accurate width/height to function properly.
         */
        getCanvasDimensions() {
            const canvasDimensions = this.$refs.badgeImgContainer.getBoundingClientRect();
            this.canvasWidth = Math.round(canvasDimensions.width);
            this.canvasHeight = Math.round(canvasDimensions.height);
            this.$nextTick(() => this.animateBadgeImage());
        },

        /**
         * animate the badge
         */
        animateBadgeImage() {
            const badgeImage = this.$refs.badgeImage;
            const awardCanvas = this.$refs.awardCanvas;
            clearCanvas(awardCanvas);
            init(badgeImage, awardCanvas);
        },
    },
};
</script>

<template>
    <div>
        <div class="badge-img-container" ref="badgeImgContainer">
            <div class="badge-img-container__background">
                <canvas :width="canvasWidth" :height="canvasHeight" ref="awardCanvas"></canvas>
            </div>
            <div class="badge-img-container__foreground">
                <img src="badge-award-sample.svg" ref="badgeImage" alt="badge sample" />
            </div>
        </div>
        <button @click="animateBadgeImage">Replay</button>
    </div>
</template>

<style scoped>
.badge-img-container {
    height: 300px;
    width: 972px;
}

button {
    background: #1ea7fd;
    color: white;
    font-weight: bold;
    padding: 10px 20px;
}
</style>
