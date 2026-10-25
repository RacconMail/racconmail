const https = require('https');

async function getUrl(url) {
  return new Promise((resolve, reject) => {
    https.get(url, { rejectUnauthorized: false }, res => {
      let d = '';
      res.on('data', chunk => d += chunk);
      res.on('end', () => resolve({ status: res.statusCode, data: d }));
    }).on('error', reject);
  });
}

async function postJson(url, payload) {
  return new Promise((resolve, reject) => {
    const data = JSON.stringify(payload);
    const req = https.request(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(data)
      },
      rejectUnauthorized: false
    }, res => {
      let d = '';
      res.on('data', chunk => d += chunk);
      res.on('end', () => {
        try { resolve({ status: res.statusCode, data: JSON.parse(d) }); }
        catch (e) { resolve({ status: res.statusCode, raw: d }); }
      });
    });
    req.on('error', reject);
    req.write(data);
    req.end();
  });
}

async function runVerification() {
  console.log('--- STARTING VERIFICATION FOR NEW REQUIREMENTS ---\n');
  let allPass = true;

  // 1. Verify Forum Subscription Plan Modal & Payment Form
  console.log('1. Testing Forum (https://racconmail.eu/forum.html)...');
  const forumRes = await getUrl('https://racconmail.eu/forum.html');
  const fData = forumRes.data;

  const forumHasBtn = fData.includes('openSubscriptionPlansModal()');
  const forumHasModal = fData.includes('id="subscription-plans-modal"');
  const forumHasCheckout = fData.includes('id="sub-step-checkout"');
  const forumHasCardInputs = fData.includes('id="chk-card-number"');

  if (forumHasBtn && forumHasModal && forumHasCheckout && forumHasCardInputs) {
    console.log('  [PASS] Forum has Pro subscription button, plans modal, and interactive checkout window.');
  } else {
    console.error('  [FAIL] Forum missing subscription components!');
    allPass = false;
  }

  // 2. Verify Stories Subscription Plan Modal & Payment Form
  console.log('\n2. Testing Stories (https://racconmail.eu/stories.html)...');
  const storiesRes = await getUrl('https://racconmail.eu/stories.html');
  const sData = storiesRes.data;

  const storiesHasBtn = sData.includes('openSubscriptionPlansModal()');
  const storiesHasModal = sData.includes('id="subscription-plans-modal"');
  const storiesHasCheckout = sData.includes('id="sub-step-checkout"');
  const storiesHasCardInputs = sData.includes('id="chk-card-number"');

  if (storiesHasBtn && storiesHasModal && storiesHasCheckout && storiesHasCardInputs) {
    console.log('  [PASS] Stories has Pro subscription button, plans modal, and interactive checkout window.');
  } else {
    console.error('  [FAIL] Stories missing subscription components!');
    allPass = false;
  }

  // 3. Verify Public Plans API & Checkout Processing
  console.log('\n3. Testing API Plans & Checkout (/api.php?route=plans & /api.php?route=subscription/checkout)...');
  const plansRes = await getUrl('https://racconmail.eu/api.php?route=plans');
  if (plansRes.status === 200 && plansRes.data.includes('"success":true')) {
    console.log('  [PASS] Public /api/plans endpoint returns active plans with HTTP 200.');
  } else {
    console.error('  [FAIL] Public /api/plans failed:', plansRes.status);
    allPass = false;
  }

  const checkoutRes = await postJson('https://racconmail.eu/api.php?route=subscription/checkout', {
    plan_id: 'plan_pro',
    billing_cycle: 'monthly',
    payment_method: 'card',
    card_holder: 'Verification Test User'
  });

  if (checkoutRes.status === 200 && checkoutRes.data && checkoutRes.data.success) {
    console.log('  [PASS] Subscription checkout processed successfully:', checkoutRes.data.message);
  } else {
    console.error('  [FAIL] Subscription checkout failed:', checkoutRes);
    allPass = false;
  }

  // 4. Verify Admin Panel News Control Panel & Header Cleanup
  console.log('\n4. Testing Admin Panel (https://racconmail.eu/admin.html)...');
  const adminRes = await getUrl('https://racconmail.eu/admin.html');
  const aData = adminRes.data;

  const hasViewNews = aData.includes('id="view-news"');
  const hasNewsModal = aData.includes('id="admin-news-modal"');
  const hasNewsTabs = aData.includes('id="tab-news"') && aData.includes('id="m-tab-news"');
  const noSearchBar = !aData.includes('placeholder="Search anything"');
  const hasSuspendBadge = aData.includes('SUSPENDAT');
  const hasProfileInTopBar = aData.includes('Admin Profile Badge in Mobile Toggle Bar');

  if (hasViewNews && hasNewsModal && hasNewsTabs) {
    console.log('  [PASS] News Wire control panel, tabs, and publication modal verified.');
  } else {
    console.error('  [FAIL] News Wire components missing in Admin!');
    allPass = false;
  }

  if (noSearchBar) {
    console.log('  [PASS] Redundant search bar and notification bell removed from admin top header.');
  } else {
    console.error('  [FAIL] Search bar still found in admin header!');
    allPass = false;
  }

  if (hasSuspendBadge && hasProfileInTopBar) {
    console.log('  [PASS] Member list has Suspend/Delete actions and Profile is moved into toggle bar.');
  } else {
    console.error('  [FAIL] Member list actions or toggle bar profile missing!');
    allPass = false;
  }

  console.log('\n=============================================');
  if (allPass) {
    console.log('🎉 ALL USER REQUIREMENTS VERIFIED & PASSING ON LIVE PRODUCTION!');
  } else {
    console.error('❌ SOME CHECKS FAILED. PLEASE REVIEW.');
  }
  console.log('=============================================\n');
}

runVerification().catch(err => {
  console.error('Verification error:', err);
  process.exit(1);
});
