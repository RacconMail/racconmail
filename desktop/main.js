// 🦝 Raccon Mail - Official Desktop Client
const { app, BrowserWindow, Menu, Tray, nativeImage, shell } = require('electron');
const path = require('path');
const fs = require('fs');

let mainWindow = null;
let tray = null;

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1280,
    height: 850,
    minWidth: 900,
    minHeight: 600,
    title: 'Raccon Mail - Nocturnal Webmail',
    icon: path.join(__dirname, 'icon.png'),
    backgroundColor: '#FFFFFF',
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      spellcheck: true
    }
  });

  // Load live secure landing page without download prompts
  mainWindow.loadURL('https://racconmail.eu/?app=desktop');

  // Handle external links safely in system default browser
  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    if (url.startsWith('https://racconmail.eu')) {
      return { action: 'allow' };
    }
    shell.openExternal(url);
    return { action: 'deny' };
  });

  mainWindow.on('close', (event) => {
    if (!app.isQuitting) {
      event.preventDefault();
      mainWindow.hide();
    }
    return false;
  });
}

app.whenReady().then(() => {
  createWindow();

  // Create System Tray Icon
  try {
    const iconPath = path.join(__dirname, 'icon.png');
    if (fs.existsSync(iconPath)) {
      tray = new Tray(iconPath);
      const contextMenu = Menu.buildFromTemplate([
        { label: 'Open Raccon Mail', click: () => { mainWindow.show(); mainWindow.focus(); } },
        { label: 'Compose Postcard', click: () => { mainWindow.loadURL('https://racconmail.eu/inbox?compose=1'); mainWindow.show(); } },
        { type: 'separator' },
        { label: 'Quit Raccon Mail', click: () => { app.isQuitting = true; app.quit(); } }
      ]);
      tray.setToolTip('Raccon Mail');
      tray.setContextMenu(contextMenu);
      tray.on('click', () => { mainWindow.show(); mainWindow.focus(); });
    }
  } catch (err) {
    console.warn('System tray setup skipped:', err.message);
  }

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
    else mainWindow.show();
  });
});

app.on('before-quit', () => {
  app.isQuitting = true;
});
