import * as React from "react";

interface IImage {
  src: string;
  alt: string;
}

/**
 * Just a basic image element
 */
const Image: React.SFC<IImage> = props => {
  const { src, alt } = props;
  return <img src={src} alt={alt} />;
};

export { IImage, Image };
