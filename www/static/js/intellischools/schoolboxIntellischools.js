/**
 * A set of convenient methods shared everywhere
 *
 * @type {{isAllowed: ((function(*): (boolean))|*)}}
 */
window.schoolboxIntellischools = {
    /**
     * Decide if url is an allowed domain
     * Whitelist allowed domains
     *
     * @param url
     */
    isAllowed: function (url) {
        /* Test new domains
         */
        if (url.match(/^https:\/\/(.*)intellischool\.app$/) != null) {
            return true;
        }

        /* If that's not allowed, test the old domains
         * (These old domains will eventually be phased out at some point)
         */
        if (
            [
                "https://analytics.intellischool.cloud",
                "https://analytics.intellischool.xyz",
                "https://analytics.intellischool.dev",
            ].includes(url)
        ) {
            return true;
        }

        /* Everything else get out
         */
        return false;
    },
};
