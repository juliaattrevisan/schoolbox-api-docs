
/*
 * Any utils that manipulate filenames
 *
 */

/*
* convert the filename to use the extension instead of whatever it was
* 
* @params { String } '593042579804375.webm'
* @params { String } '.mp4'
*/ 
export const convertFilenameExtension = (filname, extension) => {
    const filenameWithoutExtension = filname.replace(/\.[^/.]+$/, "");
    return filenameWithoutExtension + extension;
}