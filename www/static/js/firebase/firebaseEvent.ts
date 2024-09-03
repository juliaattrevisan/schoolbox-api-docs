export const PWA_NOTIFICATION_ALLOW = "PWA_NOTIFICATION_ALLOW";
export const PWA_NOTIFICATION_DENY = "PWA_NOTIFICATION_DENY";

export interface FirebaseRequestNotifEvent extends CustomEvent {
    detail: {
        deviceId: string;
    };
}

export interface FirebaseDenyEvent extends CustomEvent {
    detail: Record<PropertyKey, never>;
}

export interface FirebaseDocumentEventHandlerMap {
    [PWA_NOTIFICATION_ALLOW]: FirebaseRequestNotifEvent;
    [PWA_NOTIFICATION_DENY]: FirebaseDenyEvent;
}
