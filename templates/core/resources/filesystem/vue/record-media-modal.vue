<script>

/*
 * To notify of various media events, like camera not available or video converting/uploading
 *
 */

import { gsap } from 'gsap/all';

export default {
    
    props: {
        closedCallBack: { //what to do when the modal is closed
     		type: Function,
      		required: true,
    	},
    },

    mounted () {
        this.animateOn();
    },

    methods: {

        /*
         * to present the modal
         * 
         */ 
        animateOn () {
            gsap.from(this.$refs.modalContainer, 0.3, { alpha: 0 } );
            gsap.from(this.$refs.modal, 0.3, { alpha: 0, marginTop: 40, delay: 0.15 } );
        },

        /*
         * to close the modal
         * 
         */ 
        animateOff () {
            gsap.to(this.$refs.modal, 0.2, { alpha: 0, marginTop: 40, ease: 'power2.in' } );
            gsap.to(this.$refs.modalContainer, 0.2, { alpha: 0, ease: 'power2.in', delay: 0.1, onComplete: this.closedCallBack } );
        }

    }

}
</script>

<template>
    <div class="modal-container" ref="modalContainer">
        <div class="modal" ref="modal">
            <slot></slot>
        </div>
    </div>
</template>

<style lang="scss" scoped>

.modal-container {
	position: absolute;
	left: 0;
	top: 0;
	z-index: 99;
	width: 100%;
	height: 100%;
	display: flex;
	align-items: center;
  	justify-content: center;
	background: rgba(0, 0, 0, 0.8);
}

.modal {
	max-width: 60%;
	padding: 30px;
	background: white;
	button {
		width: 100%;
	}
}

</style>