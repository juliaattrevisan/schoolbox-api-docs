import type { APIRequestContext } from "@playwright/test";
import type BaseTester from "./BaseTester";
import type DatabaseHelper from "./DatabaseHelper";

export default class ApiHelper {
    public request: APIRequestContext;

    private baseTester: BaseTester;

    private token: string | null = null;

    private isFetchingToken: boolean = false;

    private static readonly USER_ID: number = 1; // alaress admin

    /**
     * A helper for interacting with API endpoints
     * @param request - The APIRequestContext instance
     * @param baseTester - The BaseTester instance
     */
    constructor(request: APIRequestContext, baseTester: BaseTester) {
        this.request = request;
        this.baseTester = baseTester;
    }

    /**
     * Retrieve a JWT token for a given user
     * @param userId - ID of the user to generate a token for (optional, default is alaress admin)
     * @param effectorId - ID of the user who generated the token (optional, default is alaress admin)
     * @param expiry - The amount of time (in seconds) the token is valid for (optional, default is 1 hour)
     * @returns The generated JWT token
     */
    async getUserToken(
        userId: number = ApiHelper.USER_ID,
        effectorId: number = ApiHelper.USER_ID,
        expiry?: number,
    ): Promise<string> {
        // Wait for isFetchingToken to be false before proceeding
        await new Promise<void>((resolve) => {
            const interval = setInterval(() => {
                if (this.isFetchingToken) {
                    return;
                }

                clearInterval(interval);
                resolve();
            }, 100);
        });

        if (this.token) {
            return this.token; // Return the existing token if available
        }

        this.isFetchingToken = true; // Avoid multiple calls to getUserToken in parallel

        this.baseTester.isGoingTo(`generate a JWT token for user ${userId} with effector ${effectorId}`);

        const expiryOpt = expiry ? `--expiry ${expiry}` : "";
        const token = await this.baseTester.runShellCommand(
            `php /usr/share/schoolbox/tools/ci/generateUserToken.php --user ${userId} --effector ${effectorId} ${expiryOpt}`,
        );

        const resp = await this.request.post("/user/token/1", {
            headers: {
                "Content-Type": "application/json",
                Authorization: `Bearer ${token.trim()}`,
            },
        });

        if (!resp.ok()) {
            throw new Error(`Failed to generate token: ${resp.status()} ${resp.statusText()}`);
        }

        const respBody: unknown = await resp.json();

        // Make sure there's a token in there...
        if (
            typeof respBody !== "object" ||
            respBody === null ||
            !("token" in respBody) ||
            typeof respBody.token !== "string"
        ) {
            throw new Error("Invalid token response");
        }

        this.token = respBody.token; // Save it for later use
        this.isFetchingToken = false; // Allow other calls to proceed
        return respBody.token;
    }

    /**
     * @returns a {headers: { Authorization: `Bearer ${token}` }} object for use in API requests
     */
    async getAuthHeader(): Promise<{ headers: { Authorization: string } }> {
        const token = await this.getUserToken();
        return { headers: { Authorization: `Bearer ${token}` } };
    }

    /**
     * Delete a folder
     * @param folderId - ID of the folder to delete
     */
    async deleteFolder(folderId: string | number): Promise<void> {
        this.baseTester.isGoingTo(`soft delete the folder with ID ${folderId}`);
        const resp = await this.request.delete(`/resource/folder/${folderId}`, await this.getAuthHeader());
        if (!resp.ok()) {
            throw new Error(`Failed to delete folder: ${resp.status()} ${resp.statusText()}`);
        }
    }

    /**
     * Delete a file
     * @param fileId - ID of the file to delete
     */
    async deleteFile(fileId: string | number): Promise<void> {
        this.baseTester.isGoingTo(`soft delete the file with ID ${fileId}`);
        const resp = await this.request.delete(`/fileForm.php?delete=${fileId}`, await this.getAuthHeader());
        if (!resp.ok()) {
            throw new Error(`Failed to delete file: ${resp.status()} ${resp.statusText()}`);
        }
    }

    /**
     * Delete all folders by name
     * @param db - DatabaseHelper instance
     * @param names - A name, or list of names to delete folders by - Uses SQL LIKE (so % for wildcards)
     * @returns The IDs of the deleted folders
     */
    async deleteFoldersByName(db: DatabaseHelper, names: string | string[]): Promise<number[]> {
        const nameList = Array.isArray(names) ? names : [names];

        const folderIds = (await db.grabColumnFromDatabase(
            "folder",
            "id",
            nameList.map((name) => ({ "name LIKE": name, deleted_at: null })),
        )) as number[];

        await Promise.all(folderIds.map((folderId) => this.deleteFolder(folderId)));

        return folderIds;
    }

    /**
     * Delete a homepage component
     * @param homepageId - ID of the homepage containing the component
     * @param componentInstanceId - ID of the component instance to delete
     */
    async deleteHomepageComponent(homepageId: string | number, componentInstanceId: string | number): Promise<void> {
        this.baseTester.isGoingTo(`delete the component with ID ${componentInstanceId} from homepage ${homepageId}`);

        const formData = new FormData();
        formData.append("cid", String(homepageId));
        formData.append("delete", String(componentInstanceId));

        const resp = await this.request.post(`/homepage/configForm`, {
            ...(await this.getAuthHeader()),
            multipart: formData,
        });

        if (!resp.ok()) {
            throw new Error(`Failed to delete homepage component: ${resp.status()} ${resp.statusText()}`);
        }
    }
}
