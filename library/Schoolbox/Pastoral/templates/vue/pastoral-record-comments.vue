<script>

/**
 * To add comments to an existing pastoral record
 */

import Toast from '@vue/components/toast';
import AddCommentInput from '@vue/components/ckeditor/add-comment-input';
import cdnPrefixMixin from '@vue/components/cdn-prefix-mixin';
import cloneDeep from 'lodash/cloneDeep';


export default {

    components: {
        Toast,
        AddCommentInput,
    },

    mixins: [
        cdnPrefixMixin,
    ],

    props: {
        commentHeading: {
            type: String, 
            required: true,
        },
        commentInsertRoute: {
            type: String, 
            required: true,
        },
        initialComments: {
            type: Array, 
            required: true,
        },
        record: {
            type: Object, 
            required: true,
        },
        recordType: {
            type: String, 
            required: true,
        },
        siteVersion: {
            type: String,
            required: true
        }
    },

    data () {
        return {
            comments: [],
            isProcessing: false,
        }
    },

    mounted () {
        this.comments = cloneDeep(this.initialComments);
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
    <form method="post" :action="commentInsertRoute">
        <add-comment-input 
            :cdn-prefix="cdnPrefix"
            :is-processing="isProcessing"
            :site-version="siteVersion"
            @on-load-error="onLoadError"
            @on-post-comment="postComment"
        />
        <input v-if="recordType === 'private'" type="hidden" name="comment_private" value="1" />
    </form>
</template>

<style scoped>

</style>