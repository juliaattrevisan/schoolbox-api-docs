<script>

/*
 * The top menu, to toggle between recording types
 *
 */

import { gsap } from 'gsap/all';
import mediaTypes from '../js/mediaTypes';

export default {
    
    props: {
        currentMediaType: { //to be able to respond to the initial change from UNSET
     		type: String,
      		required: true,
      		default: ''
    	},
    },

    watch: {

        currentMediaType (value) { 
            if (value !== mediaTypes.UNSET)  { //show the top menu now
                this.animateOn();
            }      
        }

    },

    created () {
        this.mediaTypes = mediaTypes; //to be able to move between media types
    },

    mounted () {
        this.setInitialPosition();
    },

    methods: {

        /*
         * to animate on the menu when an item within #record-media-unset is selected
         * 
         */ 
        setInitialPosition () {
            const allMenuItems = Array.from(this.$refs.menu.children);
            allMenuItems.forEach((element) => {
                gsap.set(element, { top: 44 } );
            });
        },

        /*
         * to show selection options
         * 
         */ 
        animateOn () {
            const allMenuItems = Array.from(this.$refs.menu.children);
            allMenuItems.forEach((element, index) => {
                gsap.to(element, 0.5, { 
                    top: 0, 
                    ease: 'power2.inOut', 
                    delay: index * 0.1 
                } );
            });
        }

    }

}
</script>

<template>
    <div class="menu" ref="menu">
        <button class="menu-item" @click="mediaTypeSelected(mediaTypes.AUDIO)">Record audio</button>
        <button class="menu-item" @click="mediaTypeSelected(mediaTypes.VIDEO)">Record a video</button>
        <button class="menu-item" @click="mediaTypeSelected(mediaTypes.UNSET)">Record your screen</button>
    </div>
</template>

<style lang="scss" scoped>

.menu {
    display: flex;
    overflow: hidden;
}

.menu-item {
    width: calc(33.333333%);
    border-left: 2px solid white;
    border-right: 2px solid white;
    &:first-of-type {
        border-left: none;
    }
    &:last-of-type {
        border-right: none;
    }
}

</style>