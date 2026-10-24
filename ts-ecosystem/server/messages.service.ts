/**
 * 🦝 Raccon Mail - NestJS Messages Service
 * Handles SQLite/Database operations, permanent trash purging, and reply categorizing
 */

import type { MailMessage, FolderType } from '../types/mail.ts';
import { MailParserService } from './mail-parser.service.ts';

export class MessagesService {
  private parser = new MailParserService();
  private messagesStore: Map<string, MailMessage> = new Map();

  constructor() {
    // Seed initial system message
    const welcomeMsg: MailMessage = {
      id: 'msg_welcome',
      sender: 'support@racconmail.eu',
      senderName: 'Raccon Mail Support',
      recipientEmail: 'user@racconmail.eu',
      subject: 'Welcome to Raccon Mail — Fully Protected Nocturnal Webmail',
      preview: 'Your private, masked postcard email experience is now active.',
      time: 'Today',
      date: new Date().toISOString(),
      isStarred: false,
      isRead: false,
      isTrashed: false,
      isSpam: false,
      isReply: false,
      category: 'primary',
      folder: 'Inbox',
      avatar: '/raccoon-logo.svg',
      thread: [
        {
          sender: 'support@racconmail.eu',
          to: 'user@racconmail.eu',
          date: 'Today',
          avatar: '/raccoon-logo.svg',
          body: 'Welcome to your private inbox. Features include hold-to-delete, permanent trash purging, and isolated clean reply templates.'
        }
      ]
    };
    this.messagesStore.set(welcomeMsg.id, welcomeMsg);
  }

  public getMessagesByFolder(folder: FolderType): MailMessage[] {
    const list = Array.from(this.messagesStore.values());
    const target = folder.toLowerCase();

    if (target === 'all') {
      return list.filter(m => !m.isTrashed);
    }
    if (target === 'trash') {
      return list.filter(m => m.isTrashed);
    }
    if (target === 'updates') {
      return list.filter(m => !m.isTrashed && (m.category === 'updates' || m.isReply));
    }
    if (target === 'starred') {
      return list.filter(m => !m.isTrashed && m.isStarred);
    }
    if (target === 'unread') {
      return list.filter(m => !m.isTrashed && !m.isRead);
    }

    return list.filter(m => !m.isTrashed && m.folder.toLowerCase() === target);
  }

  public getMessageById(id: string): MailMessage | null {
    return this.messagesStore.get(id) || null;
  }

  /**
   * Delete message:
   * If message is ALREADY in Trash OR permanent flag is set, permanently purge it!
   * If message is not in Trash yet, move to Trash!
   */
  public deleteMessage(id: string, forcePermanent = false): { success: boolean; deleted: 'trash' | 'permanent' } {
    const msg = this.messagesStore.get(id);
    if (!msg) {
      return { success: false, deleted: 'permanent' };
    }

    if (msg.isTrashed || forcePermanent) {
      // Permanently purge from storage
      this.messagesStore.delete(id);
      return { success: true, deleted: 'permanent' };
    }

    // Move to trash
    msg.isTrashed = true;
    msg.folder = 'Trash';
    return { success: true, deleted: 'trash' };
  }

  public emptyTrash(): { success: boolean; purgedCount: number } {
    let count = 0;
    for (const [id, msg] of this.messagesStore.entries()) {
      if (msg.isTrashed || msg.folder === 'Trash') {
        this.messagesStore.delete(id);
        count++;
      }
    }
    return { success: true, purgedCount: count };
  }

  public toggleStar(id: string): boolean {
    const msg = this.messagesStore.get(id);
    if (msg) {
      msg.isStarred = !msg.isStarred;
      return msg.isStarred;
    }
    return false;
  }

  public setReadStatus(id: string, isRead: boolean): void {
    const msg = this.messagesStore.get(id);
    if (msg) {
      msg.isRead = isRead;
    }
  }

  public ingestIncomingMessage(rawMsg: Partial<MailMessage>): MailMessage {
    const parsed = this.parser.parseEmailReplyAndQuote(rawMsg.thread?.[0]?.body || rawMsg.preview || '');
    const isReply = Boolean(parsed.isReply || (rawMsg.subject && rawMsg.subject.toLowerCase().startsWith('re:')));

    const newMsg: MailMessage = {
      id: rawMsg.id || `msg_${Date.now()}`,
      sender: rawMsg.sender || 'unknown@domain.com',
      senderName: rawMsg.senderName || rawMsg.sender || 'Sender',
      recipientEmail: rawMsg.recipientEmail || 'user@racconmail.eu',
      subject: rawMsg.subject || '(no subject)',
      preview: parsed.replyText || rawMsg.preview || '',
      time: 'Now',
      date: new Date().toISOString(),
      isStarred: false,
      isRead: false,
      isTrashed: false,
      isSpam: false,
      isReply,
      category: isReply ? 'updates' : 'primary',
      folder: isReply ? 'Updates' : 'Inbox',
      avatar: '/raccoon-logo.svg',
      thread: rawMsg.thread || [
        {
          sender: rawMsg.sender || 'unknown',
          to: rawMsg.recipientEmail || 'me',
          date: 'Now',
          avatar: '/raccoon-logo.svg',
          body: rawMsg.preview || ''
        }
      ],
      replyText: parsed.replyText,
      quotedText: parsed.quotedText
    };

    this.messagesStore.set(newMsg.id, newMsg);
    return newMsg;
  }
}
