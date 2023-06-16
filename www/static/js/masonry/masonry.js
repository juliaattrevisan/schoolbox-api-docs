import { setMasonryConfig, masonryConfig } from "./masonryConfig";
import { initPostToMyLinks } from "./postToMyLinks";
import { initExistingComponents } from "./existingComponents/existingComponents";
import { initToggleLockComponents } from "./existingComponents/toggleLockComponents";
import { initEditColumns } from "./editColumns/editColumns";
import { initNewComponents } from "./newComponents/newComponents";
import { initUpdatePlaceHolders } from "./updatePlaceHolders";

document.addEventListener("MASONRY__CREATE", (e) => {
    setMasonryConfig(e.detail);

    console.log("MASONRY CONFIG", masonryConfig, masonryConfig.postToMyLinks)

    if (masonryConfig.postToMyLinks) { initPostToMyLinks(); }

    initExistingComponents();

    if (masonryConfig.settings.allowEditing) {
        if (masonryConfig.toggleLockComponents) { initToggleLockComponents(); }
        initEditColumns();
        initNewComponents();
        initUpdatePlaceHolders();
    }
});
