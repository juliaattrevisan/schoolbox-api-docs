import * as React from "react";
import { InformationList } from "@schoolbox/InformationList/InformationList";
import { InformationListItem } from "@schoolbox/InformationList/InformationListItem/InformationListItem";

interface ICardList {
    children: JSX.Element[];
}

/**
 * A list of cards.
 */
const CardList: React.FunctionComponent<ICardList> = props => {
    // Wrap the cards in InformationListItem's
    const cards = props.children.map((card: any) => { // TODO: work out what type to put in instead of 'any'
        return (
            <InformationListItem key={card.props.id} actions={card.props.actions && card.props.actions.length}>
                {card}
            </InformationListItem>
        );
    });

    return (
        <InformationList>
            {cards}
        </InformationList>
    );
}

export { CardList, ICardList };