export const ENDPOINTS = Object.freeze({

    /* Evidence URLs
     */
    URL_MOMENT_EVIDENCE_DETAIL: '/learning/evidence/{evidenceId}',

    /**
     * Generate a path via const
     *
     * Provision of the returnUrl allows us to return to the place
     * from whence thou cam'st
     *
     * @param str        the path where we need to replace a var
     * @param evidenceId the evidence's id
     * @param returnUrl  a url to visit after saving
     * @return string
     */
    generateUrlFromConst(str, evidenceId, returnUrl) {
        let url = str.replace('{evidenceId}', evidenceId);

        if (returnUrl !== undefined && returnUrl !== null) {
            url += '?returnUrl=' + returnUrl;
        }

        return url;
    },


    /**
     * Evidence Detail
     *
     * @param evidenceId
     * @return string
     */
    momentEvidenceDetailPath: function(evidenceId) {
        return this.generateUrlFromConst(ENDPOINTS.URL_MOMENT_EVIDENCE_DETAIL, evidenceId, btoa(window.location.pathname));
    }
});
