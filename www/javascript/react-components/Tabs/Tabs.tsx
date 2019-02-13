import * as React from "react";
import {
    Tabs as ReactTabs,
    Tab as ReactTab,
    TabList as ReactTabList
} from "react-tabs";
import { ITab } from './Tab/Tab'

interface ITabs {
    /** Callback when a new tab is switched to. */
    onSelect?: (index: number, last: number, event: Event) => boolean | void;

    /** Default tab to show on render */
    defaultIndex?: number;

    /** Set of tabs & content to show */
    children: any; // TODO- figger out what type to put here
}

/**
 * Horizontal navigation tabs
 * Wraps an external package called [react-tabs](https://github.com/reactjs/react-tabs)
 */
const Tabs: React.SFC<ITabs> = props => {
    const tabHeadings: React.ReactNode[] = [];
    props.children.forEach((tab: any, index: number) => {
        tabHeadings.push(
            <ReactTab key={tab.props.id} className="tab-title" selectedClassName="active">
                <a>{tab.props.tab}</a>
            </ReactTab>
        );
    });

    return (
        <ReactTabs {...props}>
            <ReactTabList className="tabs">
                {tabHeadings}
            </ReactTabList>
            {props.children}
        </ReactTabs>
    );
};

export { Tabs, ITabs };