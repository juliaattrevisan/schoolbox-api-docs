import Plugin from "@ckeditor/ckeditor5-core/src/plugin";
import { toWidget } from "@ckeditor/ckeditor5-widget/src/utils";
import Widget from "@ckeditor/ckeditor5-widget/src/widget";
import InsertAddVideoCommand from "./insertAddVideoCommand";

export default class AddVideoEditing extends Plugin {
    static get requires() {
        return [Widget];
    }

    init() {
        this._defineSchema();
        this._defineConverters();
        this.editor.commands.add("insertAddVideo", new InsertAddVideoCommand(this.editor));
    }

    _defineSchema() {
        const schema = this.editor.model.schema;

        schema.register("sbVideo", {
            isObject: true, // Behaves like a self-contained object (e.g. an image).
            allowWhere: "$block", // Allow in places where other blocks are allowed (e.g. directly in the root).
            allowAttributes: [
                // all the attributes allowed on the element
                "data-schoolboxresource-type",
                "data-schoolboxresource-filesystem",
                "data-schoolboxresource-thumbnail",
                "data-schoolboxresource-source",
                "data-schoolboxresource-width",
                "data-schoolboxresource-height",
                "data-schoolboxresource-fullwidth",
            ],
        });
    }

    _defineConverters() {
        const editor = this.editor;

        editor.conversion.for("upcast").elementToElement({
            view: {
                name: "img",
                attributes: [
                    "data-schoolboxresource-type",
                    "data-schoolboxresource-filesystem",
                    "data-schoolboxresource-thumbnail",
                    "data-schoolboxresource-source",
                    "data-schoolboxresource-width",
                    "data-schoolboxresource-height",
                    "data-schoolboxresource-fullwidth",
                ],
            },
            model: (viewElement, { writer }) => {
                //const externalUrl = viewElement.getAttribute( 'data-resource-url' );

                return writer.createElement("sbVideo", {
                    // 'data-resource-url': externalUrl
                    "data-schoolboxresource-type": "video",
                    "data-schoolboxresource-filesystem": "store",
                    "data-schoolboxresource-thumbnail":
                        "/cms/videoThumb.php?size=poster&amp;id=55508bc98a00f615dbe9bd4c84a253ba4238b021&amp;type=storage",
                    "data-schoolboxresource-source":
                        "/cms/sendVideo.php?id=55508bc98a00f615dbe9bd4c84a253ba4238b021&amp;type=store",
                    "data-schoolboxresource-width": "512",
                    "data-schoolboxresource-height": "288",
                    "data-schoolboxresource-fullwidth": "false",
                });
            },
        });

        editor.conversion.for("dataDowncast").elementToElement({
            // editor.conversion.for("dataDowncast").elementToElement({
            model: "sbVideo",
            view: (modelElement, { writer }) => {
                return writer.createEmptyElement("img", {
                    // "data-resource-url": modelElement.getAttribute("data-resource-url"),
                    "data-schoolboxresource-type": "video",
                    "data-schoolboxresource-filesystem": "store",
                    "data-schoolboxresource-thumbnail":
                        "/cms/videoThumb.php?size=poster&amp;id=55508bc98a00f615dbe9bd4c84a253ba4238b021&amp;type=storage",
                    "data-schoolboxresource-source":
                        "/cms/sendVideo.php?id=55508bc98a00f615dbe9bd4c84a253ba4238b021&amp;type=store",
                    "data-schoolboxresource-width": "512",
                    "data-schoolboxresource-height": "288",
                    "data-schoolboxresource-fullwidth": "false",
                });
            },
        });

        editor.conversion.for("editingDowncast").elementToElement({
            model: "sbVideo",
            view: (modelElement, { writer }) => {
                const externalDataPreviewElement = writer.createRawElement("img", null, function (domElement) {
                    // For now show some static text
                    domElement.textContent = "Data placeholder";
                });

                const externalWidgetContainer = writer.createContainerElement("img", null, externalDataPreviewElement);

                return toWidget(externalWidgetContainer, writer, {
                    label: "External widget",
                });
            },
        });
    }
}
