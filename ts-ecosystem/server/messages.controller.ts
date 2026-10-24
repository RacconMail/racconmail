/**
 * 🦝 Raccon Mail - NestJS / Express REST Controller in TypeScript
 * Implements typed API endpoints with permanent deletion and updates routing
 */

import { MessagesService } from './messages.service.ts';
import type { FolderType } from '../types/mail.ts';

export class MessagesController {
  private readonly messagesService: MessagesService;

  constructor(messagesService: MessagesService) {
    this.messagesService = messagesService;
  }

  public getMessages(folder: string = 'inbox') {
    const list = this.messagesService.getMessagesByFolder(
      (folder.charAt(0).toUpperCase() + folder.slice(1).toLowerCase()) as FolderType
    );
    return {
      success: true,
      folder,
      total: list.length,
      unreadCount: list.filter(m => !m.isRead).length,
      messages: list
    };
  }

  public getMessage(id: string) {
    const msg = this.messagesService.getMessageById(id);
    if (!msg) {
      return { success: false, error: 'Message not found' };
    }
    return { success: true, message: msg };
  }

  /**
   * POST /api/messages/:id/trash
   * Moves to trash on first call; permanently purges on subsequent calls while in trash!
   */
  public trashMessage(id: string, force = false) {
    const result = this.messagesService.deleteMessage(id, force);
    return {
      success: result.success,
      deleted: result.deleted,
      id
    };
  }

  /**
   * DELETE /api/messages/:id
   * Permanent delete when permanent=1 or when already in trash
   */
  public deleteMessage(id: string, permanent = false) {
    const result = this.messagesService.deleteMessage(id, permanent);
    return {
      success: result.success,
      deleted: result.deleted,
      id
    };
  }

  /**
   * POST /api/messages/empty-trash
   * Purges all messages in Trash permanently
   */
  public emptyTrash() {
    const result = this.messagesService.emptyTrash();
    return {
      success: true,
      purgedCount: result.purgedCount
    };
  }

  /**
   * POST /api/messages/:id/star
   */
  public toggleStar(id: string) {
    const isStarred = this.messagesService.toggleStar(id);
    return {
      success: true,
      isStarred
    };
  }

  /**
   * POST /api/messages/:id/read
   */
  public setRead(id: string, isRead: boolean) {
    this.messagesService.setReadStatus(id, isRead);
    return {
      success: true,
      isRead
    };
  }
}
