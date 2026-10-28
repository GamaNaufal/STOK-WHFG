import { mkdir, copyFile, readdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const output=path.join(root,'public/vendor/ocr');
await mkdir(path.join(output,'lang'),{recursive:true});
await copyFile(path.join(root,'node_modules/tesseract.js/dist/worker.min.js'),path.join(output,'worker.min.js'));
for(const name of await readdir(path.join(root,'node_modules/tesseract.js-core'))) {
    if(/^tesseract-core.*\.wasm(?:\.js)?$/.test(name)) {
        await copyFile(path.join(root,'node_modules/tesseract.js-core',name),path.join(output,name));
    }
}
await copyFile(path.join(root,'node_modules/@tesseract.js-data/eng/4.0.0_best_int/eng.traineddata.gz'),path.join(output,'lang/eng.traineddata.gz'));
await copyFile(path.join(root,'node_modules/@techstark/opencv-js/dist/opencv.js'),path.join(output,'opencv.js'));
console.log('Browser OCR assets prepared locally.');
