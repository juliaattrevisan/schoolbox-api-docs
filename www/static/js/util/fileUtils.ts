/*
 * Any utils that manipulate filenames
 *
 */

/**
 * check whether a file name matches specific extension
 * @param filename '593042579804375.webm'
 * @param extension 'mp4'
 * @returns depending of if matches
 */
export function isOfFileType(filename: string, extension: string): boolean {
    const filenameExtension = filename.split(".").pop();
    return filenameExtension === extension;
}

/**
 * check whether a file is of a certain mime type
 * @param mimeType 'audio/webm;codecs=opus'
 * @param mimeTypeMatch 'video/webm'
 * @returns depending of if matches
 */
export function isOfMimeType(mimeType: string, mimeTypeMatch: string): boolean {
    return mimeType.includes(mimeTypeMatch);
}

/**
 * convert the filename to use the extension instead of whatever it was
 * @param filename '593042579804375.webm'
 * @param extension '.mp4'
 * @returns '593042579804375.mp4'
 */
export function convertFilenameExtension(filename: string, extension: string): string {
    const filenameWithoutExtension = filename.replace(/\.[^/.]+$/, "");
    return filenameWithoutExtension + extension;
}

/**
 * extract the media type from a Data URL
 * @param dataURL ''data:image/png;base64,abcdefghijklm...' - https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs
 * @returns 'image/png'
 */
export function getMediaTypeFromDataURL(dataURL: string): string {
    return dataURL.substring(dataURL.indexOf(":") + 1, dataURL.indexOf(";"));
}

/**
 * convert Data URL to Blob - https://gist.github.com/wuchengwei/b7e1820d39445f431aeaa9c786753d8e
 * @param dataURL ''data:image/png;base64,abcdefghijklm...' - https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs
 * @returns blob
 */
export function dataURLtoBlob(dataURL: string): Blob {
    const arr = dataURL.split(",");
    const mime = arr[0].match(/:(.*?);/)![1];
    const bstr = atob(arr[1]);

    let n = bstr.length;
    const u8arr = new Uint8Array(n);
    while (n > 0) {
        n -= 1;
        u8arr[n] = bstr.charCodeAt(n);
    }

    return new Blob([u8arr], { type: mime });
}

/**
 * convert Blob to Data URL - https://gist.github.com/wuchengwei/b7e1820d39445f431aeaa9c786753d8e
 * @param blob - the blob to convert
 * @returns dataURL
 */
export async function blobToDataURL(blob: Blob): Promise<string> {
    return new Promise((resolve) => {
        const fileReader = new FileReader();
        fileReader.onload = (): void => {
            resolve(fileReader.result as string);
        };
        fileReader.readAsDataURL(blob);
    });
}

/**
 * Format bytes as human-readable text.
 * Modified from https://stackoverflow.com/questions/10420352/converting-file-size-in-bytes-to-human-readable-string/14919494
 * @param $bytes - Number of bytes.
 * @param dp - Number of decimal places to display.
 * @param si - True to use metric (SI) units, aka powers of 1000.
 *                               False to use binary (IEC), aka powers of 1024.
 * @returns - Formatted string.
 */
export function humanFileSize($bytes: number | string, dp: number = 2, si: boolean = false): string {
    const thresh = si ? 1000 : 1024;

    // Return input if it is not numeric
    if (Number.isNaN(Number($bytes))) {
        return $bytes.toString();
    }

    let bytes = parseFloat($bytes.toString());

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
            u += 1;
        } while (Math.round(Math.abs(bytes) * r) / r >= thresh && u < units.length - 1);

        selectedUnit = units[u];
    }

    return `${parseFloat(bytes.toFixed(dp)).toString()} ${selectedUnit}`;
}

/**
 * Returns the HTML of a given URL
 * @param url - address for the HTML to fetch
 * @returns the resulting HTML string fragment
 */
export async function fetchHtmlAsText(url: string): Promise<string> {
    return (await fetch(url)).text();
}

/**
 * Strip file extension
 * @param filename - the filename to process
 * @returns the filename without its extension
 */
export function getWithoutExtension(filename: string): string {
    const lastDotIndex = filename.lastIndexOf(".");
    if (lastDotIndex === -1) {
        // Filename has no extension
        return filename;
    }
    return filename.slice(0, lastDotIndex);
}
