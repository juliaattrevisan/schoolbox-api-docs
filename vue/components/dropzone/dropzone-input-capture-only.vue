<script>

/*
 * To show an image that's beeen generated from videojs-record
 *
 */

import { gsap } from 'gsap/all';
import Dropzone from 'dropzone';


export default {

    props: {
        
    },

    data () {
        return {
            hasUpload: false,
        }
    },

    mounted () {
        const myDropzone = new Dropzone(this.$refs.dropzone, { 
            capture: true,

            url: '/file/post'
        });
    },

    methods: {


    }

}
</script>

<template>
    <div class="container">
        <div class="dzone icon-attach" ref="dropzone">
            <span>Click or drag here to attach a feature image</span>
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

.dzone {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}

</style>
