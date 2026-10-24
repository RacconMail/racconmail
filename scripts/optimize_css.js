const fs = require('fs');
const path = require('path');

const cssPath = path.join(__dirname, '..', 'public', 'css', 'raccoon-mail.css');
let css = fs.readFileSync(cssPath, 'utf8');

const fluidSystem = `
/* ==========================================================================
   ULTRA-FAST & FLUID PERFORMANCE SYSTEM (60-120 FPS FLUIDITY & ZERO-LAG)
   ========================================================================== */
html {
  scroll-behavior: smooth !important;
  text-rendering: optimizeLegibility !important;
  -webkit-font-smoothing: antialiased !important;
  -moz-osx-font-smoothing: grayscale !important;
}

* {
  -webkit-tap-highlight-color: transparent !important;
}

/* Eliminate 300ms mobile touch delay */
a, button, input, select, textarea, [role="button"], label, summary {
  touch-action: manipulation;
}

/* Fluid spring micro-interactions on buttons & interactive elements */
.btn-primary, .btn-secondary, button, [role="button"], a.btn {
  transition: transform 0.16s cubic-bezier(0.16, 1, 0.3, 1), 
              box-shadow 0.16s cubic-bezier(0.16, 1, 0.3, 1), 
              background-color 0.16s ease, 
              border-color 0.16s ease, 
              opacity 0.16s ease !important;
}

.btn-primary:active, .btn-secondary:active, button:active, [role="button"]:active {
  transform: scale(0.97) translateZ(0) !important;
}

/* GPU Hardware Acceleration for Smooth Scrolling & Compositing */
.gpu-accelerated, .raccoon-anim, .cookie-roller, .cookie-actor, .phone-device-container, .phone-chassis {
  transform: translateZ(0);
  will-change: transform;
  backface-visibility: hidden;
  perspective: 1000px;
}

/* Off-screen Content Visibility Optimization: Browser skips rendering below-the-fold content until scrolled into view */
.content-auto {
  content-visibility: auto;
  contain-intrinsic-size: 1px 600px;
}

/* Silky Smooth Momentum Scrolling */
.smooth-scroll, .phone-scroll, .overflow-y-auto, .overflow-x-auto {
  -webkit-overflow-scrolling: touch !important;
  scroll-behavior: smooth !important;
  overscroll-behavior: contain;
}
`;

if (!css.includes('ULTRA-FAST & FLUID PERFORMANCE SYSTEM')) {
  css = fluidSystem + '\n' + css;
  fs.writeFileSync(cssPath, css, 'utf8');
  console.log('Successfully injected ultra-fast & fluid performance system into raccoon-mail.css!');
} else {
  console.log('raccoon-mail.css already contains fluid performance system!');
}
