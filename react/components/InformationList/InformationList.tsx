import * as React from "react";

interface IInformationList {
    children: JSX.Element[];
}

/**
 * List of information.
 * For the most part you will want to use CardList instead of this (which uses this).
 */
const InformationList: React.FunctionComponent<IInformationList> = props => {
    return (
        <ul className="information-list">
            {props.children}
        </ul>
    );
}

export { IInformationList, InformationList };