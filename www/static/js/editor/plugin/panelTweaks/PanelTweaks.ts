/**
 * As much as possible, allow features no longer available in CKEditor 5 to
 * at least still persist and beave the same way in display view.
 */

import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import { getOptimalPosition } from "@ckeditor/ckeditor5-utils/";
import { EditorEvent } from "@js/editor/event/editorEvent";

export default class PanelTweaks extends Plugin {
    /**
     * We need to ensure everything has been set up before we can start modifying the panels
     */
    init() {
        this.editor.sourceElement.addEventListener(EditorEvent.EDITOR__CREATED, () => {
            this.updatePanelPositions();
        });
    }

    /**
     * Be default the <body> element is used to check if the entire dropown is visible when open.
     * This is problematic when we have either the side menu or the notifications panel open as
     * part of the dropdown may appear behind them. To resolve we pass in the #content element
     * into the getOptimalPosition method used.
     */
    updatePanelPositions() {
        const toolbarItems = this.editor.ui.view.toolbar.items;
        const dropDownItems = toolbarItems.filter((item) => item.panelPosition); // get only those toolbar items that are dropdowns
        dropDownItems.forEach((item) => {
            /**
             * When the dropdown is opened, we need to update the position of the dropdown.
             * As these listeners are assigned after the toolbar has been created, they will be executed
             * after the listener assigned within the toolbar item.
             */
            item.on("change:isOpen", (eventInfo, name, isOpen) => {
                if (!isOpen) {
                    return;
                }
                // refer to https://ckeditor.com/docs/ckeditor5/latest/api/module_utils_dom_position.html#function-getOptimalPosition for more info
                item.panelView.position = getOptimalPosition({
                    element: item.panelView.element!,
                    target: item.buttonView.element!,
                    fitInViewport: true,
                    limiter: document.getElementById("content"),
                    positions: item._panelPositions,
                }).name;
            });
        });
    }
}
