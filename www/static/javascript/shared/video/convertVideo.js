
/*
 * Convert video to other formats
 *
 */

import { createFFmpeg, fetchFile } from '@ffmpeg/ffmpeg';

/*
* Convert a video to mp4
*
* @params { Blob/File } file to convert
* @params { String } 'blah.mp4'
* @params { Function } to notify of how the conversion is tracking

* @returns { Blob } 
*/
export const convertToMp4 = async (inputFile, outputFileName, onProgress) => {
	const ffmpeg = createFFmpeg({
		log: true,
		progress: ({ ratio }) => onProgress(ratio)
	});
	await ffmpeg.load(); //load in ffmpeg for use
	ffmpeg.FS('writeFile', inputFile.name, await fetchFile(inputFile)); //save first
	await ffmpeg.run('-i', inputFile.name, outputFileName); //then run conversion
	const convertedData = ffmpeg.FS('readFile', outputFileName); //the raw mp4 data
	return new Blob([convertedData.buffer], { type: 'video/mp4' }); //return as blob for return
}