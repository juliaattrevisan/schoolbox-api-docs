/**
 * To detect versions of Safari below 14.1 (which is the first version to support flex-gap)
 * From: https://ishadeed.com/article/flexbox-gap/
 *
 * @returns { boolean } true if flex-gap is supported
 */
function checkFlexGap() {
    // create flex container with row-gap set
    const flex = document.createElement("div");
    flex.style.display = "flex";
    flex.style.flexDirection = "column";
    flex.style.rowGap = "1px";

    // create two, elements inside it
    flex.appendChild(document.createElement("div"));
    flex.appendChild(document.createElement("div"));

    // append to the DOM (needed to obtain scrollHeight)
    document.body.appendChild(flex);
    var isSupported = flex.scrollHeight === 1; // flex container should be 1px high from the row-gap
    flex.parentNode.removeChild(flex);

    return isSupported;
}

/**
 * Redirect to the upgrade.php page if browser is not supported
 * No IE versions are supported: `"ActiveXObject" in window` ought to detect IE
 * @note - !checkFlexGap() has been pulled out as an issue in Safari 17.2 looks to have started failing this test
 */
window.addEventListener("DOMContentLoaded", (event) => {
    if ("ActiveXObject" in window) {
        window.location.href = "/upgrade.php";
    }
});
