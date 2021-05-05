
/*
 * Any utils that manipulate filenames
 *
 */

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
}

/**
* extract the media type from a Data URL
* 
* @param { String } ''data:image/png;base64,abcdefghijklm...' - https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs
*
* @returns { String } 'image/png'
*/ 
export const getMediaTypeFromDataURL = (dataURL) => {
    return dataURL.substring(dataURL.indexOf(':') + 1, dataURL.indexOf(';'));
}

/**
* convert Data URL to Blob - https://gist.github.com/wuchengwei/b7e1820d39445f431aeaa9c786753d8e
* 
* @param { String } ''data:image/png;base64,abcdefghijklm...' - https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs
*
* @returns { Blob } 
*/
export const dataURLtoBlob = (dataURL) => {
    var arr = dataURL.split(','), mime = arr[0].match(/:(.*?);/)[1],
        bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
    while(n--){
        u8arr[n] = bstr.charCodeAt(n);
    }
    return new Blob([u8arr], { type: mime } );
}

/**
* convert Blob to Data URL - https://gist.github.com/wuchengwei/b7e1820d39445f431aeaa9c786753d8e
* 
* @param { Blob } 
* @param { Function } 
*
* @returns { DataURL } 
*/
export const blobToDataURL = (blob, callback) => {
    var a = new FileReader();
    a.onload = function(e) { callback(e.target.result); }
    a.readAsDataURL(blob);
}