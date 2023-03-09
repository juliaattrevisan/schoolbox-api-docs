import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import ButtonView from "@ckeditor/ckeditor5-ui/src/button/buttonview";

export default class ResourceLink extends Plugin {
    init() {
        const editor = this.editor;
        this.button = this.createButton();
        const contextualBalloon = editor.plugins.get("ContextualBalloon");
        contextualBalloon.on("change:visibleView", (eventInfo, name, value, oldValue) => {
            if (value.element.classList.contains("ck-link-form")) {
                contextualBalloon.off("change:visibleView");
                this.linkFormView = value;
                this.button.render();
                // Register the button under the link form view, it will handle its destruction.
                this.linkFormView.registerChild(this.button);
                // Inject the element into DOM.
                this.linkFormView.element.insertBefore(this.button.element, this.linkFormView.saveButtonView.element);
            }
        });
    }

    createButton() {
        const editor = this.editor;
        const button = new ButtonView(this.locale);
        const linkCommand = editor.commands.get("link");

        button.set({
            label: "Internal link",
            withText: true,
            tooltip: true,
        });

        // Probably this button should be also disabled when the link command is disabled.
        // Try setting editor.isReadOnly = true to see it in action.
        button.bind("isEnabled").to(linkCommand);
        button.on("execute", () => {
            // Do something (like open the popup), then update the link URL field's value.
            // The line below will be probably executed inside some callback.
            this.linkFormView.urlInputView.fieldView.element.value = "Some URL";
        });

        return button;
    }
}
