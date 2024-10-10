/**
 * Any UI related tweaks that need to be made to the editor to fit
 * within the SB context should be added here.
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import type { ClassicEditor } from "@ckeditor/ckeditor5-editor-classic";
import { getOptimalPosition } from "@ckeditor/ckeditor5-utils";
import { EditorEvent } from "@js/editor/event/editorEvent";
import type { DropdownView } from "@ckeditor/ckeditor5-ui";
import type ViewCollection from "@ckeditor/ckeditor5-ui/src/viewcollection";
import type { Position } from "@ckeditor/ckeditor5-utils/src/dom/position";
import type View from "@ckeditor/ckeditor5-ui/src/view";
import type { PanelPosition } from "@ckeditor/ckeditor5-ui/src/dropdown/dropdownpanelview";

export default class PanelTweaks extends Plugin {
    /**
     * We need to ensure everything has been set up before we can start modifying the panels
     */
    init(): void {
        (this.editor as ClassicEditor).sourceElement?.addEventListener(EditorEvent.EDITOR__CREATED, () => {
            this.updateDropdownPositions();
            this.checkForModal();
        });
    }

    /**
     * Be default the <body> element is used to check if the entire dropdown is visible when open.
     * This is problematic when we have either the side menu or the notifications panel open as
     * part of the dropdown may appear behind them. To resolve we pass in the #content element
     * into the getOptimalPosition method used.
     */
    updateDropdownPositions(): void {
        const toolbarItems = (this.editor as ClassicEditor).ui.view.toolbar.items as ViewCollection<DropdownView>;
        const dropDownItems = toolbarItems.filter((item) => item.panelPosition !== null); // get only those toolbar items that are dropdowns
        dropDownItems.forEach((item) => {
            this.updateDropdownPosition(item);
        });
    }

    /**
     * When the dropdown is opened, we need to update the position of the dropdown.
     * As these listeners are assigned after the toolbar has been created, they will be executed
     * after the listener assigned within the toolbar item.
     * We just replicate the logic used in https://ckeditor.com/docs/ckeditor5/latest/api/module_ui_dropdown_dropdownview-DropdownView.html#function-render
     * but add .limiter to the getOptimalPosition util method.
     * @param item - the dropdown item
     */
    updateDropdownPosition(item: DropdownView): void {
        item.on("change:isOpen", (eventInfo, name, isOpen) => {
            if (!isOpen) {
                return;
            }
            /**
             * We also need to account for dropdowns within dropdowns.
             */
            if (item.toolbarView) {
                const innerItems = item.toolbarView.itemsView.children as ViewCollection<DropdownView>;
                const innerDropDownItems = innerItems.filter(($item) => $item.panelPosition !== null); // get only those toolbar items that are dropdowns
                innerDropDownItems.forEach(($item) => {
                    this.updateDropdownPosition($item);
                });
            }
            const optimalPosition = getOptimalPosition({
                element: item.panelView.element as HTMLElement,
                target: item.buttonView.element as HTMLElement,
                fitInViewport: true,
                limiter: document.getElementById("content"),
                // @ts-expect-error - Acessing the private property is intentional here, for now
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                positions: item._panelPositions,
            }) as Position;
            item.panelView.position = optimalPosition.name as PanelPosition;
        });
    }

    /**
     * if the editor is inside a reveal modal, we need to make sure
     * the balloon is on top of the modal, otherwise it will be hidden underneath
     */
    checkForModal(): void {
        const zIndex = "var(--sbx-depth-modal-ui-overlay)";
        if ((this.editor as ClassicEditor).sourceElement?.closest("[data-reveal]")) {
            const contextualBalloon = this.editor.plugins.get("ContextualBalloon");
            // fired when the balloon is shown or hidden by any UI
            contextualBalloon.on("change:visibleView", (eventInfo, name, value: null | View<HTMLElement>) => {
                if (value === null) return;

                const balloonElement = value.element?.parentElement?.parentElement?.parentElement ?? null;
                if (balloonElement && balloonElement.style.zIndex !== zIndex) {
                    balloonElement.style.zIndex = zIndex;
                    // we also need to add the editor id to the balloon so we can potentially access it later
                    balloonElement.setAttribute("data-balloon-editor-id", this.editor.id);
                }
            });
        }
    }
}
