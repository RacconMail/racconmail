/**
 * 🦝 Raccon Mail - Core Domain Models & Static Types
 * Guaranteed compile-time safety across Web, Mobile (Android), and Desktop
 */

export type FolderType = 
  | 'Inbox' 
  | 'Updates' 
  | 'Starred' 
  | 'Unread' 
  | 'Drafts' 
  | 'Sent' 
  | 'Archive' 
  | 'Spam' 
  | 'Trash' 
  | 'All';

export interface EmailAttachment {
  id: string;
  filename: string;
  size: number;
  mimeType: string;
  url: string;
}

export interface ThreadMessageCard {
  id?: string;
  sender: string;
  to: string;
  date: string;
  avatar: string;
  body: string;
  bodyHtml?: string;
  isReply?: boolean;
}

export interface ParsedEmailReply {
  isReply: boolean;
  replyText: string;
  quotedText: string;
}

export interface MailMessage {
  id: string;
  sender: string;
  senderName: string;
  recipientEmail: string;
  subject: string;
  preview: string;
  time: string;
  date: string;
  isStarred: boolean;
  isRead: boolean;
  isTrashed: boolean;
  isSpam: boolean;
  isReply: boolean;
  category: 'primary' | 'updates' | 'promotions' | 'social';
  folder: FolderType;
  avatar: string;
  thread: ThreadMessageCard[];
  attachments?: EmailAttachment[];
  replyText?: string;
  quotedText?: string;
}

export interface UserProfile {
  id: string;
  name: string;
  email: string;
  avatar: string;
  signature?: string;
  theme?: 'light' | 'dark' | 'auto';
  quotaBytes?: number;
  usedBytes?: number;
}
