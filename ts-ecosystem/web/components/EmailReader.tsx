/**
 * 🦝 Raccon Mail - React + TypeScript Email Reader Component
 * Perfectly centered, strictly bounded, responsive with zero container overflow
 */

import React, { useState } from 'react';
import { MailMessage } from '../../types/mail.js';
import { cleanMimeEmailContent, parseEmailReplyAndQuote } from '../../shared/mailParser.js';

interface EmailReaderProps {
  message: MailMessage;
  currentFolder: string;
  onBack: () => void;
  onDelete: (id: string) => Promise<void>;
  onStar: (id: string) => Promise<void>;
  onReply: (message: MailMessage) => void;
}

export const EmailReader: React.FC<EmailReaderProps> = ({
  message,
  currentFolder,
  onBack,
  onDelete,
  onStar,
  onReply
}) => {
  const [showQuotedHistory, setShowQuotedHistory] = useState<boolean>(false);
  const isTrash = currentFolder.toLowerCase() === 'trash' || message.isTrashed;

  return (
    <div className="flex-1 flex flex-col h-full bg-white overflow-hidden min-w-0">
      {/* Top Header Bar - Truncated, Responsive, Never Collides */}
      <header className="h-16 px-4 sm:px-6 border-b border-slate-100 flex items-center justify-between shrink-0 bg-white select-none z-20 gap-2">
        <div className="flex items-center gap-2.5 min-w-0 flex-1 mr-2">
          <button
            onClick={onBack}
            className="md:hidden p-1.5 rounded-lg border border-slate-200 text-slate-600 shrink-0 hover:bg-slate-50 transition"
            title="Back to inbox"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <h2 className="text-xs sm:text-sm font-bold text-slate-800 tracking-tight truncate max-w-[130px] sm:max-w-xs md:max-w-md">
            {message.subject || 'All Emails'}
          </h2>
        </div>

        {/* Action Buttons: Reply, Star, Trash */}
        <div className="flex items-center gap-1 shrink-0">
          <button
            onClick={() => onReply(message)}
            className="p-2 rounded-xl hover:bg-slate-100 text-slate-500 hover:text-slate-800 transition"
            title="Reply"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
            </svg>
          </button>
          <button
            onClick={() => onStar(message.id)}
            className={`p-2 rounded-xl hover:bg-slate-100 transition ${message.isStarred ? 'text-amber-500' : 'text-slate-400 hover:text-amber-500'}`}
            title="Star message"
          >
            <svg className="w-4 h-4" fill={message.isStarred ? 'currentColor' : 'none'} stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
          </button>
          <button
            onClick={() => onDelete(message.id)}
            className="p-2 rounded-xl hover:bg-rose-50 text-rose-500 hover:text-rose-600 transition"
            title={isTrash ? 'Permanently Delete' : 'Move to Trash'}
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>
      </header>

      {/* Reader Scrollable Content Area - Centered Layout */}
      <div className="flex-1 overflow-y-auto px-3 sm:px-6 py-4">
        <div className="max-w-3xl mx-auto w-full">
          {/* Prominent Centered Subject Title */}
          <div className="mb-5 text-center sm:text-left">
            <h1 className="text-lg sm:text-2xl font-extrabold text-slate-900 tracking-tight leading-snug break-words [word-break:break-word] overflow-hidden">
              {message.subject || '(no subject)'}
            </h1>
            <div className="flex items-center justify-center sm:justify-start gap-2 mt-2 text-xs text-slate-400 font-mono">
              <span className="inline-flex items-center px-2.5 py-0.5 rounded-full font-bold text-[10px] uppercase bg-slate-100 text-slate-600 border border-slate-200">
                {currentFolder}
              </span>
              <span>•</span>
              <span>{message.date || message.time || 'Recently'}</span>
            </div>
          </div>

          {/* Thread Cards Loop */}
          {(message.thread || []).map((card, idx) => {
            const cleaned = cleanMimeEmailContent(card.body);
            const parsed = parseEmailReplyAndQuote(cleaned);
            const isReply = Boolean(parsed.isReply || message.isReply || message.subject.toLowerCase().startsWith('re:'));
            const replyText = parsed.replyText || cleaned;
            const quotedText = parsed.quotedText || '';

            if (isReply && replyText) {
              return (
                <div
                  key={idx}
                  className="mb-6 rounded-2xl sm:rounded-3xl bg-gradient-to-b from-indigo-50/50 via-white to-slate-50/40 border border-indigo-100/90 p-4 sm:p-6 shadow-xs relative overflow-hidden break-words w-full"
                >
                  {/* Status Badges Header */}
                  <div className="flex flex-wrap items-center justify-between gap-2 pb-3.5 border-b border-indigo-100/60 mb-4">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold bg-[#6c4ff5] text-white shadow-2xs">
                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                        </svg>
                        <span>Reply Received</span>
                      </span>
                      <span className="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 uppercase tracking-wide">
                        Updates
                      </span>
                    </div>
                    <span className="text-xs font-mono text-slate-400 shrink-0">
                      {card.date || message.date || 'Recently'}
                    </span>
                  </div>

                  {/* Sender Profile Row */}
                  <div className="flex items-start gap-3 mb-4 min-w-0">
                    <div className="w-10 h-10 rounded-full bg-indigo-100 border border-indigo-200 flex items-center justify-center overflow-hidden shrink-0">
                      <img src={card.avatar || '/raccoon-logo.svg'} className="w-7 h-7 object-contain" alt="Avatar" />
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between gap-2">
                        <h4 className="font-extrabold text-sm sm:text-base text-slate-900 truncate">
                          {card.sender}
                        </h4>
                        <button
                          onClick={() => onDelete(message.id)}
                          className="hover:text-rose-600 p-1 text-slate-300 transition"
                          title="Delete"
                        >
                          <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      </div>
                      <div className="text-[11px] text-slate-400 flex flex-wrap items-center gap-1.5 mt-0.5 break-all">
                        <span className="font-mono truncate max-w-full">to {card.to}</span>
                        <span>•</span>
                        <span className="text-emerald-600 font-medium inline-flex items-center gap-1 shrink-0">
                          <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                          Verified Delivery
                        </span>
                      </div>
                    </div>
                  </div>

                  {/* Clean Response Message Box ("Chenar" - Text NEVER exceeds borders) */}
                  <div className="p-4 sm:p-6 rounded-2xl bg-white border border-slate-200/90 shadow-2xs relative overflow-hidden break-words w-full">
                    <div className="text-[10px] font-extrabold text-[#6c4ff5] uppercase tracking-wider mb-2.5 flex items-center gap-1.5">
                      <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                      </svg>
                      <span>Response Message</span>
                    </div>
                    <div className="text-sm sm:text-base font-semibold text-slate-900 leading-relaxed whitespace-pre-wrap break-words [word-break:break-word] overflow-hidden select-text">
                      {replyText}
                    </div>
                  </div>

                  {/* Collapsible Quoted Email History */}
                  {quotedText && (
                    <div className="mt-4 pt-3 border-t border-slate-100/90 w-full overflow-hidden">
                      <button
                        type="button"
                        onClick={() => setShowQuotedHistory(!showQuotedHistory)}
                        className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold text-slate-500 hover:text-slate-800 bg-slate-100 hover:bg-slate-200/70 transition cursor-pointer border border-slate-200/60"
                      >
                        <span className="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                        <span>{showQuotedHistory ? 'Hide quoted email history' : 'Show quoted email history'}</span>
                        <svg
                          className={`w-3.5 h-3.5 text-slate-400 transition-transform duration-200 ${showQuotedHistory ? 'rotate-180' : ''}`}
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                        </svg>
                      </button>
                      {showQuotedHistory && (
                        <div className="mt-3 p-3.5 sm:p-5 rounded-2xl bg-slate-50 border-l-4 border-indigo-400 text-xs text-slate-600 font-mono leading-relaxed whitespace-pre-wrap break-words [word-break:break-word] max-h-96 overflow-y-auto overflow-x-hidden select-text w-full">
                          {quotedText}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              );
            }

            // Standard Non-Reply Email Card
            return (
              <div
                key={idx}
                className="mb-6 rounded-2xl bg-white border border-slate-200/80 p-4 sm:p-6 shadow-2xs relative overflow-hidden break-words w-full"
              >
                <div className="flex items-start gap-3 mb-3.5 min-w-0">
                  <img src={card.avatar || '/raccoon-logo.svg'} className="h-9 w-9 rounded-full object-cover shrink-0 border border-slate-200" alt="Avatar" />
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between gap-2">
                      <span className="font-bold text-sm text-slate-900 truncate">{card.sender}</span>
                      <div className="flex items-center gap-2 text-slate-400 shrink-0">
                        <span className="text-[11px] font-mono">{card.date}</span>
                        <button onClick={() => onDelete(message.id)} className="hover:text-rose-600 p-0.5 text-slate-300 transition" title="Delete">
                          <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      </div>
                    </div>
                    <div className="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5">
                      <span className="truncate">to {card.to}</span>
                    </div>
                  </div>
                </div>
                <div className="text-xs sm:text-sm text-slate-700 leading-relaxed space-y-2 whitespace-pre-wrap break-words [word-break:break-word] overflow-hidden select-text">
                  {cleaned}
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};
