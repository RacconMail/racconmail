/**
 * 🦝 Raccon Mail - Electron Preload Script with Typed Context Bridge
 */

import { contextBridge, ipcRenderer } from 'electron';

export interface DesktopApi {
  getVersion: () => Promise<string>;
  showNotification: (title: string, body: string) => Promise<boolean>;
  onTriggerSync: (callback: () => void) => () => void;
}

const desktopApi: DesktopApi = {
  getVersion: () => ipcRenderer.invoke('get-app-version'),
  showNotification: (title: string, body: string) => 
    ipcRenderer.invoke('show-native-notification', { title, body }),
  onTriggerSync: (callback: () => void) => {
    const handler = () => callback();
    ipcRenderer.on('trigger-sync', handler);
    return () => {
      ipcRenderer.removeListener('trigger-sync', handler);
    };
  }
};

contextBridge.exposeInMainWorld('desktopApi', desktopApi);

declare global {
  interface Window {
    desktopApi: DesktopApi;
  }
}
