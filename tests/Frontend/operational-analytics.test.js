import assert from 'node:assert/strict';
import test from 'node:test';
import {nearestChartIndex, chartKeyIndex, toggleChartSeries, formatMetric} from '../../public/js/pages/operational-analytics.js';

const rows = [{x:64}, {x:378}, {x:692}];
test('chart selection follows the plotted bucket at desktop, mobile and letterboxed sizes', () => {
    for (const rect of [{left:10,width:720,height:268}, {left:15,width:360,height:134}, {left:0,width:1000,height:268}]) {
        const scale = Math.min(rect.width / 720, rect.height / 268);
        const left = rect.left + (rect.width - 720 * scale) / 2;
        for (let index = 0; index < rows.length; index++) assert.equal(nearestChartIndex(rows, left + rows[index].x * scale, rect), index);
    }
    assert.equal(nearestChartIndex(rows, -500, {left:0,width:720}), 0);
    assert.equal(nearestChartIndex(rows, 5000, {left:0,width:720}), 2);
    assert.equal(nearestChartIndex([{x:378}], 0, {left:0,width:720}), 0);
});

test('empty or temporarily hidden plots do not produce a cursor selection', () => {
    assert.equal(nearestChartIndex([], 100, {left:0,width:720}), -1);
    assert.equal(nearestChartIndex(rows, 100, {left:0,width:0}), -1);
    assert.equal(nearestChartIndex(rows, NaN, {left:0,width:720}), -1);
});

test('keyboard browsing stays within real buckets and supports Home and End', () => {
    assert.equal(chartKeyIndex('ArrowLeft', 0, 3), 0);
    assert.equal(chartKeyIndex('ArrowRight', 2, 3), 2);
    assert.equal(chartKeyIndex('ArrowLeft', 2, 3), 1);
    assert.equal(chartKeyIndex('Home', 2, 3), 0);
    assert.equal(chartKeyIndex('End', 0, 3), 2);
    assert.equal(chartKeyIndex('End', 0, 0), -1);
});

test('series controls isolate a metric but always retain one visible series', () => {
    const original = {first:true,second:true};
    const incomingOnly = toggleChartSeries(original, 'second');
    assert.deepEqual(incomingOnly, {first:true,second:false});
    assert.deepEqual(original, {first:true,second:true});
    assert.deepEqual(toggleChartSeries(incomingOnly, 'first'), {first:false,second:true});
    assert.deepEqual(toggleChartSeries(original, 'unknown'), original);
});

test('visual counters preserve Indonesian grouping, zero and fractional KPI values', () => {
    assert.equal(formatMetric(10000), '10.000');
    assert.equal(formatMetric(66.7,1), '66,7');
    assert.equal(formatMetric(0), '0');
    assert.equal(formatMetric(NaN), '0');
});
