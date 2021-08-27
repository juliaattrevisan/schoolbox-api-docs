
/** 
 * Try and catch as many errors as possible to provide 
 * the reason for the error
 *
 */

/** 
 * Another Application or Browser window could already be using the camera
 * 
 * @param { String }
 *
 * @returns { Boolean }
 */
export const isDeviceAlreadyInUseError = (errorCode) => {
    if (errorCode === 'NotReadableError: Could not start video source' ||
        errorCode === 'AbortError: Starting video failed' ||
        errorCode === 'AbortError: Starting videoinput failed') {
        return true;
    } else {
        return false;
    }
}

/** 
 * User rejected access
 * 
 * @param { String }
 *
 * @returns { Boolean }
 */
export const isPermissionDeniedError = (errorCode) => {
    if (errorCode === 'NotAllowedError: Permission denied' ||
        errorCode === 'NotAllowedError: The request is not allowed by the user agent or the platform in the current context.') {
        return true;
    } else {
        return false;
    }
}

/** 
 * Another Application or Browser window could already be using the camera
 * 
 * @param { String }
 *
 * @returns { Boolean }
 */
 export const isDeviceChangeError = (errorCode) => {
    if (errorCode === '[object OverconstrainedError]') {
        return true;
    } else {
        return false;
    }
}