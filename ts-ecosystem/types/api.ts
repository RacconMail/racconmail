/**
 * 🦝 Raccon Mail - API Contracts & Protocol Types
 */

import { MailMessage, FolderType, UserProfile } from './mail.ts';

export interface ApiResponse<T = unknown> {
  success: boolean;
  error?: string;
  data?: T;
}

export interface MessagesListResponse {
  success: boolean;
  folder: FolderType;
  total: number;
  unreadCount: number;
  messages: MailMessage[];
}

export interface DeleteMessageResponse {
  success: boolean;
  deleted: 'trash' | 'permanent';
  id: string;
}

export interface SendMessagePayload {
  to: string;
  subject: string;
  body: string;
  cc?: string;
  bcc?: string;
  inReplyTo?: string;
  attachments?: File[];
}

export interface SendMessageResponse {
  success: boolean;
  messageId: string;
  deliveredLocal: number;
  deliveredExternal: number;
}

export interface LoginPayload {
  email: string;
  password: string;
}

export interface LoginResponse {
  success: boolean;
  token?: string;
  user?: UserProfile;
  error?: string;
}
