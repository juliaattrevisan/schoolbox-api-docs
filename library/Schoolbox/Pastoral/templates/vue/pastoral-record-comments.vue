<script>

/**
 * To add comments to an existing pastoral record
 */

import Toast from '@vue/components/toast';
import AddCommentInput from '@vue/components/ckeditor/add-comment-input';
import cdnPrefixMixin from '@vue/components/cdn-prefix-mixin';


export default {

    components: {
        Toast,
        AddCommentInput,
    },

    mixins: [
        cdnPrefixMixin,
    ],

    props: {
        // comments: {
        //     type: Array, 
        //     required: true,
        // },
        siteVersion: {
            type: String,
            required: true
        }
    },

    data () {
        return {
            isProcessing: false,
        }
    },

    mounted () {
        // console.warn('oi');
    },

    methods: {

        /**
         * Comment input failed to load, so show error
         */
        onLoadError () {
            console.warn('error');
            // this.$refs.toast.timeout('Loading comment box failed', false, 5000);
        },

        /**
         * Post a comment
         *
         *  @param { String }
         */
        postComment(newComment) {
            console.warn('postComment');
        }

    },

}

</script>

<template>
    <add-comment-input 
        :cdn-prefix="cdnPrefix"
        :is-processing="isProcessing"
        :site-version="siteVersion"
        @on-load-error="onLoadError"
        @on-post-comment="postComment"
    />
</template>

<style scoped>

</style>