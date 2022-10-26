/**
 * Any shared methods related to badge awards that dont
 * require the reactivity of mixins/composables
 */

/**
 * Generate a URL which references this page with all filters cleared.
 *
 * @param { string }
 * @param { string }
 *
 * @return { string }
 */
export function generateAwardWithEvidenceUrl (selectedUserIds, selectedBadgeIds) {
    const evidenceUrl = `/learning/badge/award/user/${selectedUserIds}/award`;
    const emptyQueryString = {};
    emptyQueryString['badges'] = selectedBadgeIds;
    const url = new URL(window.location.href);
    if (url.searchParams.get('returnUrl') !== null) {
        emptyQueryString['returnUrl'] = url.searchParams.get('returnUrl');
    }
    return evidenceUrl + "?" + new URLSearchParams(emptyQueryString).toString();
}