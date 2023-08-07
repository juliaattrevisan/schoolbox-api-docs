/**
 * As much as possible, allow features no longer available in CKEditor 5 to
 * at least still persist and beave the same way in display view.
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";

export default class PanelTweaks extends Plugin {
    init() {
        this.updatePanelPositions();
    }

    /**
     * Some items in the toolbar are panels that display the items within horizontally.
     * These panels can be hidden underneath the notifications menu when its open.
     * So we should align them so the right edge of the panel is aligned with the right edge of the button.
     * Due to the current setup of the toolbar, we can keep this positioning regardless of whether the
     * notifications panel is pinned or not.
     * 		         [ Button ]
     *		+-----------------+
     *		|      Panel      |
     *		+-----------------+
     */
    updatePanelPositions() {
        const buttonTitles = ["Insert media", "Emoji", "Insert table", "Other"];
        const toolbarItems = this.editor.ui.view.toolbar.items._items;
        toolbarItems.forEach((item) => {
            if (buttonTitles.includes(item.buttonView?.label)) {
                // refer to https://ckeditor.com/docs/ckeditor5/latest/api/module_ui_dropdown_dropdownview-DropdownView.html#static-member-defaultPanelPositions
                item.panelPosition = "sw";
            }
        });
    }
}
