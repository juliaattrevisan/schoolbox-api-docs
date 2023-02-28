import Plugin from "@ckeditor/ckeditor5-core/src/plugin";

export default class SchoolboxResourceEditing extends Plugin {
    init() {
        this.updateSchema();
        this.defineConverters();
    }

    updateSchema() {
        const schema = this.editor.model.schema;
        schema.extend("imageBlock", {
            allowAttributes: [
                "data-schoolboxresource-type",
                "data-schoolboxresource-filesystem",
                "data-schoolboxresource-thumbnail",
                "data-schoolboxresource-source",
                "data-schoolboxresource-width",
                "data-schoolboxresource-height",
                "data-schoolboxresource-fullwidth",
            ],
        });
        schema.extend("imageInline", {
            allowAttributes: [
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

    defineConverters() {
        const conversion = this.editor.conversion;

        conversion.for("downcast").attributeToAttribute({
            model: "data-schoolboxresource-type",
            view: "data-schoolboxresource-type",
        });
        conversion.for("upcast").attributeToAttribute({
            view: {
                name: "image",
                key: "data-schoolboxresource-type",
            },
            model: "data-schoolboxresource-type",
        });

        conversion.for("downcast").attributeToAttribute({
            model: "data-schoolboxresource-filesystem",
            view: "data-schoolboxresource-filesystem",
        });
        conversion.for("upcast").attributeToAttribute({
            view: {
                name: "image",
                key: "data-schoolboxresource-filesystem",
            },
            model: "data-schoolboxresource-filesystem",
        });

        conversion.for("downcast").attributeToAttribute({
            model: "data-schoolboxresource-thumbnail",
            view: "data-schoolboxresource-thumbnail",
        });
        conversion.for("upcast").attributeToAttribute({
            view: {
                name: "image",
                key: "data-schoolboxresource-thumbnail",
            },
            model: "data-schoolboxresource-thumbnail",
        });

        conversion.for("downcast").attributeToAttribute({
            model: "data-schoolboxresource-source",
            view: "data-schoolboxresource-source",
        });
        conversion.for("upcast").attributeToAttribute({
            view: {
                name: "image",
                key: "data-schoolboxresource-source",
            },
            model: "data-schoolboxresource-source",
        });

        conversion.for("downcast").attributeToAttribute({
            model: "data-schoolboxresource-width",
            view: "data-schoolboxresource-width",
        });
        conversion.for("upcast").attributeToAttribute({
            view: {
                name: "image",
                key: "data-schoolboxresource-width",
            },
            model: "data-schoolboxresource-width",
        });

        conversion.for("downcast").attributeToAttribute({
            model: "data-schoolboxresource-height",
            view: "data-schoolboxresource-height",
        });
        conversion.for("upcast").attributeToAttribute({
            view: {
                name: "image",
                key: "data-schoolboxresource-height",
            },
            model: "data-schoolboxresource-height",
        });

        conversion.for("downcast").attributeToAttribute({
            model: "data-schoolboxresource-fullwidth",
            view: "data-schoolboxresource-fullwidth",
        });
        conversion.for("upcast").attributeToAttribute({
            view: {
                name: "image",
                key: "data-schoolboxresource-fullwidth",
            },
            model: "data-schoolboxresource-fullwidth",
        });
    }
}
