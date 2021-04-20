<script>

import { gsap } from 'gsap/all';
import mediaTypes from '../js/mediaTypes';

/*
 * The initial menu, given maximum presence
 *
 */

export default {

    created () {
        this.mediaTypes = mediaTypes; //to be able to move between media types
    },

    mounted () {
        this.updateContainerHeight();
        this.animateOn();
    },

    methods: {

        /*
         * To set up appropriate initial height on container, keeping the same size as a 4/3 video element
         * 
         */ 
        updateContainerHeight () {
            const targetHeight = this.$refs.pseudoVideo.getBoundingClientRect().width * 0.5625; //so there's no height jumps on moving to selected record method
            this.$refs.pseudoVideo.style.height = targetHeight + 'px';
        },

        /*
         * to show selection options
         * 
         */ 
        animateOn () {
            const allMenuItems = Array.from(this.$refs.pseudoVideo.children);
            allMenuItems.forEach((element, index) => {
                gsap.from(element, 0.6, { 
                    scale: 0.6, 
                    alpha: 0.7, 
                    ease: 'power2.out', 
                    delay: index * 0.1 
                } );
                let titleContainer = element.querySelector('div');
                gsap.from(titleContainer, 0.4, { 
                    marginTop: 50, 
                    alpha: 0, 
                    ease: 'power2.out', 
                    delay: (index * 0.1) + 0.2, 
                    onComplete: this.onTitleRevealed 
                } );
            });
            this.animatedOutCount = 0;
        },


        /**
		 * to make the pseudo video bakground black on animated
		 *
		 */
		onTitleRevealed () {
            this.animatedOutCount++;
            if (this.animatedOutCount === 3) {
                this.$refs.pseudoVideo.style.background = 'black';
            }
        },

        /**
		 * selected, so set and animate out
		 *
         * @params {String}
		 */
		mediaTypeSelected (selectedMediaType) {
            this.selectedMediaType = selectedMediaType;
            this.animateOutMenu();
		},

        /*
         * fade out revealing psuedo video element in the background
         * 
         */ 
        animateOutMenu () {
            const allMenuItems = Array.from(this.$refs.pseudoVideo.children);
            allMenuItems.forEach((element, index) => {
                element.style.border = 'none';
                element.disabled = true; //prevent multiple clicks
                let titleContainer = element.querySelector('div');
                gsap.to(titleContainer, 0.2, { 
                    alpha: 0, 
                    marginBottom: 50,
                    ease: 'power2.in', 
                    delay: index * 0.1 
                } );
                gsap.to(element, 0.3, { 
                    scale: 0.9, 
                    alpha: 0, 
                    ease: 'power2.in', 
                    delay: (index * 0.1) + 0.3, 
                    onComplete: this.onMenuHidden
                } );
            });
            this.animatedOutCount = 0;
        },

        /*
         * animation complete, so dispatch
         * 
         */ 
        onMenuHidden () {
            this.animatedOutCount++;
            if (this.animatedOutCount === 3) {
                this.$emit('on-media-type-selected', this.selectedMediaType);
            }
        },

    }

}

</script>

<template>
    <div>
        <div class="ab-pane">
            <div>
                <div class="pseudo-video" ref="pseudoVideo">
                    <button class="menu-item" @click="mediaTypeSelected(mediaTypes.AUDIO)">
                        <div>
                            <span class='icon-audio'></span>
                            <span>Record audio</span>
                        </div>
                    </button>
                    <button class="menu-item" @click="mediaTypeSelected(mediaTypes.VIDEO)">
                        <div>
                            <div class='icon-video'></div>
                            <span>Record a video</span>
                        </div>
                    </button>
                    <button class="menu-item" @click="mediaTypeSelected(mediaTypes.AUDIO)">
                        <div>
                            <div class='icon-screen'></div>
                            <span>Record your screen</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
        <div class="component-action">
			<section>
                <a role="button" class="button" disabled></a><!-- just to keep the size between all record-x.vue consistent -->
			</section>
		</div>
    </div>
</template>

<style lang="scss" scoped>

$font-family-icon: 'schoolbox';
$text-colour: #0066B0; //pull this out of settings 

.pseudo-video {
    width: 100%;
    height: 300px; //will be updated at mounted()
    display: flex;
    background: black;
    overflow: hidden;
}

.menu-item {
    width: calc(33.4%);
    height: 100%;
    background: #FAFAFA;
    span {
        display: block;
        transition: padding-bottom 0.2s;
    }
    &:hover {
        background: white;
        span {
            padding-bottom: 20px;
        }
    }
}

.icon {
    display: block;
    margin: 0px auto 10px auto;
    width: 50px;
    height: 50px;
    font-family: $font-family-icon;
    &:before {
        color: $text-colour;
        font-size: 50px;
        line-height: 1;
    }
}

    .icon-audio {
        @extend .icon;
        &:before {
            content: '\e606';
        }
    }

    .icon-video {
        @extend .icon;
        &:before {
            content: '\e674';
        }
    }

    .icon-screen {
        @extend .icon;
        &:before {
            content: '\e917';
        }
    }

</style>