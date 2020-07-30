export const ENDPOINTS = Object.freeze({

    /* Endpoint for the Top 10 visitors payload
     */
    URL_VISITATION_PAYLOAD_TOP10: '/news/statistics/{articleId}/visitation/top10',

    /* Endpoint for the Full visitors payload
     */
    URL_VISITATION_PAYLOAD_FULL: '/news/statistics/{articleId}/visitation/full',

    /* Url for the Full report
     */
    URL_VISITATION_FULL: '/news/statistics/{articleId}/visitation',

    /**
     * Generate a path via const
     *
     * @param str        the path where we need to replace a var
     * @param articleId  the news article's id
     * @return string
     */
    generateUrlFromConst(str, articleId) {
        return str.replace('{articleId}', articleId);
    },


    /**
     * Get Top 10 payload data about a given article
     *
     * @param articleId
     * @return string
     */
    payloadTop10Path: function(articleId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_VISITATION_PAYLOAD_TOP10, articleId);
    },

    /**
     * Get Full payload data about a given article
     *
     * @param articleId
     * @return string
     */
    payloadFullPath: function(articleId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_VISITATION_PAYLOAD_FULL, articleId);
    }
});
