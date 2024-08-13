/**
 * This contains code to display and tweak the Learnosity authoring app to our needs (displayed when modifying a quiz contents)
 * It was originally written directly in `library/Schoolbox/Learning/Learnosity/templates/content.twig`
 * but has been moved into a module so we can use the image compression on upload extension.
 */

// Ignore this file for TypeScript and ESLint checks for now (SA-18139)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
// @ts-nocheck

import { LT } from "@caspingus/lt/src/authoring/core";
import * as imageUploader from "@caspingus/lt/src/authoring/extensions/ui/imageUploader/index";

/**
 * Adjust the header of the question items list to be fixed when scrolling down
 * Prevents the "Add items" button from being lost when scrolling down
 */
function adjustItemsListHeader() {
    const questionItemsListHeader = document.querySelector(
        "ul.lrn-list-view.lrn-list-unstyled.lrn-list-divider.lrn-author-ui-no-preview",
    );
    const questionItemsList = document.querySelector(
        "ul.lrn-list-view.lrn-list-unstyled.lrn-list-divider:not(.lrn-author-ui-no-preview)",
    );

    if (questionItemsListHeader && questionItemsList) {
        const questionItemsListHeaderRect = questionItemsListHeader.getBoundingClientRect();
        const questionItemsListRect = questionItemsList.getBoundingClientRect();

        if (questionItemsListRect.top < questionItemsListHeaderRect.height) {
            questionItemsListHeader.style.position = "fixed";
            questionItemsListHeader.style.top = "0px";
            questionItemsListHeader.style.width = questionItemsListRect.width + "px";
            questionItemsListHeader.style.zIndex = "var(--sbx-depth-ui-overlay)";
        } else {
            questionItemsListHeader.style.position = "";
            questionItemsListHeader.style.top = "";
            questionItemsListHeader.style.width = "";
            questionItemsListHeader.style.zIndex = "var(--sbx-depth-default)";
        }
    }
}
window.addEventListener("scroll", adjustItemsListHeader);

// Create a MutationObserver to watch for changes in the quiz container
const lrnObserver = new MutationObserver((mutationsList) => {
    mutationsList.forEach((mutation) => {
        if (mutation.type === "childList" && mutation.addedNodes.length > 0) {
            mutation.addedNodes.forEach((node) => {
                // Process any new oembeds
                if (node.nodeType === Node.ELEMENT_NODE) {
                    node.querySelectorAll(
                        "oembed[url]:not(:has(.iframely-embed)), div[data-oembed-url]:not(:has(.iframely-embed))",
                    ).forEach((oEmbedEl) => {
                        // Ensure not inside a ckeditor
                        if (oEmbedEl.closest('[contenteditable="true"]') !== null) {
                            return;
                        }
                        window.iframely.load(
                            oEmbedEl,
                            oEmbedEl.getAttribute("data-oembed-url") || oEmbedEl.getAttribute("url"),
                        );
                    });
                }

                // Process any new SB media containers
                if (node.nodeType === Node.ELEMENT_NODE) {
                    node.querySelectorAll("[data-schoolboxresource-source]").forEach((sbMediaEl) => {
                        // If the next element is a schoolbox media container, skip it
                        if (
                            sbMediaEl.nextElementSibling &&
                            sbMediaEl.nextElementSibling.matches("[data-schoolboxmedia-replacement-container]")
                        ) {
                            return;
                        }
                        // Ensure not inside a ckeditor
                        if (sbMediaEl.closest('[contenteditable="true"]') !== null) {
                            return;
                        }

                        // Wait an arbitrary amount of time so the content is loaded to avoid the new player ending up with 0 width/height
                        setTimeout(() => {
                            $.fn.schoolboxResource_ProxyReplacement(sbMediaEl.parentElement);
                        }, 1000);
                    });
                }
            });
        }
    });
});

// Start observing changes in the quiz container's subtree
lrnObserver.observe(document.getElementById("quiz-container"), { subtree: true, childList: true });

/**
 * To add tags to the activity and also add browser state
 */
