import { setMasonryConfig, masonryConfig } from "./masonryConfig";
import { initPostToMyLinks } from "./postToMyLinks";
import { initExistingComponents } from "./existingComponents/existingComponents";
import { initToggleLockComponents } from "./existingComponents/toggleLockComponents";
import { initEditColumns } from "./editColumns/editColumns";
import { initNewComponents } from "./newComponents/newComponents";
import { initUpdatePlaceHolders } from "./updatePlaceHolders";

document.addEventListener("MASONRY__CREATE", (e) => {
    // setMasonryConfig(e.detail.config);

    setMasonryConfig({
        settings: {
            allowEditing: true, // Whether the user has full edit control, or is read-only
            existingComponentSelector: "[data-component]",
            updateUrl: "/homepage/configForm.php",
        },
    }); // TEMP

    initPostToMyLinks();
    initExistingComponents();
    if (masonryConfig.settings.allowEditing) {
        initToggleLockComponents();
        initEditColumns();
        initNewComponents();
        initUpdatePlaceHolders();
    }
});
