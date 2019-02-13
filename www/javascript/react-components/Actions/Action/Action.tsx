import * as React from "react";

interface IAction {
    /** Icon to display */
    icon: string;

    /** Link for the action */
    href: string;

    /** Describe the action, hover text for the icon */
    title: string;
}

/**
 * A possible action to take on an item.
 * Generally used on the top right of a page, or a card
 */
const Action: React.SFC<IAction> = ({ href, icon, title }) => {
    return <a href={href} className={`icon-${icon} no-text`} title={title} />;
}

export { IAction, Action }; 