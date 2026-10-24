/**
 * 🦝 Raccon Mail - Electron Desktop Client Main Process in TypeScript
 * Native OS integration for Windows, macOS, and Linux
 */

import { app, BrowserWindow, Menu, Tray, shell, ipcMain } from 'electron';
import * as path from 'path';

class RacconDesktopApp {
  private mainWindow: BrowserWindow | null = null;
  private tray: Tray | null = null;
  private isQuitting = false;

  public init(): void {
    // Single instance lock
    const gotLock = app.requestSingleInstanceLock();
    if (!gotLock) {
      app.quit();
      return;
    }

    app.on('second-instance', () => {
      if (this.mainWindow) {
        if (this.mainWindow.isMinimized()) this.mainWindow.restore();
        this.mainWindow.show();
        this.mainWindow.focus();
      }
    });

    app.whenReady().then(() => {
      this.createMainWindow();
      this.setupTray();
      this.setupIpc();
    });

    app.on('before-quit', () => {
      this.isQuitting = true;
    });

    app.on('window-all-closed', () => {
      if (process.platform !== 'darwin') {
        app.quit();
      }
    });
  }

  private createMainWindow(): void {
    this.mainWindow = new BrowserWindow({
      width: 1280,
      height: 850,
      minWidth: 900,
      minHeight: 600,
      title: 'Raccon Mail — Nocturnal & Masked Webmail',
      icon: path.join(__dirname, 'icon.png'),
      backgroundColor: '#FFFFFF',
      webPreferences: {
        preload: path.join(__dirname, 'preload.js'),
        nodeIntegration: false,
        contextIsolation: true,
        spellcheck: true
      }
    });

    // Load production URL or local bundle
    this.mainWindow.loadURL('https://racconmail.eu/inbox');

    // Safe handling of external links
    this.mainWindow.webContents.setWindowOpenHandler(({ url }) => {
      if (url.startsWith('https://racconmail.eu')) {
        return { action: 'allow' };
      }
      shell.openExternal(url);
      return { action: 'deny' };
    });

    // Minimize to tray on close
    this.mainWindow.on('close', (event) => {
      if (!this.isQuitting) {
        event.preventDefault();
        this.mainWindow?.hide();
      }
    });
  }

  private setupTray(): void {
    try {
      const iconPath = path.join(__dirname, 'icon.png');
      this.tray = new Tray(iconPath);
      const contextMenu = Menu.buildFromTemplate([
        {
          label: 'Open Raccon Mail',
          click: () => {
            this.mainWindow?.show();
            this.mainWindow?.focus();
          }
        },
        { type: 'separator' },
        {
          label: 'Check for New Mail',
          click: () => {
            this.mainWindow?.webContents.send('trigger-sync');
          }
        },
        { type: 'separator' },
        {
          label: 'Quit',
          click: () => {
            this.isQuitting = true;
            app.quit();
          }
        }
      ]);
      this.tray.setToolTip('Raccon Mail - Nocturnal Webmail');
      this.tray.setContextMenu(contextMenu);
      this.tray.on('double-click', () => {
        this.mainWindow?.show();
        this.mainWindow?.focus();
      });
    } catch (e) {
      console.warn('System tray setup skipped:', e);
    }
  }

  private setupIpc(): void {
    ipcMain.handle('get-app-version', () => app.getVersion());
    ipcMain.handle('show-native-notification', (_, { title, body }) => {
      // Native notification
      console.log(`[Notification] ${title}: ${body}`);
      return true;
    });
  }
}

const desktopApp = new RacconDesktopApp();
desktopApp.init();
