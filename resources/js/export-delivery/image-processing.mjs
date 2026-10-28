// Pure image operations shared by browser OCR and local verification.
export function preparePrintedTable(cv, image) {
    const mats = [];
    const keep = (mat) => { mats.push(mat); return mat; };
    const source = keep(cv.matFromImageData(image));
    const gray = keep(new cv.Mat());
    const binary = keep(new cv.Mat());
    const contours = keep(new cv.MatVector());
    const hierarchy = keep(new cv.Mat());
    try {
        cv.cvtColor(source, gray, cv.COLOR_RGBA2GRAY);
        cv.adaptiveThreshold(gray, binary, 255, cv.ADAPTIVE_THRESH_GAUSSIAN_C, cv.THRESH_BINARY_INV, 31, 12);
        cv.findContours(binary, contours, hierarchy, cv.RETR_EXTERNAL, cv.CHAIN_APPROX_SIMPLE);
        let best = null;
        for (let i = 0; i < contours.size(); i++) {
            const contour = contours.get(i);
            const rect = cv.boundingRect(contour);
            const area = cv.contourArea(contour);
            if (rect.width > source.cols * 0.55 && rect.height > source.rows * 0.1 &&
                rect.height < source.rows * 0.72 && area > source.cols * source.rows * 0.06 &&
                (!best || area > best.area)) {
                const polygon = new cv.Mat();
                cv.approxPolyDP(contour, polygon, cv.arcLength(contour, true) * 0.025, true);
                const points = [];
                const coordinates = polygon.rows === 4 ? polygon.data32S : contour.data32S;
                for (let p = 0; p < coordinates.length; p += 2) points.push({ x: coordinates[p], y: coordinates[p + 1] });
                best = { area, points, rect };
                polygon.delete();
            }
            contour.delete();
        }
        const toPixels = (mat) => {
            const rgba = keep(new cv.Mat());
            cv.cvtColor(mat, rgba, cv.COLOR_GRAY2RGBA);
            return { data: new Uint8ClampedArray(rgba.data), width: rgba.cols, height: rgba.rows };
        };
        let aligned = gray;
        let headerHeight = Math.round(source.rows * 0.23);
        if (best) {
            const minBy = (fn) => best.points.reduce((a, b) => fn(a) < fn(b) ? a : b);
            const maxBy = (fn) => best.points.reduce((a, b) => fn(a) > fn(b) ? a : b);
            const tl = minBy(p => p.x + p.y);
            const tr = maxBy(p => p.x - p.y);
            const br = maxBy(p => p.x + p.y);
            const bl = minBy(p => p.x - p.y);
            const width = 2000;
            const distance = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
            const originalWidth = Math.max(distance(tl, tr), distance(bl, br));
            const height = Math.max(250, Math.round(width * Math.max(distance(tl, bl), distance(tr, br)) / originalWidth));
            const from = keep(cv.matFromArray(4, 1, cv.CV_32FC2, [tl.x, tl.y, tr.x, tr.y, br.x, br.y, bl.x, bl.y]));
            const to = keep(cv.matFromArray(4, 1, cv.CV_32FC2, [0, 0, width - 1, 0, width - 1, height - 1, 0, height - 1]));
            const transform = keep(cv.getPerspectiveTransform(from, to));
            aligned = keep(new cv.Mat());
            cv.warpPerspective(gray, aligned, transform, new cv.Size(width, height), cv.INTER_CUBIC, cv.BORDER_CONSTANT, new cv.Scalar(255));
            headerHeight = Math.min(source.rows, Math.round(Math.max(tl.y, tr.y) + 10));
        }
        const tableBinary = keep(new cv.Mat());
        cv.adaptiveThreshold(aligned, tableBinary, 255, cv.ADAPTIVE_THRESH_GAUSSIAN_C, cv.THRESH_BINARY_INV, 31, 12);
        const horizontal = keep(new cv.Mat());
        const vertical = keep(new cv.Mat());
        const hKernel = keep(cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(Math.max(30, Math.round(aligned.cols / 25)), 1)));
        const vKernel = keep(cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(1, Math.max(30, Math.round(aligned.rows / 25)))));
        cv.morphologyEx(tableBinary, horizontal, cv.MORPH_OPEN, hKernel);
        cv.morphologyEx(tableBinary, vertical, cv.MORPH_OPEN, vKernel);
        const lines = keep(new cv.Mat());
        cv.bitwise_or(horizontal, vertical, lines);
        const kernel = keep(cv.getStructuringElement(cv.MORPH_RECT, new cv.Size(2, 2)));
        cv.dilate(lines, lines, kernel);
        const cleaned = keep(new cv.Mat());
        cv.subtract(tableBinary, lines, cleaned);
        cv.bitwise_not(cleaned, cleaned);
        const headerCrop = keep(gray.roi(new cv.Rect(0, 0, gray.cols, Math.max(1, headerHeight))));
        const header = keep(new cv.Mat());
        cv.resize(headerCrop, header, new cv.Size(2000, Math.round(headerHeight * 2000 / gray.cols)), 0, 0, cv.INTER_CUBIC);
        const cells = [];
        const partX = Math.round(cleaned.cols*0.025), partWidth = Math.round(cleaned.cols*0.20);
        const partRegion = keep(cleaned.roi(new cv.Rect(partX,0,partWidth,cleaned.rows)));
        const partInk = keep(new cv.Mat());
        cv.bitwise_not(partRegion,partInk);
        const letterContours = keep(new cv.MatVector());
        const letterHierarchy = keep(new cv.Mat());
        cv.findContours(partInk,letterContours,letterHierarchy,cv.RETR_EXTERNAL,cv.CHAIN_APPROX_SIMPLE);
        const letters=[];
        for(let i=0;i<letterContours.size();i++) {
            const contour=letterContours.get(i), rect=cv.boundingRect(contour);
            if(rect.height>=10 && rect.height<=65 && rect.width>=3 && rect.width<90 &&
               rect.y>cleaned.rows*0.13 && rect.y+rect.height<cleaned.rows*0.85) letters.push(rect);
            contour.delete();
        }
        letters.sort((a,b)=>(a.y+a.height/2)-(b.y+b.height/2));
        const groups=[];
        for(const rect of letters) {
            const cy=rect.y+rect.height/2;
            let group=groups.find(g=>Math.abs(g.cy-cy)<12);
            if(!group) {group={cy,letters:[]};groups.push(group);}
            group.letters.push(rect);
            group.cy=group.letters.reduce((s,r)=>s+r.y+r.height/2,0)/group.letters.length;
        }
        const cropCell = (x,y,width,height) => {
            const cropped=keep(cleaned.roi(new cv.Rect(x,y,width,height))), padded=keep(new cv.Mat());
            cv.copyMakeBorder(cropped,padded,10,10,10,10,cv.BORDER_CONSTANT,new cv.Scalar(255));
            return toPixels(padded);
        };
        for(const group of groups.sort((a,b)=>a.cy-b.cy)) {
            const left=Math.min(...group.letters.map(r=>r.x)),right=Math.max(...group.letters.map(r=>r.x+r.width));
            if(group.letters.length<4 || right-left<70) continue;
            const top=Math.max(0,Math.min(...group.letters.map(r=>r.y))-10);
            const bottom=Math.min(cleaned.rows,Math.max(...group.letters.map(r=>r.y+r.height))+10);
            cells.push({
                part:cropCell(partX+Math.max(0,left-5),top,Math.min(partWidth-left+5,right-left+10),bottom-top),
                quantity:cropCell(Math.round(cleaned.cols*0.235),top,Math.round(cleaned.cols*0.10),bottom-top),
            });
        }
        return { table: toPixels(cleaned), header: toPixels(header), cells, foundTable: Boolean(best) };
    } finally {
        mats.reverse().forEach(mat => mat.delete());
    }
}
