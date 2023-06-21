import { setMasonryConfig, masonryConfig } from "./masonryConfig";
import { initPostToMyLinks } from "./postToMyLinks";
import { initExistingComponents } from "./existingComponents/existingComponents";
import { initToggleLockComponents } from "./existingComponents/toggleLockComponents";
import { initEditColumns } from "./editColumns/editColumns";
import { initNewComponents } from "./newComponents/newComponents";
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
    }
});

document.addEventListener("MASONRY__UPDATE__CONFIG", (e) => {
    setMasonryConfig(merge(masonryConfig, e.detail));
});
