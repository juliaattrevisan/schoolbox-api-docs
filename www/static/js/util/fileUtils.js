/*
 * Any utils that manipulate filenames
 *
 */

/**
 * check whether a file name matches specific extension
 *
 * @param { String } '593042579804375.webm'
 * @param { String } 'mp4'
 *
 * @returns { Boolean } depending of if matches
 */
export const isOfFileType = (filename, extension) => {
    const filenameExtension = filename.split(".").pop();
    return filenameExtension === extension;
};

/**
 * check whether a file is of a certain mime type
 *
 * @param { String } 'audio/webm;codecs=opus'
 * @param { String } 'video/webm'
 *
 * @returns { Boolean } depending of if matches
 */
export const isOfMimeType = (mimeType, mimeTypeMatch) => {
    return mimeType.includes(mimeTypeMatch);
};

/**
 * convert the filename to use the extension instead of whatever it was
 *
 * @param { String } '593042579804375.webm'
 * @param { String } '.mp4'
 *
 * @returns { String } '593042579804375.mp4'
 */
export const convertFilenameExtension = (filname, extension) => {
    const filenameWithoutExtension = filname.replace(/\.[^/.]+$/, "");
    return filenameWithoutExtension + extension;
};

/**
 * extract the media type from a Data URL
 *
 * @param { String } ''data:image/png;base64,abcdefghijklm...' - https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs
 *
 * @returns { String } 'image/png'
 */
export const getMediaTypeFromDataURL = (dataURL) => {
    return dataURL.substring(dataURL.indexOf(":") + 1, dataURL.indexOf(";"));
};

/**
 * convert Data URL to Blob - https://gist.github.com/wuchengwei/b7e1820d39445f431aeaa9c786753d8e
 *
 * @param { String } ''data:image/png;base64,abcdefghijklm...' - https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs
 *
 * @returns { Blob }
 */
export const dataURLtoBlob = (dataURL) => {
    var arr = dataURL.split(","),
        mime = arr[0].match(/:(.*?);/)[1],
        bstr = atob(arr[1]),
        n = bstr.length,
        u8arr = new Uint8Array(n);
    while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
    }
    return new Blob([u8arr], { type: mime });
};

/**
 * convert Blob to Data URL - https://gist.github.com/wuchengwei/b7e1820d39445f431aeaa9c786753d8e
 *
 * @param { Blob }
 *
 * @returns { DataURL }
 */
export const blobToDataURL = async (blob) => {
    return new Promise((resolve, reject) => {
        const fileReader = new FileReader();
        fileReader.onload = () => {
            resolve(fileReader.result);
        };
        fileReader.readAsDataURL(blob);
    });
};

/**
 * Format bytes as human-readable text.
 * Modified from https://stackoverflow.com/questions/10420352/converting-file-size-in-bytes-to-human-readable-string/14919494
 *
 * @param { Number } bytes - Number of bytes.
 * @param { Number } dp - Number of decimal places to display.
 * @param { Boolean } si - True to use metric (SI) units, aka powers of 1000.
 *                               False to use binary (IEC), aka powers of 1024.
 * @returns { String } - Formatted string.
 */
export const humanFileSize = (bytes, dp = 2, si = false) => {
    const thresh = si ? 1000 : 1024;

    // Return input if it is not numeric
    if (isNaN(bytes)) {
        return bytes;
    }

    bytes = parseFloat(bytes);

    let selectedUnit = "B";

    if (Math.abs(bytes) >= thresh) {
        // Although usually when using factors of 1024 we would show "KiB", "MiB", etc.,
        // SchoolBox uses "kB", "MB", etc. for consistency with the rest of the site,
        // hence, we always use SI unit prefixes regardless of the value of si.
        const units = ["kB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];
        let u = -1;
        const r = 10 ** dp;

        do {
            bytes /= thresh;
            ++u;
        } while (Math.round(Math.abs(bytes) * r) / r >= thresh && u < units.length - 1);

        selectedUnit = units[u];
    }

    return parseFloat(bytes.toFixed(dp)).toString() + " " + selectedUnit;
};

/**
 * Returns the HTML of a given URL
 *
 * @param { string } url - address for the HTML to fetch
 * @return { string } the resulting HTML string fragment
 */
export async function fetchHtmlAsText(url) {
    return await (await fetch(url)).text();
}

/**
 * Strip file extension
 *
 * @param { string } url - address for the HTML to fetch
 * @return { string } the resulting HTML string fragment
 */
export function getWithoutExtension(filename) {
    let lastDotIndex = filename.lastIndexOf(".");
    if (lastDotIndex === -1) {
        // Filename has no extension
        return filename;
    } else {
        return filename.slice(0, lastDotIndex);
    }
}
