import type { APIRequestContext } from "@playwright/test";
import type BaseTester from "./BaseTester";

export interface DataPairs {
    [key: string]: string | number | boolean | unknown[] | null;
}

export default class ApiHelper {
    public request: APIRequestContext;

    private baseTester: BaseTester;

    private token: string | null = null;

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
        if (this.token) {
            return this.token; // Return the existing token if available
        }

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
        return respBody.token;
    }

    /**
     * @returns a {headers: { Authorization: `Bearer ${token}` }} object for use in API requests
     */
    async getAuthHeader(): Promise<{ headers: { Authorization: string } }> {
        const token = await this.getUserToken();
        return { headers: { Authorization: `Bearer ${token}` } };
    }
}
