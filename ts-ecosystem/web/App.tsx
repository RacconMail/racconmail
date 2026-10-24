/**
 * 🦝 Raccon Mail - Main React + TypeScript Web Application
 * Full-stack TypeScript architecture connecting to live API
 */

import React, { useState, useEffect, useCallback } from 'react';
import { MailMessage, FolderType } from '../types/mail.js';
import { InboxView } from './components/InboxView.js';
import { EmailReader } from './components/EmailReader.js';
import { MobileActionSheet } from './components/MobileActionSheet.js';

export const App: React.FC = () => {
  const [currentFolder, setCurrentFolder] = useState<FolderType>('Inbox');
  const [messages, setMessages] = useState<MailMessage[]>([]);
  const [selectedMessage, setSelectedMessage] = useState<MailMessage | null>(null);
  const [actionSheetMessage, setActionSheetMessage] = useState<MailMessage | null>(null);
  const [isSheetOpen, setIsSheetOpen] = useState<boolean>(false);
  const [toastMessage, setToastMessage] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState<string>('');

  const showToast = (msg: string) => {
    setToastMessage(msg);
    setTimeout(() => {
      setToastMessage(null);
    }, 2600);
  };

  const loadFolderMessages = useCallback(async (folder: FolderType) => {
    try {
      const res = await fetch(`/api/messages?folder=${encodeURIComponent(folder.toLowerCase())}`);
      if (res.ok) {
        const data = await res.json();
        const loaded: MailMessage[] = data.messages || [];
        setMessages(loaded);
        if (loaded.length > 0 && !selectedMessage) {
          setSelectedMessage(loaded[0]);
        }
      }
    } catch (e) {
      console.error('Failed to load messages:', e);
    }
  }, [selectedMessage]);

  useEffect(() => {
    loadFolderMessages(currentFolder);
  }, [currentFolder, loadFolderMessages]);

  const handleDelete = async (id: string) => {
    const isTrash = currentFolder.toLowerCase() === 'trash';
    try {
      if (isTrash) {
        await fetch(`/api/messages/${encodeURIComponent(id)}?permanent=1`, { method: 'DELETE' });
        showToast('Message permanently deleted from Trash 🗑️');
      } else {
        await fetch(`/api/messages/${encodeURIComponent(id)}/trash`, { method: 'POST' });
        showToast('Message moved to Trash 🗑️');
      }
      setMessages(prev => prev.filter(m => m.id !== id));
      if (selectedMessage?.id === id) {
        const remaining = messages.filter(m => m.id !== id);
        setSelectedMessage(remaining.length > 0 ? remaining[0] : null);
      }
    } catch (e) {
      console.error('Delete failed:', e);
    }
  };

  const handleStar = async (id: string) => {
    setMessages(prev => prev.map(m => m.id === id ? { ...m, isStarred: !m.isStarred } : m));
    if (selectedMessage?.id === id) {
      setSelectedMessage(prev => prev ? { ...prev, isStarred: !prev.isStarred } : null);
    }
    try {
      await fetch(`/api/messages/${encodeURIComponent(id)}/star`, { method: 'POST' });
    } catch (_) {}
  };

  const handleToggleRead = async (id: string) => {
    setMessages(prev => prev.map(m => m.id === id ? { ...m, isRead: !m.isRead } : m));
    try {
      await fetch(`/api/messages/${encodeURIComponent(id)}/read`, { method: 'POST' });
    } catch (_) {}
  };

  const filteredMessages = messages.filter(m => {
    if (!searchQuery.trim()) return true;
    const q = searchQuery.toLowerCase();
    return (
      (m.subject || '').toLowerCase().includes(q) ||
      (m.senderName || '').toLowerCase().includes(q) ||
      (m.sender || '').toLowerCase().includes(q)
    );
  });

  return (
    <div className="flex h-screen w-screen overflow-hidden bg-slate-100 font-sans antialiased text-slate-800">
      {/* Middle Column: Inbox Card List */}
      <div className={`w-full md:w-[440px] xl:w-[480px] flex flex-col bg-[#FDFDFE] border-r border-slate-200/80 ${selectedMessage ? 'hidden md:flex' : 'flex'}`}>
        {/* Header Search & Title */}
        <div className="p-4 border-b border-slate-100 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <img src="/raccoon-logo.svg" className="w-7 h-7 object-contain" alt="Logo" />
            <h2 className="font-extrabold text-base text-slate-900 tracking-tight">{currentFolder}</h2>
          </div>
          <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-600">
            {messages.length}
          </span>
        </div>

        {/* Postcard Message List */}
        <InboxView
          messages={filteredMessages}
          currentFolder={currentFolder}
          selectedId={selectedMessage?.id || null}
          onSelect={(msg) => setSelectedMessage(msg)}
          onHoldDelete={(msg) => {
            setActionSheetMessage(msg);
            setIsSheetOpen(true);
          }}
          onQuickDelete={(msg) => handleDelete(msg.id)}
        />
      </div>

      {/* Right Column: Centered Email Reader */}
      <div className={`flex-1 flex flex-col bg-white overflow-hidden ${selectedMessage ? 'flex' : 'hidden md:flex'}`}>
        {selectedMessage ? (
          <EmailReader
            message={selectedMessage}
            currentFolder={currentFolder}
            onBack={() => setSelectedMessage(null)}
            onDelete={handleDelete}
            onStar={handleStar}
            onReply={(msg) => console.log('Reply triggered for:', msg.id)}
          />
        ) : (
          <div className="flex-1 flex items-center justify-center p-8 text-center text-slate-400">
            <p className="font-medium text-sm">Select an email from the list to view</p>
          </div>
        )}
      </div>

      {/* Mobile Hold-to-Delete Action Bottom Sheet */}
      <MobileActionSheet
        message={actionSheetMessage}
        currentFolder={currentFolder}
        isOpen={isSheetOpen}
        onClose={() => {
          setIsSheetOpen(false);
          setActionSheetMessage(null);
        }}
        onDelete={handleDelete}
        onStar={handleStar}
        onToggleRead={handleToggleRead}
      />

      {/* Floating Live Toast Banner */}
      {toastMessage && (
        <div className="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-4 py-2.5 rounded-full bg-slate-900/90 text-white text-xs font-semibold shadow-lg backdrop-blur-md transition-all duration-300 pointer-events-none flex items-center gap-2">
          <span>{toastMessage}</span>
        </div>
      )}
    </div>
  );
};

export default App;
