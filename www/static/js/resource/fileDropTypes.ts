import type { StorageFile } from "./resourceUtils";

export type FileDropFile = {
    file: File;
    error: false | string;
    /** When the file is uploaded to storage, it'll get a hash */
    storageHash?: string;
    storageFile?: StorageFile;
};
