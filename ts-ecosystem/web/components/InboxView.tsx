/**
 * 🦝 Raccon Mail - React + TypeScript Inbox Postcard View
 * Features touch hold-to-delete, quick card trash button, and zero container overflow
 */

import React, { useRef } from 'react';
import { MailMessage } from '../../types/mail.js';
import { cleanMimeEmailContent, parseEmailReplyAndQuote } from '../../shared/mailParser.js';

interface InboxViewProps {
  messages: MailMessage[];
  currentFolder: string;
  selectedId: string | null;
  onSelect: (msg: MailMessage) => void;
  onHoldDelete: (msg: MailMessage) => void;
  onQuickDelete: (msg: MailMessage) => void;
}

export const InboxView: React.FC<InboxViewProps> = ({
  messages,
  currentFolder,
  selectedId,
  onSelect,
  onHoldDelete,
  onQuickDelete
}) => {
  const longPressTimer = useRef<NodeJS.Timeout | null>(null);
  const touchStartPos = useRef<{ x: number; y: number }>({ x: 0, y: 0 });
  const touchMoved = useRef<boolean>(false);
  const longPressFired = useRef<boolean>(false);

  const visualArtworks = [
    { img: '/images/abstract_1.svg', badgeColor: 'bg-[#F45E86]', badgeIcon: '★' },
    { img: '/images/abstract_2.svg', badgeColor: 'bg-[#6C8DF6]', badgeIcon: '✦' },
    { img: '/images/abstract_3.svg', badgeColor: 'bg-[#FF7D54]', badgeIcon: '✹' },
    { img: '/images/abstract_4.svg', badgeColor: 'bg-[#8B5CF6]', badgeIcon: '⚡' }
  ];

  const handleTouchStart = (msg: MailMessage, e: React.TouchEvent) => {
    if (e.touches.length > 1) return;
    const touch = e.touches[0];
    touchStartPos.current = { x: touch.clientX, y: touch.clientY };
    touchMoved.current = false;
    longPressFired.current = false;

    if (longPressTimer.current) clearTimeout(longPressTimer.current);
    longPressTimer.current = setTimeout(() => {
      if (!touchMoved.current) {
        longPressFired.current = true;
        if (typeof navigator !== 'undefined' && navigator.vibrate) {
          try { navigator.vibrate(60); } catch (_) {}
        }
        onHoldDelete(msg);
      }
    }, 450); // 450ms hold
  };

  const handleTouchMove = (e: React.TouchEvent) => {
    if (!touchMoved.current && e.touches[0]) {
      const dx = Math.abs(e.touches[0].clientX - touchStartPos.current.x);
      const dy = Math.abs(e.touches[0].clientY - touchStartPos.current.y);
      if (dx > 10 || dy > 10) {
        touchMoved.current = true;
        if (longPressTimer.current) clearTimeout(longPressTimer.current);
      }
    }
  };

  const handleTouchEnd = (e: React.TouchEvent) => {
    if (longPressTimer.current) clearTimeout(longPressTimer.current);
    if (longPressFired.current) {
      if (e.cancelable) e.preventDefault();
      e.stopPropagation();
      setTimeout(() => { longPressFired.current = false; }, 200);
    }
  };

  return (
    <div className="flex-1 overflow-y-auto px-3 sm:px-5 py-4 min-w-0">
      {messages.length === 0 ? (
        <div className="p-12 text-center text-slate-400 select-none">
          <div className="w-16 h-16 rounded-3xl bg-slate-50 border border-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-300">
            <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
          </div>
          <p className="font-bold text-sm text-slate-700">No messages in {currentFolder}</p>
          <p className="text-xs text-slate-400 mt-1">Your folder is clean and up to date.</p>
        </div>
      ) : (
        messages.map((msg, idx) => {
          const isActive = msg.id === selectedId;
          const raw = cleanMimeEmailContent(msg.thread?.[msg.thread.length - 1]?.body || msg.preview || '');
          const parsed = parseEmailReplyAndQuote(raw);
          const snippetText = parsed.isReply && parsed.replyText ? parsed.replyText : raw;
          const cleanSnippet = snippetText.replace(/<[^>]+>/g, '').slice(0, 95).trim() + (snippetText.length > 95 ? '...' : '');
          const art = visualArtworks[idx % visualArtworks.length];
          const readMinutes = Math.max(1, Math.ceil((cleanSnippet.length + (msg.subject || '').length) / 25));

          return (
            <div
              key={msg.id}
              onClick={() => {
                if (!longPressFired.current) onSelect(msg);
              }}
              onTouchStart={(e) => handleTouchStart(msg, e)}
              onTouchMove={handleTouchMove}
              onTouchEnd={handleTouchEnd}
              onContextMenu={(e) => {
                e.preventDefault();
                onHoldDelete(msg);
              }}
              className={`p-4 sm:p-5 transition-all duration-200 cursor-pointer mb-3.5 relative overflow-hidden group select-none rounded-2xl border ${
                isActive
                  ? 'bg-indigo-50/70 border-indigo-200/90 shadow-xs'
                  : 'bg-white hover:bg-slate-50/80 border-slate-200/70'
              }`}
            >
              <div className="flex items-start justify-between gap-3.5">
                {/* Left Column: Metadata, Subject, Snippet, Tags */}
                <div className="flex-1 min-w-0 pr-1">
                  <div className="flex items-center justify-between mb-1.5">
                    <div className="flex items-center gap-2 text-xs text-slate-400 font-semibold font-mono">
                      <span>{msg.time || 'Today'}</span>
                      <span className="text-slate-300">•</span>
                      <span>{readMinutes} min read</span>
                    </div>
                    {/* Quick Delete Button for Smartphone and Desktop */}
                    <button
                      type="button"
                      onClick={(e) => {
                        e.stopPropagation();
                        onQuickDelete(msg);
                      }}
                      className="p-1.5 -mr-1 text-slate-300 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition cursor-pointer"
                      title={currentFolder.toLowerCase() === 'trash' ? 'Delete Permanently' : 'Delete / Move to Trash'}
                    >
                      <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>

                  {/* Bold Title */}
                  <h3 className="font-bold text-[15px] sm:text-base text-slate-900 leading-snug tracking-tight mb-1.5 line-clamp-2">
                    {msg.subject || '(no subject)'}
                  </h3>

                  {/* Body Snippet */}
                  <p className="text-xs text-slate-500 leading-relaxed line-clamp-2 mb-3">
                    {cleanSnippet || 'Dive into the email thread to review communication and attachments.'}
                  </p>

                  {/* Category Chips */}
                  <div className="flex items-center flex-wrap gap-1.5">
                    <span className="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-[#FFEFEA] text-[#E06748]">
                      {msg.senderName || 'Raccon Mail'}
                    </span>
                    <span className="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-[#FEF9EE] text-[#B48328]">
                      {currentFolder}
                    </span>
                    {msg.isStarred && (
                      <span className="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-[#FFF4ED] text-[#D96B43] flex items-center gap-1">
                        ★ Starred
                      </span>
                    )}
                  </div>
                </div>

                {/* Right Column: Visual Thumbnail */}
                <div className="relative shrink-0 w-24 sm:w-28 h-28 sm:h-32 rounded-2xl overflow-hidden shadow-xs border border-slate-200/60 bg-slate-100">
                  <img src={art.img} className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" alt="Artwork visual" />
                  <div className={`absolute top-2 right-2 w-7 h-7 rounded-xl ${art.badgeColor} flex items-center justify-center text-white text-xs shadow-sm font-bold`}>
                    {art.badgeIcon}
                  </div>
                </div>
              </div>
            </div>
          );
        })
      )}
    </div>
  );
};
