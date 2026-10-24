/**
 * 🦝 Raccon Mail - React + TypeScript Mobile Action Bottom Sheet
 * Effortless smartphone hold-to-delete bottom sheet with permanent trash purge option
 */

import React from 'react';
import { MailMessage } from '../../types/mail.js';

interface MobileActionSheetProps {
  message: MailMessage | null;
  currentFolder: string;
  isOpen: boolean;
  onClose: () => void;
  onDelete: (id: string) => Promise<void>;
  onStar: (id: string) => Promise<void>;
  onToggleRead: (id: string) => Promise<void>;
}

export const MobileActionSheet: React.FC<MobileActionSheetProps> = ({
  message,
  currentFolder,
  isOpen,
  onClose,
  onDelete,
  onStar,
  onToggleRead
}) => {
  if (!isOpen || !message) return null;

  const isTrash = currentFolder.toLowerCase() === 'trash' || message.isTrashed;

  return (
    <div
      onClick={onClose}
      className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 transition-opacity duration-300 flex items-end sm:items-center justify-center p-0 sm:p-4"
    >
      <div
        onClick={(e) => e.stopPropagation()}
        className="w-full sm:max-w-md bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl p-5 sm:p-6 border border-slate-200/80 animate-slide-up"
      >
        {/* Drag indicator pill for smartphone */}
        <div className="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mb-4 sm:hidden" />

        {/* Header: Subject & Sender */}
        <div className="flex items-start gap-3 pb-4 border-b border-slate-100">
          <div className="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0">
            <img src="/raccoon-logo.svg" className="w-6 h-6 object-contain" alt="Raccoon Logo" />
          </div>
          <div className="flex-1 min-w-0">
            <div className="text-[10px] font-extrabold uppercase tracking-wider text-[#6c4ff5]">
              {isTrash ? 'Trash Folder Options' : 'Email Options'}
            </div>
            <h3 className="text-sm font-bold text-slate-900 truncate">
              {message.subject || '(no subject)'}
            </h3>
            <p className="text-xs text-slate-400 truncate">
              {message.senderName || message.sender}
            </p>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-100 transition cursor-pointer"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Action Buttons */}
        <div className="py-3 space-y-2">
          {/* Delete Option (Permanently if in Trash, or move to Trash) */}
          <button
            onClick={async () => {
              onClose();
              await onDelete(message.id);
            }}
            className="w-full flex items-center justify-between p-3.5 rounded-2xl bg-rose-50/80 hover:bg-rose-100 text-rose-600 transition font-bold text-sm cursor-pointer border border-rose-200/60 active:scale-98"
          >
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-xl bg-rose-500 text-white flex items-center justify-center shadow-xs">
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </div>
              <div className="text-left">
                <div className="font-extrabold text-rose-700">
                  {isTrash ? 'Delete Permanently' : 'Delete / Move to Trash'}
                </div>
                <div className="text-[11px] text-rose-500/80 font-normal">
                  {isTrash ? 'Purge permanently from SQLite storage' : 'Move message to Trash folder'}
                </div>
              </div>
            </div>
            <svg className="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>

          {/* Toggle Star */}
          <button
            onClick={async () => {
              onClose();
              await onStar(message.id);
            }}
            className="w-full flex items-center justify-between p-3.5 rounded-2xl hover:bg-slate-50 text-slate-700 transition font-bold text-sm cursor-pointer border border-slate-100 active:scale-98"
          >
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-xl bg-amber-50 text-amber-500 border border-amber-200/60 flex items-center justify-center">
                <svg className="w-4 h-4" fill={message.isStarred ? 'currentColor' : 'none'} stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                </svg>
              </div>
              <div className="text-left">
                <div className="font-bold text-slate-800">
                  {message.isStarred ? 'Unstar Message' : 'Star Message'}
                </div>
                <div className="text-[11px] text-slate-400 font-normal">Mark as important</div>
              </div>
            </div>
            <svg className="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>

          {/* Mark Unread / Read */}
          <button
            onClick={async () => {
              onClose();
              await onToggleRead(message.id);
            }}
            className="w-full flex items-center justify-between p-3.5 rounded-2xl hover:bg-slate-50 text-slate-700 transition font-bold text-sm cursor-pointer border border-slate-100 active:scale-98"
          >
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-200/60 flex items-center justify-center">
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
              </div>
              <div className="text-left">
                <div className="font-bold text-slate-800">
                  {message.isRead ? 'Mark as Unread' : 'Mark as Read'}
                </div>
                <div className="text-[11px] text-slate-400 font-normal">Keep for later review</div>
              </div>
            </div>
            <svg className="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>

        {/* Cancel Button */}
        <button
          type="button"
          onClick={onClose}
          className="w-full py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-sm transition mt-1 active:scale-98 cursor-pointer"
        >
          Cancel
        </button>
      </div>
    </div>
  );
};
