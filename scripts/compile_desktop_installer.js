const fs = require('fs');
const { execSync } = require('child_process');
const path = require('path');

const cscPaths = [
  'C:\\Windows\\Microsoft.NET\\Framework64\\v4.0.30319\\csc.exe',
  'C:\\Windows\\Microsoft.NET\\Framework\\v4.0.30319\\csc.exe'
];

let csc = cscPaths.find(p => fs.existsSync(p));
console.log('CSC compiler found:', csc);

if (csc) {
  const workDir = path.resolve(__dirname, '..');
  const inputLogo = path.join(workDir, 'public', 'raccoon-logo.png');
  const desktopDir = path.join(workDir, 'desktop');
  fs.copyFileSync(inputLogo, path.join(desktopDir, 'icon.png'));
  if (fs.existsSync(path.join(workDir, 'desktop_build', 'icon.ico'))) {
    fs.copyFileSync(path.join(workDir, 'desktop_build', 'icon.ico'), path.join(desktopDir, 'raccoon.ico'));
  }

  const outExe = path.join(workDir, 'public', 'downloads', 'RacconMail-Setup.exe');
  const programCs = path.join(desktopDir, 'Program.cs');
  const iconIco = path.join(desktopDir, 'raccoon.ico');
  const iconPng = path.join(desktopDir, 'icon.png');

  try {
    const cmd = `"${csc}" /target:winexe /win32icon:"${iconIco}" /resource:"${iconIco}",RacconIcon /resource:"${iconPng}",RacconLogo /out:"${outExe}" "${programCs}"`;
    console.log('Executing:', cmd);
    execSync(cmd, { stdio: 'inherit' });
    console.log('Successfully compiled RacconMail-Setup.exe, size:', fs.statSync(outExe).size);
    fs.copyFileSync(outExe, path.join(desktopDir, 'RacconMail-Setup.exe'));
    // Also copy to desktop_build
    fs.copyFileSync(outExe, path.join(workDir, 'desktop_build', 'RacconMail-Setup.exe'));
  } catch (err) {
    console.error('Compilation failed:', err.message);
  }
}