const authorAppCallback = {
    readyListener: function () {
        /**
         * Hide this random search thing that's broken by design
         * The Search for Question Types can find hidden/deprecated items intentionally
         * Also hidden via css for surety
         */
        authorApp.on("widgetedit:editor:ready", function (event) {
            document.querySelector(".lrn-qe-tiles-nav [data-lrn-qe-tiles-search-outlet]")?.remove();
        });

        /**
         * Force tagging to occur immediately
         * tags_on_create requires you to hit the save button or authorApp.saveActivity()
         * in order to spawn tags. Using authorApp.setActivityTags() allows you have tags
         * spawned immediately upon authorApp rendering
         */
        authorApp.on("render:activity", function (event) {
            authorApp.setActivityTags(window.sbProps.authorApp.activityTags);
        });

        /**
         * When the items list is rendered, including after every search or after sorting
         * Need to re-apply the fixed header, as the items list is recreated and wipes our changes
         */
        authorApp.on("render:itemlist", function () {
            adjustItemsListHeader();
        });

        /**
         * When an item is added through the items list, save the activity. This avoids some confusion
         * where the user is given a "saved" message but it only added the items, not saved the activity
         */
        authorApp.on("activityedit:items:added", function () {
            authorApp.saveActivity();
        });

        /**
         * On any "saveable" change that occurs on the main modify page, save the activity
         * e.g. removing items or changing the order of items
         */
        authorApp.on("activityedit:saveable:changed", function (event) {
            if (authorApp.getLocation().route === "activities/:reference") {
                authorApp.saveActivity();
            }
        });

        if (window.sbProps.authorApp.sitePlan === "elite") {
            authorApp.on("widgetedit:editor:ready", function (event) {
                /* Artificially wait for their dom
                 */
                setTimeout(function () {
                    document
                        .querySelector('[data-lrn-qe-template-reference="734ea0be-309b-4ff8-b004-3abd70094efd"]')
                        ?.closest("li")
                        .remove();
                    document
                        .querySelector('[data-lrn-qe-template-reference="25368800-1ebe-4f2c-b220-6fe90a44883b"]')
                        ?.closest("li")
                        .remove();
                }, 100);
            });
        }

        /**
         * Push app location changes to browser history, so we can use the browser back/forward
         */
        authorApp.on("navigate", function (event) {
            window.location.hash = `#${event.data.location}`;
        });

        /**
         * Navigate to the correct part of the app based on the hash
         */
        function navigateAuthorApp() {
            const hash = window.location.hash.replace(/^#/, ""); // strip the # from the hash
            const jsonMatch = hash.match(/\{(.|\n)*\}/);
            // if the hash contains json, we need to encode it first before passing it to the app
            if (jsonMatch) {
                const prefix = hash.substring(0, jsonMatch.index);
                const jsonString = decodeURIComponent(jsonMatch[0]);
                const modifiedHash = `${prefix}${encodeURIComponent(jsonString)}`;
                authorApp.navigate(modifiedHash);
            } else {
                authorApp.navigate(hash);
            }
        }
        navigateAuthorApp();

        /**
         * Listen for hash changes so we can navigate to the correct part of the app
         */
        window.onhashchange = function () {
            // If the hash is empty, go back to the previous page, as the first hash is injected on quiz load
            if (window.location.hash === "") {
                history.back();
            } else {
                navigateAuthorApp();
            }
        };

        LT.init(authorApp); // Set up LT with the Author API application instance variable

        // Put individual extensions in the LT object if that makes your life easier
        LT.extensions = {
            imageUploader,
        };

        // Run the image uploader extension
        LT.extensions.imageUploader.run(
            window.sbProps.authorApp.initOptions.security,
            window.sbProps.authorApp.initOptions.request,
        );
    },
    /* Log errors to console and to screen for users
     */
    errorListener: function (error) {
        let errorElement = document.createElement("div");
        errorElement.classList.add("alert-box", "warning");
        errorElement.innerHTML = "<p>There was a problem loading this quiz.</p>";
        errorElement.innerHTML += "<p>" + error + "</p>";

        document.getElementById("learnosity-author").replaceWith(errorElement);
        document.getElementById("quiz-container").classList.remove("island");
        document.getElementById("quiz-container").classList.add("columns");

        console.log(error);
    },
};

const authorApp = window.LearnosityAuthor.init(window.sbProps.authorApp.initOptions, authorAppCallback);
