/**
 * A single OAuth client entry
 */
export type OAuthClient = {
    id: number;
    name: string;
    clientId: string;
    redirectUri: string; // When the user approves a request to share details, where should they be redirected to?
    clientCredentialsUser: {
        // Any client credentials requests (i.e. requests made by the client backend to this Schoolbox instance, without interaction by an actual user) will be authenticated as this user;
        id: number;
        _links: {
            profile: string;
            avatar: string;
        };
        title: string;
        firstName: string;
        preferredName: string;
        pronoun: string;
        lastName: string;
        fullName: string;
        isDeleted: boolean;
    };
    _links: {
        // action links
        put: string;
        patch: string;
        delete: string;
        regenerate: string;
    };
};

/**
 * Return an empty OAuth client object
 * @returns {OAuthClient} an empty OAuth client object
 */
export function getEmptyOAuthClient() {
    return {
        id: 0,
        name: "",
        clientId: "",
        redirectUri: "",
        clientCredentialsUser: {
            id: 0,
            _links: {
                profile: "",
                avatar: "",
            },
            title: "",
            firstName: "",
            preferredName: "",
            pronoun: "",
            lastName: "",
            fullName: "",
            isDeleted: false,
        },
        _links: {
            put: "",
            patch: "",
            delete: "",
            regenerate: "",
        },
    };
}
