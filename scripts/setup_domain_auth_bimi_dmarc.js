const https = require('node:https');

async function setupDomainAuth() {
  console.log('=== SETTING UP DOMAIN AUTHENTICATION (DMARC & BIMI) ===');
  const loginBody = new URLSearchParams({ user: 'raccxuxv', pass: 'on4A1WMnvcWi' }).toString();
  const loginRes = await new Promise((resolve) => {
    const req = https.request('https://premium252.web-hosting.com:2083/login/?login_only=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Content-Length': Buffer.byteLength(loginBody) },
      rejectUnauthorized: false
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve({ headers: res.headers, body: JSON.parse(data) }));
    });
    req.write(loginBody);
    req.end();
  });

  const secToken = loginRes.body.security_token;
  const cookie = loginRes.headers['set-cookie'].map(c => c.split(';')[0]).join('; ');

  async function api2Call(module, func, params = {}) {
    const q = new URLSearchParams({
      cpanel_jsonapi_user: 'raccxuxv',
      cpanel_jsonapi_apiversion: '2',
      cpanel_jsonapi_module: module,
      cpanel_jsonapi_func: func,
      ...params
    }).toString();

    const url = 'https://premium252.web-hosting.com:2083' + secToken + '/json-api/cpanel?' + q;
    return new Promise(resolve => {
      https.request(url, { headers: { 'Cookie': cookie }, rejectUnauthorized: false }, res => {
        let d = ''; res.on('data', c => d += c); res.on('end', () => {
          try { resolve(JSON.parse(d)); } catch(_) { resolve(d); }
        });
      }).end();
    });
  }

  // 1. Fetch zone to find DMARC and BIMI lines
  const zoneData = await api2Call('ZoneEdit', 'fetchzone', { domain: 'racconmail.eu' });
  const records = zoneData.cpanelresult.data[0].record || [];
  
  let dmarcLine = null;
  let bimiLine = null;
  for (const r of records) {
    if (r.name && r.name.toLowerCase().startsWith('_dmarc')) {
      dmarcLine = r.line;
      console.log('Found existing DMARC record at line:', dmarcLine, r.record);
    }
    if (r.name && r.name.toLowerCase().startsWith('default._bimi')) {
      bimiLine = r.line;
      console.log('Found existing BIMI record at line:', bimiLine, r.record);
    }
  }

  // 2. Edit or Add DMARC
  const strictDmarc = 'v=DMARC1; p=quarantine; sp=quarantine; pct=100; rua=mailto:dmarc-reports@racconmail.eu; ruf=mailto:dmarc-forensics@racconmail.eu; fo=1; aspf=r; adkim=r';
  if (dmarcLine) {
    console.log('Editing DMARC record to strict quarantine policy...');
    const editRes = await api2Call('ZoneEdit', 'edit_zone_record', {
      domain: 'racconmail.eu',
      line: dmarcLine,
      type: 'TXT',
      txtdata: strictDmarc
    });
    console.log('Edit DMARC status:', editRes.cpanelresult.data ? editRes.cpanelresult.data[0].statusmsg : editRes);
  } else {
    console.log('Adding DMARC record...');
    const addRes = await api2Call('ZoneEdit', 'add_zone_record', {
      domain: 'racconmail.eu',
      name: '_dmarc.racconmail.eu.',
      type: 'TXT',
      txtdata: strictDmarc
    });
    console.log('Add DMARC status:', addRes.cpanelresult.data ? addRes.cpanelresult.data[0].statusmsg : addRes);
  }

  // 3. Edit or Add BIMI
  const bimiRecord = 'v=BIMI1; l=https://racconmail.eu/raccoon-logo.svg; a=;';
  if (bimiLine) {
    console.log('Editing BIMI record...');
    const editRes = await api2Call('ZoneEdit', 'edit_zone_record', {
      domain: 'racconmail.eu',
      line: bimiLine,
      type: 'TXT',
      txtdata: bimiRecord
    });
    console.log('Edit BIMI status:', editRes.cpanelresult.data ? editRes.cpanelresult.data[0].statusmsg : editRes);
  } else {
    console.log('Adding BIMI record (default._bimi.racconmail.eu)...');
    const addRes = await api2Call('ZoneEdit', 'add_zone_record', {
      domain: 'racconmail.eu',
      name: 'default._bimi.racconmail.eu.',
      type: 'TXT',
      txtdata: bimiRecord
    });
    console.log('Add BIMI status:', addRes.cpanelresult.data ? addRes.cpanelresult.data[0].statusmsg : addRes);
  }

  console.log('=== DOMAIN AUTHENTICATION UPDATE FINISHED ===');
}

setupDomainAuth().catch(console.error);
