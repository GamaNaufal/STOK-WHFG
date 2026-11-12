/* Run with PLAYWRIGHT_MODULE pointing to playwright/playwright-core if needed. */
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const baseURL = process.env.LOGIN_PREVIEW_URL || 'http://127.0.0.1:8765/login';
const layoutOnly = process.env.LOGIN_LAYOUT_ONLY === '1';
const output = path.resolve(__dirname, '../../storage/app/previews/login-cinematic-20261111');
fs.mkdirSync(output, {recursive: true});

(async () => {
    const browser = await chromium.launch({
        headless: true,
        channel: process.env.BROWSER_CHANNEL || 'chrome',
    });
    const results = [];
    try {
        for (const width of [360, 390, 768, 1024, 1440, 1920]) {
            const page = await browser.newPage({viewport: {width, height: 900}});
            const errors = [];
            const mediaRequests = [];
            page.on('pageerror', error => errors.push(error.message));
            page.on('request', request => {
                if (/\.mp4(?:\?|$)/.test(request.url())) mediaRequests.push(request.url());
            });
            await page.goto(baseURL, {waitUntil: 'networkidle'});
            const layout = await page.evaluate(() => {
                const button = document.querySelector('.btn-login').getBoundingClientRect();
                const video = document.querySelector('video');
                const inputs = [...document.querySelectorAll('.form-control')];
                return {
                    width: innerWidth,
                    scrollWidth: document.documentElement.scrollWidth,
                    buttonBottom: button.bottom,
                    buttonHeight: button.height,
                    inputHeights: inputs.map(input => input.getBoundingClientRect().height),
                    source: video.getAttribute('src'),
                    videoWidth: video.videoWidth,
                    duration: Number.isFinite(video.duration) ? video.duration : null,
                    imageReady: document.querySelector('picture img').naturalWidth > 0,
                    csrfPresent: Boolean(document.querySelector('input[name="_token"]').value),
                };
            });
            assert.ok(layout.scrollWidth <= width, `Horizontal overflow at ${width}`);
            assert.ok(layout.buttonBottom <= 900, `Login action offscreen at ${width}`);
            assert.ok(layout.buttonHeight >= 44 && layout.inputHeights.every(h => h >= 44));
            assert.ok(layout.csrfPresent && layout.imageReady, `Missing form/poster at ${width}`);
            assert.deepEqual(errors, []);
            if (width < 1024) {
                assert.equal(layout.source, null);
                assert.equal(mediaRequests.length, 0, `Mobile downloaded video at ${width}`);
            } else if (!layoutOnly) {
                assert.equal(layout.videoWidth, 1280);
                assert.equal(layout.duration, 90);
                await page.evaluate(() => {
                    const video = document.querySelector('video');
                    video.currentTime = 8;
                });
                await page.waitForFunction(() => !document.querySelector('video').seeking);
                await page.locator('[data-login-motion]').click();
                assert.equal(await page.locator('video').evaluate(video => video.paused), true);
                assert.equal(await page.locator('[data-login-motion]').getAttribute('aria-label'), 'Putar animasi gudang');
                await page.locator('[data-login-motion]').press('Enter');
                await page.waitForFunction(() => !document.querySelector('video').paused);
                await page.locator('[data-login-motion]').click();
            }
            await page.locator('#password').fill('preview-only');
            await page.locator('#togglePassword').click();
            assert.equal(await page.locator('#password').getAttribute('type'), 'text');
            await page.locator('#togglePassword').click();
            assert.equal(await page.locator('#password').getAttribute('type'), 'password');
            await page.locator('#password').fill('');
            await page.locator('#email').focus();
            await page.keyboard.press('Tab');
            assert.equal(await page.evaluate(() => document.activeElement.id), 'password');
            await page.screenshot({path: path.join(output, `login-${width}.png`), fullPage: true});
            results.push({case: `viewport-${width}`, ...layout, mediaRequests: mediaRequests.length});
            await page.close();
        }
        for (const scenario of ['reduced-motion', 'save-data', 'slow-2g', 'no-javascript', 'media-failure', 'autoplay-denied']) {
            const page = await browser.newPage({
                viewport: {width: 1440, height: 900},
                reducedMotion: scenario === 'reduced-motion' ? 'reduce' : 'no-preference',
                javaScriptEnabled: scenario !== 'no-javascript',
            });
            const mediaRequests = [];
            page.on('request', request => {
                if (/\.mp4(?:\?|$)/.test(request.url())) mediaRequests.push(request.url());
            });
            if (['save-data', 'slow-2g'].includes(scenario)) {
                await page.addInitScript(({saveData, effectiveType}) => {
                    Object.defineProperty(navigator, 'connection', {value: {saveData, effectiveType}});
                }, {saveData: scenario === 'save-data', effectiveType: scenario === 'slow-2g' ? 'slow-2g' : '4g'});
            }
            if (scenario === 'media-failure') await page.route('**/*.mp4', route => route.abort());
            if (scenario === 'autoplay-denied') await page.addInitScript(() => {
                // Disable the browser's independent autoplay path so the mocked
                // rejected play() promise represents a real policy rejection.
                new MutationObserver(() => {
                    document.querySelectorAll('video[autoplay]').forEach(video => video.removeAttribute('autoplay'));
                }).observe(document, {childList: true, subtree: true});
                HTMLMediaElement.prototype.play = () => Promise.reject(new DOMException('Preview autoplay denied', 'NotAllowedError'));
            });
            await page.goto(baseURL, {waitUntil: 'networkidle'});
            if (!['media-failure', 'autoplay-denied'].includes(scenario)) assert.equal(mediaRequests.length, 0, scenario);
            assert.ok(await page.locator('picture img').evaluate(img => img.naturalWidth > 0));
            assert.equal(await page.locator('[data-login-cinema]').evaluate(el => el.classList.contains('has-video-frame')), false);
            await page.locator('#email').fill('preview@example.test');
            await page.locator('#password').fill('preview-only');
            assert.equal(await page.locator('form').evaluate(form => form.checkValidity()), true);
            await page.screenshot({path: path.join(output, `${scenario}.png`), fullPage: true});
            results.push({case: scenario, posterVisible: true, formValid: true, mediaRequests: mediaRequests.length});
            await page.close();
        }
        // Exercise the real CSRF/session/validation redirect without querying
        // accounts or submitting real credentials. Invalid email stops in validation.
        const invalid = await browser.newPage({viewport: {width: 360, height: 900}});
        await invalid.goto(baseURL, {waitUntil: 'networkidle'});
        const token = await invalid.locator('input[name="_token"]').inputValue();
        const response = await invalid.request.post(baseURL, {
            form: {_token: token, email: 'invalid-email', password: ''},
            headers: {Referer: baseURL}, maxRedirects: 0,
        });
        assert.equal(response.status(), 302);
        await invalid.goto(baseURL, {waitUntil: 'networkidle'});
        assert.equal(await invalid.locator('[role="alert"]').count(), 1);
        assert.equal(await invalid.locator('#email').inputValue(), 'invalid-email');
        assert.equal(await invalid.locator('#password').inputValue(), '');
        assert.equal(await invalid.locator('#password').evaluate(el => getComputedStyle(el).backgroundImage), 'none');
        assert.ok(await invalid.locator('.btn-login').evaluate(el => el.getBoundingClientRect().bottom <= innerHeight));
        await invalid.screenshot({path: path.join(output, 'validation-error-360.png'), fullPage: true});
        results.push({case: 'validation-error-360', errorVisible: true, emailPreserved: true, passwordEmpty: true});
        await invalid.close();
        fs.writeFileSync(path.join(output, 'browser-report.json'), JSON.stringify(results, null, 2) + '\n');
        console.log(JSON.stringify(results, null, 2));
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
