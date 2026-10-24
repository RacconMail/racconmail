const fs = require('fs');
const path = require('path');

const indexPath = path.join(__dirname, '..', 'public', 'index.html');
let html = fs.readFileSync(indexPath, 'utf8');

// 1. Add Privacy link to header
if (!html.includes('href="#compare"')) {
  html = html.replace(
    '<a href="#app" class="text-xs font-bold text-[#333333] hover:text-slate-900 transition px-2.5 py-1.5">Apps</a>',
    '<a href="#compare" class="text-xs font-bold text-[#333333] hover:text-slate-900 transition px-2.5 py-1.5 flex items-center gap-1"><i class="fas fa-shield-alt text-xs text-slate-500"></i><span>Privacy</span></a>\n          <a href="#app" class="text-xs font-bold text-[#333333] hover:text-slate-900 transition px-2.5 py-1.5">Apps</a>'
  );
}

// 2. Build the comparison section
const compareSection = `
    <!-- SECURITY & PRIVACY COMPARISON: RACCON MAIL VS GMAIL & OUTLOOK -->
    <section id="compare" class="w-full py-16 sm:py-24 bg-gradient-to-b from-white via-slate-50 to-white border-t border-slate-200/80">
      <div class="mx-auto max-w-6xl px-4 sm:px-6">
        <div class="text-center max-w-3xl mx-auto mb-12 sm:mb-16">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-900/5 border border-slate-900/10 text-slate-800 text-xs font-bold tracking-wide uppercase mb-3">
            <i class="fas fa-shield-halved text-emerald-600"></i> Why Raccon Mail
          </div>
          <h2 class="text-2xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mb-4">
            Radical Privacy vs. The Big Tech Surveillance Model
          </h2>
          <p class="text-sm sm:text-base text-slate-600 font-medium leading-relaxed">
            Big tech email providers treat your inbox like an ad-targeting database. Raccon Mail was engineered from the ground up to keep your personal correspondence truly private.
          </p>
        </div>

        <!-- Comparison Table (Responsive) -->
        <div class="overflow-x-auto rounded-2xl border border-slate-200/90 bg-white shadow-xl shadow-slate-100">
          <table class="w-full text-left border-collapse min-w-[640px]">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-900 text-white">
                <th class="py-4 px-6 text-xs sm:text-sm font-bold uppercase tracking-wider w-1/3">Security & Privacy Feature</th>
                <th class="py-4 px-6 text-xs sm:text-sm font-bold uppercase tracking-wider w-1/4 bg-emerald-700/90 text-white border-l border-r border-emerald-600/50">
                  <div class="flex items-center gap-2">
                    <img src="/raccoon-logo.png" alt="Raccon Mail" class="h-5 w-5 object-contain inline-block" />
                    <span>Raccon Mail</span>
                  </div>
                </th>
                <th class="py-4 px-6 text-xs sm:text-sm font-bold uppercase tracking-wider w-1/5 text-slate-300">Gmail</th>
                <th class="py-4 px-6 text-xs sm:text-sm font-bold uppercase tracking-wider w-1/5 text-slate-300">Outlook</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs sm:text-sm">
              <tr class="hover:bg-slate-50/80 transition">
                <td class="py-4 px-6 font-semibold text-slate-900">
                  <div class="font-bold text-slate-900">Ad Profiling & Content Scanning</div>
                  <div class="text-[11px] text-slate-500 font-normal">Does the provider scan email text to build an advertising dossier?</div>
                </td>
                <td class="py-4 px-6 bg-emerald-50/40 border-l border-r border-emerald-100 text-emerald-800 font-bold">
                  <i class="fas fa-check-circle text-emerald-600 mr-1.5"></i> 100% Zero Scanning & No Ads
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <i class="fas fa-times-circle text-rose-500 mr-1.5"></i> Scans for targeted ads
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <i class="fas fa-times-circle text-rose-500 mr-1.5"></i> Targeted ads in inbox
                </td>
              </tr>

              <tr class="hover:bg-slate-50/80 transition">
                <td class="py-4 px-6 font-semibold text-slate-900">
                  <div class="font-bold text-slate-900">Spy Tracker & Pixel Stripping</div>
                  <div class="text-[11px] text-slate-500 font-normal">Blocks hidden 1x1 marketing pixels that report when & where you opened an email.</div>
                </td>
                <td class="py-4 px-6 bg-emerald-50/40 border-l border-r border-emerald-100 text-emerald-800 font-bold">
                  <i class="fas fa-check-circle text-emerald-600 mr-1.5"></i> Built-in Tracker Stripper
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <i class="fas fa-exclamation-triangle text-amber-500 mr-1.5"></i> Proxies images (sender still logs)
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <i class="fas fa-exclamation-triangle text-amber-500 mr-1.5"></i> Prompts per sender
                </td>
              </tr>

              <tr class="hover:bg-slate-50/80 transition">
                <td class="py-4 px-6 font-semibold text-slate-900">
                  <div class="font-bold text-slate-900">Disposable Burrow Aliases</div>
                  <div class="text-[11px] text-slate-500 font-normal">Instant shield addresses to isolate newsletters, services, and online trials.</div>
                </td>
                <td class="py-4 px-6 bg-emerald-50/40 border-l border-r border-emerald-100 text-emerald-800 font-bold">
                  <i class="fas fa-check-circle text-emerald-600 mr-1.5"></i> Unlimited Native Aliases
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <i class="fas fa-minus-circle text-slate-400 mr-1.5"></i> Plus-addressing only (+tag)
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <i class="fas fa-minus-circle text-slate-400 mr-1.5"></i> Max 10 linked aliases/year
                </td>
              </tr>

              <tr class="hover:bg-slate-50/80 transition">
                <td class="py-4 px-6 font-semibold text-slate-900">
                  <div class="font-bold text-slate-900">Cryptographic Key Sovereignty</div>
                  <div class="text-[11px] text-slate-500 font-normal">Who holds the master keys to unlock and read your stored messages?</div>
                </td>
                <td class="py-4 px-6 bg-emerald-50/40 border-l border-r border-emerald-100 text-emerald-800 font-bold">
                  <i class="fas fa-check-circle text-emerald-600 mr-1.5"></i> Client-Side PGP / End-to-End
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <i class="fas fa-times-circle text-rose-500 mr-1.5"></i> Google holds master keys
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <i class="fas fa-times-circle text-rose-500 mr-1.5"></i> Microsoft holds master keys
                </td>
              </tr>

              <tr class="hover:bg-slate-50/80 transition">
                <td class="py-4 px-6 font-semibold text-slate-900">
                  <div class="font-bold text-slate-900">Data Residency & Legal Jurisdiction</div>
                  <div class="text-[11px] text-slate-500 font-normal">Protection from warrantless extraterritorial government surveillance.</div>
                </td>
                <td class="py-4 px-6 bg-emerald-50/40 border-l border-r border-emerald-100 text-emerald-800 font-bold">
                  <i class="fas fa-check-circle text-emerald-600 mr-1.5"></i> 100% EU GDPR / No US CLOUD Act
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <i class="fas fa-times-circle text-rose-500 mr-1.5"></i> US FISA 702 & CLOUD Act
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <i class="fas fa-times-circle text-rose-500 mr-1.5"></i> US FISA 702 & CLOUD Act
                </td>
              </tr>

              <tr class="hover:bg-slate-50/80 transition">
                <td class="py-4 px-6 font-semibold text-slate-900">
                  <div class="font-bold text-slate-900">Open Source Transparency</div>
                  <div class="text-[11px] text-slate-500 font-normal">Is the codebase publicly auditable by independent security researchers?</div>
                </td>
                <td class="py-4 px-6 bg-emerald-50/40 border-l border-r border-emerald-100 text-emerald-800 font-bold">
                  <i class="fas fa-check-circle text-emerald-600 mr-1.5"></i> Public GitHub Repository
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <i class="fas fa-times-circle text-rose-500 mr-1.5"></i> Closed proprietary black box
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <i class="fas fa-times-circle text-rose-500 mr-1.5"></i> Closed proprietary black box
                </td>
              </tr>

              <tr class="hover:bg-slate-50/80 transition">
                <td class="py-4 px-6 font-semibold text-slate-900">
                  <div class="font-bold text-slate-900">Offline Autonomy & Multi-Platform</div>
                  <div class="text-[11px] text-slate-500 font-normal">Signed standalone desktop binaries and verified Android packages.</div>
                </td>
                <td class="py-4 px-6 bg-emerald-50/40 border-l border-r border-emerald-100 text-emerald-800 font-bold">
                  <i class="fas fa-check-circle text-emerald-600 mr-1.5"></i> Signed Windows EXE + Android APK
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <i class="fas fa-minus-circle text-slate-400 mr-1.5"></i> Web browser / Closed Store only
                </td>
                <td class="py-4 px-6 text-slate-600">
                  <i class="fas fa-minus-circle text-slate-400 mr-1.5"></i> Heavy telemetry desktop client
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- 3 Feature Highlight Cards Under Table -->
        <div class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="p-6 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col justify-between">
            <div>
              <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg mb-4">
                <i class="fas fa-mask"></i>
              </div>
              <h3 class="text-base font-bold text-slate-900 mb-2">Stealth Burrow Aliases</h3>
              <p class="text-xs text-slate-600 leading-relaxed">
                Never hand out your primary inbox. Spawn disposable addresses on the fly; if a vendor leaks or spams your alias, seal the burrow with one click.
              </p>
            </div>
            <div class="mt-4 text-[11px] font-bold text-emerald-700 flex items-center gap-1">
              <span>Instant defense against spam breaches</span> &rarr;
            </div>
          </div>

          <div class="p-6 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col justify-between">
            <div>
              <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg mb-4">
                <i class="fas fa-ban"></i>
              </div>
              <h3 class="text-base font-bold text-slate-900 mb-2">Zero-Pixel Spy Stripper</h3>
              <p class="text-xs text-slate-600 leading-relaxed">
                Email marketers embed microscopic 1x1 GIF trackers to harvest your IP address, device model, and time of reading. Raccon Mail strips them before rendering.
              </p>
            </div>
            <div class="mt-4 text-[11px] font-bold text-blue-700 flex items-center gap-1">
              <span>Silent reading with total anonymity</span> &rarr;
            </div>
          </div>

          <div class="p-6 rounded-2xl bg-white border border-slate-200/80 shadow-xs flex flex-col justify-between">
            <div>
              <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg mb-4">
                <i class="fas fa-key"></i>
              </div>
              <h3 class="text-base font-bold text-slate-900 mb-2">Client-Side PGP Ready</h3>
              <p class="text-xs text-slate-600 leading-relaxed">
                True end-to-end encryption means only your recipient has the mathematical key to decrypt your message. Neither ISP nor server operators can peek.
              </p>
            </div>
            <div class="mt-4 text-[11px] font-bold text-purple-700 flex items-center gap-1">
              <span>Cryptographic privacy guaranteed</span> &rarr;
            </div>
          </div>
        </div>

        <div class="mt-12 text-center">
          <a href="/auth?tab=register" onclick="openAuthModal('register'); return false;" class="inline-flex items-center gap-2 rounded-full bg-slate-900 hover:bg-black text-white px-7 py-3 text-sm font-bold shadow-lg shadow-slate-900/10 hover:shadow-slate-900/20 transition transform active:scale-95">
            <i class="fas fa-user-shield text-emerald-400"></i>
            <span>Create Your Free Private Raccon Mail Account</span>
            <i class="fas fa-arrow-right text-xs text-white/70"></i>
          </a>
        </div>
      </div>
    </section>
`;

// 3. Insert before footer
const footerIdx = html.indexOf('<!-- HARMONY ULTRA-MODERN LEGAL & COMMUNITY FOOTER -->');
if (footerIdx !== -1) {
  html = html.substring(0, footerIdx) + compareSection + '\n    ' + html.substring(footerIdx);
  fs.writeFileSync(indexPath, html, 'utf8');
  console.log('Successfully inserted compareSection and updated navbar!');
} else {
  console.error('Footer comment not found in public/index.html');
  process.exit(1);
}
