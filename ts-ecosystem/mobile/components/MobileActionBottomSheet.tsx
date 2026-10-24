/**
 * 🦝 Raccon Mail - React Native Mobile Action Bottom Sheet
 * Tactile touch options: Permanent Trash Delete / Move to Trash, Star, Mark Unread
 */

import React from 'react';
import {
  View,
  Text,
  Modal,
  Pressable,
  StyleSheet,
  Image
} from 'react-native';
import { MailMessage } from '../../types/mail.js';

interface MobileActionBottomSheetProps {
  visible: boolean;
  message: MailMessage | null;
  currentFolder: string;
  onClose: () => void;
  onDelete: (id: string) => Promise<void>;
  onStar: (id: string) => Promise<void>;
  onToggleRead: (id: string) => Promise<void>;
}

export const MobileActionBottomSheet: React.FC<MobileActionBottomSheetProps> = ({
  visible,
  message,
  currentFolder,
  onClose,
  onDelete,
  onStar,
  onToggleRead
}) => {
  if (!message) return null;
  const isTrash = currentFolder.toLowerCase() === 'trash' || message.isTrashed;

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      onRequestClose={onClose}
    >
      <Pressable style={styles.backdrop} onPress={onClose}>
        <Pressable style={styles.sheet} onPress={(e) => e.stopPropagation()}>
          {/* Top Pill Handle */}
          <View style={styles.handle} />

          {/* Header Row */}
          <View style={styles.headerRow}>
            <Image
              source={{ uri: 'https://racconmail.eu/raccoon-logo.png' }}
              style={styles.logo}
              resizeMode="contain"
            />
            <View style={styles.headerInfo}>
              <Text style={styles.folderText}>{isTrash ? 'Trash Options' : 'Email Options'}</Text>
              <Text style={styles.subjectText} numberOfLines={1}>
                {message.subject || '(no subject)'}
              </Text>
              <Text style={styles.senderText} numberOfLines={1}>
                {message.senderName || message.sender}
              </Text>
            </View>
            <Pressable onPress={onClose} hitSlop={10} style={styles.closeBtn}>
              <Text style={styles.closeBtnText}>✕</Text>
            </Pressable>
          </View>

          {/* Action List */}
          <View style={styles.actionList}>
            {/* Delete / Move to Trash Option */}
            <Pressable
              onPress={async () => {
                onClose();
                await onDelete(message.id);
              }}
              style={styles.deleteOption}
            >
              <View style={styles.deleteIconWrap}>
                <Text style={styles.deleteIcon}>🗑</Text>
              </View>
              <View style={styles.optionInfo}>
                <Text style={styles.deleteTitle}>
                  {isTrash ? 'Delete Permanently' : 'Delete / Move to Trash'}
                </Text>
                <Text style={styles.deleteSub}>
                  {isTrash ? 'Erase permanently from SQLite database' : 'Move message to Trash folder'}
                </Text>
              </View>
            </Pressable>

            {/* Star Option */}
            <Pressable
              onPress={async () => {
                onClose();
                await onStar(message.id);
              }}
              style={styles.standardOption}
            >
              <View style={styles.starIconWrap}>
                <Text style={styles.starIcon}>★</Text>
              </View>
              <View style={styles.optionInfo}>
                <Text style={styles.optionTitle}>
                  {message.isStarred ? 'Unstar Message' : 'Star Message'}
                </Text>
                <Text style={styles.optionSub}>Mark as important</Text>
              </View>
            </Pressable>

            {/* Read / Unread Option */}
            <Pressable
              onPress={async () => {
                onClose();
                await onToggleRead(message.id);
              }}
              style={styles.standardOption}
            >
              <View style={styles.mailIconWrap}>
                <Text style={styles.mailIcon}>✉</Text>
              </View>
              <View style={styles.optionInfo}>
                <Text style={styles.optionTitle}>
                  {message.isRead ? 'Mark as Unread' : 'Mark as Read'}
                </Text>
                <Text style={styles.optionSub}>Change read status</Text>
              </View>
            </Pressable>
          </View>

          {/* Cancel Button */}
          <Pressable onPress={onClose} style={styles.cancelBtn}>
            <Text style={styles.cancelBtnText}>Cancel</Text>
          </Pressable>
        </Pressable>
      </Pressable>
    </Modal>
  );
};

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.5)',
    justifyContent: 'flex-end'
  },
  sheet: {
    backgroundColor: '#FFFFFF',
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    padding: 20,
    paddingBottom: 32
  },
  handle: {
    width: 44,
    height: 5,
    borderRadius: 3,
    backgroundColor: '#CBD5E1',
    alignSelf: 'center',
    marginBottom: 16
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingBottom: 14,
    borderBottomWidth: 1,
    borderBottomColor: '#F1F5F9',
    marginBottom: 12
  },
  logo: {
    width: 38,
    height: 38,
    marginRight: 12
  },
  headerInfo: {
    flex: 1
  },
  folderText: {
    fontSize: 10,
    fontWeight: '800',
    color: '#6C4FF5',
    textTransform: 'uppercase'
  },
  subjectText: {
    fontSize: 14,
    fontWeight: '700',
    color: '#0F172A'
  },
  senderText: {
    fontSize: 11,
    color: '#64748B'
  },
  closeBtn: {
    padding: 6
  },
  closeBtnText: {
    fontSize: 14,
    color: '#94A3B8',
    fontWeight: '700'
  },
  actionList: {
    gap: 8,
    marginVertical: 12
  },
  deleteOption: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    backgroundColor: '#FFF1F2',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#FECDD3'
  },
  deleteIconWrap: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: '#F43F5E',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12
  },
  deleteIcon: {
    color: '#FFFFFF',
    fontSize: 16
  },
  deleteTitle: {
    fontSize: 14,
    fontWeight: '800',
    color: '#BE123C'
  },
  deleteSub: {
    fontSize: 11,
    color: '#FB7185'
  },
  standardOption: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 12,
    backgroundColor: '#F8FAFC',
    borderRadius: 16,
    borderWidth: 1,
    borderColor: '#E2E8F0'
  },
  starIconWrap: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: '#FEF3C7',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12
  },
  starIcon: {
    color: '#D97706',
    fontSize: 16
  },
  mailIconWrap: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: '#EEF2FF',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 12
  },
  mailIcon: {
    color: '#4F46E5',
    fontSize: 16
  },
  optionInfo: {
    flex: 1
  },
  optionTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: '#1E293B'
  },
  optionSub: {
    fontSize: 11,
    color: '#64748B'
  },
  cancelBtn: {
    paddingVertical: 12,
    backgroundColor: '#F1F5F9',
    borderRadius: 14,
    alignItems: 'center',
    marginTop: 8
  },
  cancelBtnText: {
    fontSize: 14,
    fontWeight: '700',
    color: '#475569'
  }
});
