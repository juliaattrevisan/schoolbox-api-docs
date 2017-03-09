/**
 * Redirect to the upgrade.php page if browser is not supported
 * Currently: IE10 or lower
 */
if (Function('/*@cc_on return document.documentMode<=10@*/')()) {
    window.location.href = '/upgrade.php';
}