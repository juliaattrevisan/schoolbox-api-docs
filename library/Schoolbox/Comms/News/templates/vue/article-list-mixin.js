/**
 * Shared between each of the Layout components
 */
export default {
    computed: {

        /**
         * Detect whether Compact Mode is on
         *
         * @param option
         * @returns {boolean}
         */
        isCompactMode() {
            return parseInt(this.compactMode) !== 0;
        }
    },
    methods: {
        /**
         * Save an article for later
         *
         * @param article
         */
        saveForLater: function(article) {
            var self = this;

            var requestURL,
                toastActivateMessage,
                toastSuccessMessage,
                toastFailMessage,
                updateArticleIds;

            function removeArticleId(articleId) {
                var articleIdIndex = self.savedArticleIds.indexOf(articleId)
                self.savedArticleIds.splice(articleIdIndex, 1);
            }

            function addArticleId(articleId) {
                self.savedArticleIds.splice(0, 0, articleId);
            }

            if (self.savedArticleIds.indexOf(article.id) >= 0 && article._links.unsaveForLater.auth) {
                // Article is already saved; this is an "unsave" action
                requestURL = article._links.unsaveForLater.href;
                toastActivateMessage = 'Removing...';
                toastSuccessMessage = 'Removed';
                toastFailMessage = 'Unable to remove';
                updateArticleIds = removeArticleId;
            } else if (article._links.saveForLater.auth) {
                // Save the article for later
                requestURL = article._links.saveForLater.href;
                toastActivateMessage = 'Saving...';
                toastSuccessMessage = 'Saved';
                toastFailMessage = 'Unable to save';
                updateArticleIds = addArticleId;
            }

            var $toast = $('#toaster');
            $toast.toastActivate({text: toastActivateMessage});
            axios.post(requestURL, [], { headers: postHeaders }).then(function () {
                $toast.toastTimeout({text: toastSuccessMessage, duration: 2000});
                updateArticleIds(article.id);
            }).catch(function (error) {
                console.log(error);
                $toast.toastTimeout({text: toastFailMessage, duration: 2000, css: 'alert'});
            });
        }
    },

    filters: {

        /**
         * Pluralise the comment count string
         *
         * @param value
         * @returns {string}
         */
        formatCommentCountMeta: function (value) {
            let suffix = ' comment';

            if (value > 1) {
                suffix += 's';
            }

            return value + suffix;
        }
    }
}
