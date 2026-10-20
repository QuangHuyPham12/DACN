const fs = require('node:fs');
const jsQR = require('./jsQR.js');
const bgra = fs.readFileSync(__dirname + '/qr-bgra.bin');
const rgba = new Uint8ClampedArray(bgra.length);
for (let i = 0; i < bgra.length; i += 4) {
 rgba[i] = bgra[i + 2]; rgba[i + 1] = bgra[i + 1]; rgba[i + 2] = bgra[i]; rgba[i + 3] = 255;
}
const found = jsQR(rgba, 274, 274);
const expected = fs.readFileSync(__dirname + '/qr-test-payload.txt', 'utf8');
if (!found || found.data !== expected) throw new Error('QR screenshot did not decode to the server payload');
console.log(JSON.stringify({screenshot_decoded:true,exact_payload_match:true,amount_vnd:780000,test_only:true}));
