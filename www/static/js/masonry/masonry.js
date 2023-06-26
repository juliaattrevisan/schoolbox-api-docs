/**
 * This file is the entry point for masonry used on homepages, eportfolios, and the admin template pages.
 *
 * It handles adding, removing, and updating components, as well as sorting components, editing columns or other page settings, and various other things.
 * Masonry can be initialised by dispatching a MASONRY__CREATE event on the document with settings in the detail.
 * See masonryConfig.js for the settings that can be passed in.
 */

import { setMasonryConfig, masonryConfig } from "./masonryConfig";
import { initPostToMyLinks } from "./postToMyLinks";
import { initExistingComponents } from "./existingComponents/existingComponents";
import { initToggleLockComponents } from "./existingComponents/toggleLockComponents";
import { initEditColumns } from "./editColumns/editColumns";
import { initNewComponents } from "./newComponents/newComponents";
import { updateAllComponentCounts } from "./newComponents/updateNewComponentButtons";
import { initUpdatePlaceHolders } from "./updatePlaceHolders";
import { merge } from "lodash";

document.addEventListener("MASONRY__CREATE", (e) => {
    setMasonryConfig(e.detail);

    if (masonryConfig.postToMyLinks) { initPostToMyLinks(); }

    initExistingComponents();

    if (masonryConfig.settings.allowEditing) {
        if (masonryConfig.toggleLockComponents) { initToggleLockComponents(); }
        if (masonryConfig.editColumns) { initEditColumns() };
        initNewComponents();
        initUpdatePlaceHolders();

        if (masonryConfig.refreshPaletteCountsOnLoad) { updateAllComponentCounts(); }
    }
});

document.addEventListener("MASONRY__UPDATE__CONFIG", (e) => {
    setMasonryConfig(merge(masonryConfig, e.detail));
});
