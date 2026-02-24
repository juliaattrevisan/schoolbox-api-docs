/**
 * This file is the entry point for masonry used on homepages, eportfolios, and the admin template pages.
 *
 * It handles adding, removing, and updating components, as well as sorting components, editing columns or other page settings, and various other things.
 * Masonry can be initialised by dispatching a MASONRY__CREATE event on the document with settings in the detail.
 * See masonryConfig.js for the settings that can be passed in.
 */

import { setMasonryConfig, getMasonryConfig } from "./masonryConfig";
import { initPostToMyLinks } from "./postToMyLinks";
import { initExistingComponents } from "./existingComponents/existingComponents";
import { initToggleLockComponents } from "./existingComponents/toggleLockComponents";
import { initEditColumns } from "./editColumns/editColumns";
import { initNewComponents } from "./newComponents/newComponents";
import { updateAllComponentCounts } from "./newComponents/updateNewComponentButtons";
import { initUpdatePlaceHolders } from "./updatePlaceHolders";
import { merge } from "es-toolkit";

document.addEventListener("MASONRY__CREATE", (e) => {
    setMasonryConfig(e.detail);

    if (getMasonryConfig().postToMyLinks) {
        initPostToMyLinks();
    }

    initExistingComponents();

    if (getMasonryConfig().settings.allowEditing) {
        if (getMasonryConfig().toggleLockComponents) {
            initToggleLockComponents();
        }
        if (getMasonryConfig().editColumns) {
            initEditColumns();
        }
        initNewComponents();
        initUpdatePlaceHolders();

        if (getMasonryConfig().settings.refreshPaletteCountsOnLoad) {
            updateAllComponentCounts();
        }
    }
});

document.addEventListener("MASONRY__UPDATE__CONFIG", (e) => {
    setMasonryConfig(merge(getMasonryConfig(), e.detail));
});
