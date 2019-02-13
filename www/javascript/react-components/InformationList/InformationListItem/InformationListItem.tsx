import * as React from "react";

interface IInformationListItem {
    children: JSX.Element[];

    /** The amount of actions that will display on the top right of this item */
    actions?: number;
}

/**
 * An information list item.
 */
const InformationListItem: React.SFC<IInformationListItem> = props => {
    return (
        <li className={props.actions && `actions-small-${props.actions}`}>
            {props.children}
        </li>
    );
}

export { IInformationListItem, InformationListItem };