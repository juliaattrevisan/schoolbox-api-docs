<script>

/**
 * The buttons at the bottom of the create news page
 * There can be 1 or 2 depending on the status of the news
 * They are also only active when the news is drafted
 *
 */

export default {
    
    props: {
        initialArticleId: Number,
        initialApproveRoute: String,
        globalModeration: Boolean,
        globalPosting: Boolean,
        isSubmitted: Boolean,
        isRejected: Boolean,
        isAutoSavable: Boolean,
        localeNews: String
    },

    data () {
        return {
            articleId: 0,
            approveRoute: '',
        }
    },

    computed: {

        /**
		 * to change the text of the submit button depending on the news state
		 *
		 * @returns {String}
		 */
        submitText () {
            if (this.isSubmitted && !this.isRejected) {
                return 'Save';
            } else if (this.requiresModeration && this.articleId) {
                return 'Submit for moderation';
            }
            return 'Publish ' + this.localeNews;
        },

        /**
		 * Switch to 'requiring moderation' if:
         * Global moderation config is turned on
         * Global posting is turned on (no case where a user can post for moderation otherwise)
         * The draft save response has returned false for an approveRoute
		 *
		 * @returns {Boolean}
		 */
        requiresModeration () {
            return this.globalModeration && this.globalPosting && !this.approveRoute;
        },

        /**
		 * to disable buttons when the news isn't ready to be submitted
		 *
		 * @returns {Boolean}
		 */
        isDisabled () {
            if (this.articleId === 0) {
                return true;
            } else {
                return false;
            }
        },

    },

    mounted () {
        this.initDataFromProps();
        this.initNewsSaveListener();
    },

    methods: {

        /**
         * any props that will need to be tracked through the lifecycle
         *
         */
        initDataFromProps () {
            this.articleId = this.initialArticleId;
            this.approveRoute = this.initialApproveRoute;
		},

        /**
         * Once the news item has been saved, can enable the preview/publish buttons 
         * Event is dispatched from /library/Schoolbox/Comms/News/templates/create.twig
         * 
         * @params {Event} with articleId and approveRoute
         *
         */
        initNewsSaveListener (setting) {
            document.addEventListener('create-news-form-actions__on-news-saved', (event) => {
                this.articleId = event.detail.articleId;
                this.approveRoute = event.detail.approveRoute;
            });
		},

    }

}
</script>

<template>
    <ul class="flex-list buttons">
    <template v-if="isAutoSavable">
        <li>
            <button type="submit" id="news-preview" :disabled="isDisabled">Preview</button>
        </li>
    </template>
        <li>
            <button type="submit" class="submit" id="news-submit" :disabled="isDisabled">{{ submitText }}</button>
        </li>
    </ul>
</template>