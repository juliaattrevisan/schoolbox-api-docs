import type { GeneralHtmlSupportConfig } from "@ckeditor/ckeditor5-html-support";

/**
 * Add support for the listed HTML, so the editor doesn't automatically remove what's listed
 * https://ckeditor.com/docs/ckeditor5/latest/features/html/general-html-support.html
 *
 * Here's the list previously enabled in CKEditor4:
 * "img(full-width)[data-schoolboxresource-fullwidth,data-schoolboxresource-width,data-schoolboxresource-height,data-schoolboxresource-filesystem,data-schoolboxresource-type,data-schoolboxresource-thumbnail,data-schoolboxresource-source]{width,height,border,margin,margin-top,margin-bottom,margin-left,margin-right,display,float};" +
 * "iframe(full-width)[id,src,allowfullscreen,width,height,allow,frameBorder,webkitallowfullscreen,mozallowfullscreen]{width,height,margin,margin-top,margin-bottom,margin-left,margin-right,display,float,border}; " +
 * "div(*);" +
 * "object[id,name,width,height];" +
 * "param[name,value];" +
 * "embed[src,type,allowscriptaccess,allowfullscreen,wmode,width,height];" +
 * "a[id,href,target,data-portal,title];" +
 * "map[!name];" +
 * "area[!shape,!coords,!href,target,!alt];" +
 * "img[usemap];"
 *
 * So the below is a translation of this into the new format, to ensure legacy data is not lost.
 * Note that some of these would already be enabled in CKEditor5, but we're explicitly enabling them here to match exactly the previous settings.
 * @returns - the html support settings
 */
export function getHTMLSupportSettings(): { htmlSupport: GeneralHtmlSupportConfig } {
    return {
        htmlSupport: {
            disallow: [
                {
                    name: "div",
                    styles: {
                        position: true,
                    },
                },
            ],
            allow: [
                // "img(full-width)[data-schoolboxresource-fullwidth,data-schoolboxresource-width,data-schoolboxresource-height,data-schoolboxresource-filesystem,data-schoolboxresource-type,data-schoolboxresource-thumbnail,data-schoolboxresource-source]{width,height,border,margin,margin-top,margin-bottom,margin-left,margin-right,display,float};"
                // "img[usemap];"
                {
                    name: "img",
                    classes: ["full-width"],
                    attributes: {
                        "data-schoolboxresource-fullwidth": true,
                        "data-schoolboxresource-width": true,
                        "data-schoolboxresource-height": true,
                        "data-schoolboxresource-filesystem": true,
                        "data-schoolboxresource-type": true,
                        "data-schoolboxresource-thumbnail": true,
                        "data-schoolboxresource-source": true,
                        srcset: true,
                        usemap: true,
                    },
                    styles: {
                        width: true,
                        height: true,
                        border: true,
                        margin: true,
                        "margin-top": true,
                        "margin-bottom": true,
                        "margin-left": true,
                        "margin-right": true,
                        display: true,
                        float: true,
                    },
                },
                // "iframe(full-width)[id,src,allowfullscreen,width,height,allow,frameBorder,webkitallowfullscreen,mozallowfullscreen]{width,height,margin,margin-top,margin-bottom,margin-left,margin-right,display,float,border,position,top,bottom,left,right};"
                {
                    name: "iframe",
                    classes: ["full-width"],
                    attributes: {
                        id: true,
                        src: true,
                        allowfullscreen: true,
                        width: true,
                        height: true,
                        allow: true,
                        frameBorder: true,
                        webkitallowfullscreen: true,
                        mozallowfullscreen: true,
                    },
                    styles: {
                        width: true,
                        height: true,
                        margin: true,
                        "margin-top": true,
                        "margin-bottom": true,
                        "margin-left": true,
                        "margin-right": true,
                        display: true,
                        float: true,
                        border: true,
                        top: true,
                        bottom: true,
                        left: true,
                        right: true,
                    },
                },
                // "Allow class on all div, p, a, h2, h3, h4, and span elements"
                {
                    name: /^(div|p|a|h2|h3|h4|span)$/,
                    classes: true,
                },
                // Allow all styles on div elements
                {
                    name: "div",
                    styles: true,
                },
                // "object[id,name,width,height];"
                {
                    name: "object",
                    attributes: {
                        id: true,
                        name: true,
                        width: true,
                        height: true,
                    },
                },
                // "param[name,value];"
                {
                    name: "param",
                    attributes: {
                        name: true,
                        value: true,
                    },
                },
                // "embed[src,type,allowscriptaccess,allowfullscreen,wmode,width,height];"
                {
                    name: "embed",
                    attributes: {
                        src: true,
                        type: true,
                        allowscriptaccess: true,
                        allowfullscreen: true,
                        wmode: true,
                        width: true,
                        height: true,
                    },
                },
                // "a[id,href,target,data-portal,title];"
                {
                    name: "a",
                    attributes: {
                        id: true,
                        href: true,
                        target: true,
                        "data-portal": true,
                        title: true,
                    },
                },
                // "map[!name];" - note the ! before name, which means it's required to be allowed, but I don't believe this is supported in CKEditor5
                {
                    name: "map",
                    attributes: {
                        name: true,
                    },
                },
                // "area[!shape,!coords,!href,target,!alt];"
                {
                    name: "area",
                    attributes: {
                        shape: true,
                        coords: true,
                        href: true,
                        target: true,
                        alt: true,
                    },
                },
                // "<code>"
                {
                    name: "code",
                },
                // "<tt>"
                {
                    name: "tt",
                },
                // "<pre>"
                {
                    name: "pre",
                },
                // "Allow id on all div, p, h2, h3, h4, h5 and span elements"
                {
                    name: /^(div|p|h2|h3|h4|h5|span)$/,
                    attributes: {
                        id: true,
                    },
                },
            ],
        },
    };
}
