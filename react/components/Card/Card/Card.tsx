import * as React from "react";
import { IImage, Image } from "@schoolbox/Image/Image";
import { Meta } from "@schoolbox/Meta/Meta";
import { Actions } from "@schoolbox/Actions/Actions";
import { Action, IAction } from "@schoolbox/Actions/Action/Action";

interface ICard {
  /** Unique identifier for the card */
  id: string;

  /** Title to display on the card */
  title: string;

  /** Meta information to display below the title */
  meta: string | string[];
  // ^Ideally this would be Meta | Meta[] instead of string, but that would require us to send
  // meta={{children: "Some meta" }} instead of just meta="Some meta", so I'm leaving this for now.

  /** Adds a link to the image and title of the card */
  href?: string;

  /** URL of the image to display on the card */
  image?: IImage;

  /** Actions to display on the top right of the card (will only work properly from inside an InformationList at this point) */
  actions?: IAction[];
}

/**
 * Standard size card. Usually contains an image, title & meta.
 */
const Card: React.SFC<ICard> = props => {
  return (
    <>
      <div className="list-item">
        <div className="small-12 card">
          <CardImage {...props} />
          <CardTitle {...props} />
          <CardMeta {...props} />
        </div>
      </div>
      <CardActions {...props} />
      <CardContent {...props} />
    </>
  );
};

const CardImage: React.SFC<ICard> = props => {
  const { image, href } = props;
  if (image && href) {
    return (
      <a href={href}>
        <Image src={image.src} alt={image.alt} />
      </a>
    );
  }

  if (image) {
    return <Image src={image.src} alt={image.alt} />;
  }

  return null;
};

const CardTitle: React.SFC<ICard> = props => {
  const { title, href } = props;

  if (title && href) {
    return (
      <h3>
        <a href={href}>{title}</a>
      </h3>
    );
  }

  if (title) {
    return <h3>{title}</h3>;
  }

  return null;
};

const CardMeta: React.SFC<ICard> = props => {
  const { meta } = props;

  if (meta && Array.isArray(meta)) {
    return (
      <>
        {meta.map((thisMeta, index) => (
          <Meta key={index}>{thisMeta}</Meta>
        ))}
      </>
    );
  }

  if (meta && !Array.isArray(meta)) {
    return <Meta>{meta}</Meta>;
  }

  return null;
};

const CardActions: React.SFC<ICard> = ({ actions }) => {
  if (!actions) {
    return null;
  }

  const actionComponents = actions.map((action: IAction) => <Action key={action.id} {...action} />);
  return <Actions>{actionComponents}</Actions>;
}

const CardContent: React.SFC<ICard> = ({ children }) => {
  if (!children) {
    return null;
  }

  return <article>{children}</article>;
}

export { ICard, Card };
