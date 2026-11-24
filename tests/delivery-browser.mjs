import {spawn} from 'node:child_process';
import {readFile, writeFile} from 'node:fs/promises';
const data = JSON.parse(await readFile('public/var/tmp/wgmulti-delivery-browser.json', 'utf8'));
const base = 'https://dev.lmsperformance.eu';
const port = 9397;
const chrome = spawn('google-chrome', ['--headless', '--no-sandbox', '--disable-gpu', '--remote-debugging-port=' + port,
    '--user-data-dir=/tmp/wgmulti-delivery-chrome-' + Date.now(), 'about:blank'], {stdio: 'ignore'});
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
const sockets = [];
async function connect(url) {
    const socket = new WebSocket(url);
    sockets.push(socket);
    await new Promise(resolve => socket.addEventListener('open', resolve, {once: true}));
    let id = 0;
    const pending = new Map();
    socket.addEventListener('message', event => {
        const result = JSON.parse(event.data);
        const p = pending.get(result.id);
        if (p) { pending.delete(result.id); result.error ? p.reject(result.error) : p.resolve(result.result); }
    });
    return (method, params = {}) => new Promise((resolve, reject) => {
        const n = ++id;
        pending.set(n, {resolve, reject});
        socket.send(JSON.stringify({id: n, method, params}));
    });
}
let browser;
try {
    let version;
    for (let i = 0; i < 100; i++) {
        try { version = await fetch('http://127.0.0.1:' + port + '/json/version').then(r => r.json()); break; } catch { await sleep(100); }
    }
    if (!version) { throw new Error('Chrome did not start.'); }
    browser = await connect(version.webSocketDebuggerUrl);
    for (let context = 0; context < 2; context++) {
        const {browserContextId} = await browser('Target.createBrowserContext');
        const {targetId} = await browser('Target.createTarget', {url: 'about:blank', browserContextId});
        const call = await connect('ws://127.0.0.1:' + port + '/devtools/page/' + targetId);
        const evaluate = async expression => {
            const r = await call('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
            if (r.exceptionDetails) { throw new Error(JSON.stringify(r.exceptionDetails)); }
            return r.result.value;
        };
        async function waitFor(expression, label) {
            for (let i = 0; i < 100; i++) { if (await evaluate(expression)) { return; } await sleep(200); }
            throw new Error('Timed out: ' + label + ' ' + JSON.stringify(await evaluate('({url:location.href,title:document.title,text:document.querySelector("[data-wgmulti-delivery]")?.textContent})')));
        }
        await call('Page.enable');
        await call('Emulation.setDeviceMetricsOverride', {width: context ? 390 : 1366, height: 900, deviceScaleFactor: 1, mobile: !!context});
        await call('Page.addScriptToEvaluateOnNewDocument', {source: `(()=>{const fetchOriginal=window.fetch;window.fetch=function(input,options){if(String(input).includes('/wgmulti/delivery/estimate')){if(window.deliveryTestFailure){return Promise.reject(new Error('Simulated failure'));}return new Promise(resolve=>setTimeout(resolve,2000)).then(()=>fetchOriginal.call(this,input,options));}return fetchOriginal.call(this,input,options);};})()`});
        await call('Page.navigate', {url: base + '/catalog/product/view/id/' + data.products[0] + '/'});
        await waitFor(`document.querySelector('[data-wgmulti-delivery-slot]') && document.readyState==='complete'`, 'Reserved delivery space');
        const reserved = await evaluate(`(()=>{const s=document.querySelector('[data-wgmulti-delivery-slot]'),b=s.querySelector('[data-wgmulti-delivery]');return {height:s.getBoundingClientRect().height,top:s.getBoundingClientRect().top+scrollY,hidden:b.hidden};})()`);
        if (!reserved.hidden || reserved.height < 57) { throw new Error('Initial estimate space not reserved: '+JSON.stringify(reserved)); }
        await waitFor('document.querySelector("[data-wgmulti-delivery]")?.hidden === false', 'Simple product estimate');
        const filled = await evaluate(`(()=>{const s=document.querySelector('[data-wgmulti-delivery-slot]');return {height:s.getBoundingClientRect().height,top:s.getBoundingClientRect().top+scrollY};})()`);
        if (Math.abs(filled.height-reserved.height)>1 || Math.abs(filled.top-reserved.top)>1) { throw new Error('Estimate changed reserved layout: '+JSON.stringify({reserved,filled})); }
        await evaluate(`(()=>{document.querySelector('#zec-close')?.click(); [...document.querySelectorAll('button')].find(x=>x.textContent.trim()==='Összes elutasítása')?.click();})()`);
        const before = await evaluate('document.querySelector("[data-wgmulti-delivery]").textContent');
        if (!before.includes('Becsült kézbesítés') || !before.includes('1-2 munkanap')) { throw new Error('Incorrect initial estimate: ' + before); }
        await evaluate(`(()=>{const q=document.querySelector('[name="qty"]');q.value='3';q.dispatchEvent(new Event('input',{bubbles:true}));})()`);
        await waitFor(`document.querySelector('[data-wgmulti-delivery]')?.textContent.includes('5-7 munkanap') && !document.querySelector('[data-wgmulti-delivery]').hidden`, 'Split warehouse estimate');
        for (const [width, height] of [[1366, 900], [390, 844]]) {
            await call('Emulation.setDeviceMetricsOverride', {width, height, deviceScaleFactor: 1, mobile: width < 600});
            await evaluate(`document.querySelector('[data-wgmulti-delivery]').scrollIntoView({block:'center'})`);
            await sleep(500);
            const box = await evaluate(`(()=>{const b=document.querySelector('[data-wgmulti-delivery]');const r=b.getBoundingClientRect(),cart=b.closest('.add-to-cart'),button=cart.querySelector('button.btn-cart').getBoundingClientRect();return {width:r.width,left:r.left,right:r.right,viewport:innerWidth,hidden:b.hidden,text:b.textContent,below:r.top>=button.bottom,near:r.top-button.bottom<16,inCart:!!cart,count:document.querySelectorAll('[data-wgmulti-delivery]').length,height:r.height,notice:!!b.querySelector('.wgmulti-delivery__notice'),overflow:document.documentElement.scrollWidth>innerWidth+1};})()`);
            if (box.hidden || box.left < 0 || box.right > box.viewport + 1 || box.width < 150 || box.overflow) { throw new Error('Invalid layout: ' + JSON.stringify(box)); }
            if (!box.below || !box.near || !box.inCart || box.count!==1 || box.notice || box.height>65 || !box.text.includes('Házhozszállítással várhatóan 5-7 munkanap.')) { throw new Error('Delivery placement or copy incorrect: '+JSON.stringify(box)); }
            if (context === 0) {
                const shot = await call('Page.captureScreenshot', {format: 'png'});
                await writeFile('/tmp/wgmulti-delivery-' + width + '.png', Buffer.from(shot.data, 'base64'));
            }
        }
        await evaluate(`(()=>{const q=document.querySelector('[name="qty"]');q.value='99';q.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        await waitFor(`document.querySelector('[data-wgmulti-delivery]')?.hidden === true`, 'Insufficient quantity hides estimate');
        await waitFor(`document.querySelector('[data-wgmulti-delivery-slot]').hidden`, 'Unavailable estimate collapses reserved space');
        await evaluate(`(()=>{window.deliveryTestFailure=true; const q=document.querySelector('[name="qty"]');q.value='1';q.dispatchEvent(new Event('input',{bubbles:true}));})()`);
        await waitFor(`document.querySelector('[data-wgmulti-delivery-slot]').hidden`, 'Failed request collapses reserved space');
        await evaluate(`(()=>{window.deliveryTestFailure=false; const q=document.querySelector('[name="qty"]');q.value='2';q.dispatchEvent(new Event('input',{bubbles:true}));})()`);
        await waitFor(`!document.querySelector('[data-wgmulti-delivery]').hidden && !document.querySelector('[data-wgmulti-delivery-slot]').hidden`, 'Valid estimate restores reserved space');
        const api = await evaluate(`(async()=>{const r=await fetch('/wgmulti/delivery/estimate/?___store=default',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'product=${data.products[0]}&qty=1'});return {cache:r.headers.get('Cache-Control'),body:await r.json()};})()`);
        if (!api.cache?.includes('no-store') || api.body.min_days !== 1) { throw new Error('Unsafe or incorrect API response.'); }
        await call('Page.navigate', {url: base + '/catalog/product/view/id/' + data.products[2] + '/'});
        await waitFor(`document.querySelector('[data-wgmulti-delivery]')?.dataset.product === '${data.products[2]}'`, 'Configurable page');
        await evaluate(`(()=>{const s=document.querySelector('[name="super_attribute[${data.color_attribute}]"]');if(!s)throw Error('Missing configurable selector');s.value='34231';s.dispatchEvent(new Event('change',{bubbles:true}));})()`);
        await waitFor(`!document.querySelector('[data-wgmulti-delivery]').hidden && document.querySelector('[data-wgmulti-delivery]').textContent.includes('3-5 munkanap')`, 'Selected variant estimate');
        if (!await evaluate(`document.querySelectorAll('[data-wgmulti-delivery]').length === 1 && !!document.querySelector('[data-wgmulti-delivery]').closest('.add-to-cart')`)) { throw new Error('Configurable estimate is not under its cart button.'); }
        await browser('Target.disposeBrowserContext', {browserContextId});
        console.log('PASS: isolated context ' + (context + 1) + ', desktop/mobile, quantity changes, stock shortfall, selected variant and no-store response.');
    }
} finally {
    if (browser) { try { await browser('Browser.close'); } catch {} }
    sockets.forEach(socket => socket.close());
    chrome.kill();
}
