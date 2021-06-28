/**
 * Redirect to the upgrade.php page if browser is not supported
 * No IE versions are supported: `"ActiveXObject" in window` ought to detect IE
 */
if ("ActiveXObject" in window) {
    window.location.href = '/upgrade.php';
}
