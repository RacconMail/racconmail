/**
 * Search Engine Submission & IndexNow Broadcast Script
 * Submits racconmail.eu to all global search engine indexers and directories.
 */

const https = require('https');
const http = require('http');

const HOST = 'racconmail.eu';
const KEY = '36eb429cbb0749a997d4c063b4f5be7e';
const KEY_LOCATION = 'https://racconmail.eu/36eb429cbb0749a997d4c063b4f5be7e.txt';

const URL_LIST = [
  'https://racconmail.eu/',
  'https://racconmail.eu/auth',
  'https://racconmail.eu/forum',
  'https://racconmail.eu/news',
  'https://racconmail.eu/stories',
  'https://racconmail.eu/legal/terms',
  'https://racconmail.eu/legal/privacy'
];

async function postJSON(endpoint, data) {
  return new Promise((resolve) => {
    try {
      const url = new URL(endpoint);
      const postData = JSON.stringify(data);
      const req = https.request(
        {
          hostname: url.hostname,
          path: url.pathname + url.search,
          method: 'POST',
          headers: {
            'Content-Type': 'application/json; charset=utf-8',
            'Content-Length': Buffer.byteLength(postData),
            'User-Agent': 'RacconMail-Indexer/1.0'
          },
          timeout: 10000
        },
        (res) => {
          let body = '';
          res.on('data', chunk => body += chunk);
          res.on('end', () => {
            resolve({
              endpoint,
              status: res.statusCode,
              body: body.substring(0, 300)
            });
          });
        }
      );

      req.on('error', (err) => {
        resolve({ endpoint, status: 'ERROR', error: err.message });
      });
      req.on('timeout', () => {
        req.destroy();
        resolve({ endpoint, status: 'TIMEOUT' });
      });

      req.write(postData);
      req.end();
    } catch (e) {
      resolve({ endpoint, status: 'EXCEPTION', error: e.message });
    }
  });
}

async function getURL(endpoint) {
  return new Promise((resolve) => {
    try {
      const url = new URL(endpoint);
      const client = url.protocol === 'https:' ? https : http;
      const req = client.get(
        endpoint,
        {
          headers: { 'User-Agent': 'RacconMail-Indexer/1.0' },
          timeout: 10000
        },
        (res) => {
          let body = '';
          res.on('data', chunk => body += chunk);
          res.on('end', () => {
            resolve({
              endpoint,
              status: res.statusCode,
              body: body.substring(0, 300)
            });
          });
        }
      );
      req.on('error', (err) => resolve({ endpoint, status: 'ERROR', error: err.message }));
      req.on('timeout', () => {
        req.destroy();
        resolve({ endpoint, status: 'TIMEOUT' });
      });
    } catch (e) {
      resolve({ endpoint, status: 'EXCEPTION', error: e.message });
    }
  });
}

async function main() {
  console.log('🚀 Starting Worldwide Search Engine Indexing & Directory Submission...');
  console.log(`📡 Target Host: ${HOST}`);
  console.log(`🔑 Verification Key: ${KEY}`);
  console.log(`📄 URL Count: ${URL_LIST.length}\n`);

  const indexNowPayload = {
    host: HOST,
    key: KEY,
    keyLocation: KEY_LOCATION,
    urlList: URL_LIST
  };

  const endpoints = [
    'https://api.indexnow.org/indexnow',
    'https://www.bing.com/indexnow',
    'https://yandex.com/indexnow',
    'https://search.seznam.cz/indexnow'
  ];

  console.log('--- 1. IndexNow API Broadcasts ---');
  for (const ep of endpoints) {
    const res = await postJSON(ep, indexNowPayload);
    console.log(`[IndexNow] ${ep} => Status: ${res.status} ${res.body ? `(${res.body})` : ''}`);
  }

  console.log('\n--- 2. Search Engine Sitemap Pings ---');
  const pings = [
    'https://www.google.com/ping?sitemap=https%3A%2F%2Fracconmail.eu%2Fsitemap.xml',
    'https://www.bing.com/ping?sitemap=https%3A%2F%2Fracconmail.eu%2Fsitemap.xml'
  ];

  for (const ping of pings) {
    const res = await getURL(ping);
    console.log(`[Sitemap Ping] ${res.endpoint} => Status: ${res.status}`);
  }

  console.log('\n✅ Search Engine submission and IndexNow broadcast complete!');
}

main();
