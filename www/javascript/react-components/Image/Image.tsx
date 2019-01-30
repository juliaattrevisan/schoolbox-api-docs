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

/**
 * Empty element for creating a skeleton loader of an image
 */
const LoadingImage: React.SFC = props => {
  return <div className="img" />;
};

export { IImage, Image, LoadingImage };
