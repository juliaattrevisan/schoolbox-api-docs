import * as React from "react";

interface IImage {
  src: string;
  alt: string;
}

/**
 * Just a basic image element
 */
const Image: React.SFC<IImage> = props => {
  return <img src={props.src} alt={props.alt} />;
};

export { IImage, Image };
