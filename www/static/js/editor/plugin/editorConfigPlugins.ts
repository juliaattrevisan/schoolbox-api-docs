import { getTablePlugins } from "@js/editor/plugin/tablePlugins";
import { getTokensPlugins } from "@js/editor/plugin/tokensPlugins";
import { getEmojiPlugins } from "@js/editor/plugin/emojiPlugins";
import {
    Essentials,
    Bold,
    Italic,
    Underline,
    Strikethrough,
    Link,
    List,
    BlockQuote,
    GeneralHtmlSupport,
    PasteFromOffice,
    Notification,
    ImageInline,
    ImageBlock,
    ImageToolbar,
    ImageStyle,
    ImageResize,
    ImageUpload,
    LinkImage,
    Table,
    TableToolbar,
    TableProperties,
    TableCellProperties,
    TableColumnResize,
    Heading,
    HeadingButtonsUI,
    Paragraph,
    ParagraphButtonUI,
    Style,
    Subscript,
    Superscript,
    Font,
    RemoveFormat,
    MediaEmbed,
    HorizontalLine,
    Alignment,
    FindAndReplace,
    WordCount,
    Fullscreen,
    Bookmark,
    type EditorConfig,
} from "ckeditor5";
import {
    PasteFromOfficeEnhanced,
    AIAssistant,
    OpenAITextAdapter,
    SourceEditingEnhanced,
    Template,
} from "ckeditor5-premium-features";
import UploadAdapterPlugin from "@js/editor/plugin/uploadAdapter/UploadAdapterPlugin";
import { EditorToolbar, type SbxEditorConfig } from "@js/editor/types/editorTypes";
import Eqneditor5 from "@js/editor/plugin/eqnEditor/eqneditor5";
import Resource from "@js/editor/plugin/resource/Resource";
import UITweaks from "@js/editor/plugin/ui/UITweaks";
import Legacy from "@js/editor/plugin/legacy/Legacy";
import FullWidth from "@js/editor/plugin/fullWidth/FullWidth";
import TableUpdates from "@js/editor/plugin/tableUpdates/TableUpdates";
import { getSpecialCharactersPlugins } from "./specialCharactersPlugins";

/**
 * Return the default configuration
 * @param config - the default config
 * @returns - refer to https://ckeditor.com/docs/ckeditor5/latest/api/module_core_editor_editorconfig-EditorConfig.html
 */
export function getPlugins(config: SbxEditorConfig): EditorConfig & { sbConfig: SbxEditorConfig } {
    return {
        plugins: [
            // list all the plugins we'll use in the default editor
            Essentials,
            Bold,
            Italic,
            Underline,
            Strikethrough,
            Link,
            List,
            BlockQuote,
            GeneralHtmlSupport,
            Legacy,
            Notification,
            PasteFromOffice,
            PasteFromOfficeEnhanced,
            ImageInline,
            ImageBlock,
            ImageToolbar,
            ImageStyle,
            ImageResize,
            ImageUpload,
            LinkImage,
            FullWidth,
            Table,
            TableToolbar,
            TableProperties,
            TableCellProperties,
            TableColumnResize,
            TableUpdates,
            ...getEmojiPlugins(),
            ...getTokensPlugins(config),
            ...getSpecialCharactersPlugins(),
            Heading,
            Paragraph,
            HeadingButtonsUI,
            ParagraphButtonUI,
            Style,
            Subscript,
            Superscript,
            Font,
            RemoveFormat,
            MediaEmbed,
            HorizontalLine,
            Alignment,
            FindAndReplace,
            SourceEditingEnhanced,
            WordCount,
            Eqneditor5,
            Resource,
            UITweaks,
            AIAssistant,
            OpenAITextAdapter,
            Fullscreen,
            Bookmark,
            Template,
        ],
        extraPlugins: config.toolbar !== EditorToolbar.SIMPLE ? [UploadAdapterPlugin] : [],
    };
}
