/**
 * 🦝 Raccon Mail - React Native + TypeScript Mobile Inbox Screen (Android APK)
 * Native touch engine with 450ms hold-to-delete, haptic feedback, and zero border overflow
 */

import React, { useState } from 'react';
import {
  View,
  Text,
  FlatList,
  Pressable,
  StyleSheet,
  Vibration,
  Image,
  Alert
} from 'react-native';
import { MailMessage } from '../../types/mail.js';
import { cleanMimeEmailContent, parseEmailReplyAndQuote } from '../../shared/mailParser.js';

interface MobileInboxScreenProps {
  messages: MailMessage[];
  currentFolder: string;
  onSelectMessage: (msg: MailMessage) => void;
  onOpenActionSheet: (msg: MailMessage) => void;
  onQuickDelete: (msg: MailMessage) => void;
}

export const MobileInboxScreen: React.FC<MobileInboxScreenProps> = ({
  messages,
  currentFolder,
  onSelectMessage,
  onOpenActionSheet,
  onQuickDelete
}) => {
  const isTrash = currentFolder.toLowerCase() === 'trash';

  const renderItem = ({ item }: { item: MailMessage }) => {
    const raw = cleanMimeEmailContent(item.thread?.[item.thread.length - 1]?.body || item.preview || '');
    const parsed = parseEmailReplyAndQuote(raw);
    const snippetText = parsed.isReply && parsed.replyText ? parsed.replyText : raw;
    const cleanSnippet = snippetText.replace(/<[^>]+>/g, '').slice(0, 85).trim();

    return (
      <Pressable
        onPress={() => onSelectMessage(item)}
        onLongPress={() => {
          try { Vibration.vibrate(60); } catch (_) {}
          onOpenActionSheet(item);
        }}
        delayLongPress={450}
        android_ripple={{ color: '#E2E8F0' }}
        style={({ pressed }) => [
          styles.card,
          pressed && styles.cardPressed
        ]}
      >
        <View style={styles.cardContent}>
          {/* Top Metadata Row: Time & Quick Delete */}
          <View style={styles.metaRow}>
            <Text style={styles.timeText}>{item.time || 'Today'}</Text>
            <Pressable
              hitSlop={8}
              onPress={() => {
                if (isTrash) {
                  Alert.alert(
                    'Delete Permanently',
                    'Permanently delete this email from Trash?',
                    [
                      { text: 'Cancel', style: 'cancel' },
                      { text: 'Delete', style: 'destructive', onPress: () => onQuickDelete(item) }
                    ]
                  );
                } else {
                  onQuickDelete(item);
                }
              }}
              style={styles.trashBtn}
            >
              <Text style={styles.trashBtnText}>🗑</Text>
            </Pressable>
          </View>

          {/* Subject Line (Strictly Bounded to Prevent Overflow) */}
          <Text style={styles.subjectText} numberOfLines={2} ellipsizeMode="tail">
            {item.subject || '(no subject)'}
          </Text>

          {/* Snippet Preview */}
          <Text style={styles.snippetText} numberOfLines={2} ellipsizeMode="tail">
            {cleanSnippet || 'Open thread to read incoming messages and view attachments.'}
          </Text>

          {/* Category Chips Row */}
          <View style={styles.chipsRow}>
            <View style={styles.senderChip}>
              <Text style={styles.senderChipText} numberOfLines={1}>
                {item.senderName || 'Raccon Mail'}
              </Text>
            </View>
            <View style={styles.folderChip}>
              <Text style={styles.folderChipText}>{currentFolder}</Text>
            </View>
            {item.isStarred && (
              <View style={styles.starredChip}>
                <Text style={styles.starredChipText}>★ Starred</Text>
              </View>
            )}
          </View>
        </View>

        {/* Mascot / Avatar Thumbnail */}
        <View style={styles.avatarContainer}>
          <Image
            source={{ uri: item.avatar || 'https://racconmail.eu/raccoon-logo.png' }}
            style={styles.avatarImg}
            resizeMode="contain"
          />
        </View>
      </Pressable>
    );
  };

  return (
    <View style={styles.container}>
      <FlatList
        data={messages}
        keyExtractor={(item) => item.id}
        renderItem={renderItem}
        contentContainerStyle={styles.listPadding}
        showsVerticalScrollIndicator={false}
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F8FAFC'
  },
  listPadding: {
    padding: 12,
    paddingBottom: 24
  },
  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    overflow: 'hidden'
  },
  cardPressed: {
    backgroundColor: '#F1F5F9'
  },
  cardContent: {
    flex: 1,
    marginRight: 10,
    overflow: 'hidden'
  },
  metaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 4
  },
  timeText: {
    fontSize: 11,
    color: '#94A3B8',
    fontFamily: 'monospace',
    fontWeight: '600'
  },
  trashBtn: {
    padding: 4
  },
  trashBtnText: {
    fontSize: 13,
    color: '#CBD5E1'
  },
  subjectText: {
    fontSize: 15,
    fontWeight: '700',
    color: '#0F172A',
    lineHeight: 20,
    marginBottom: 4
  },
  snippetText: {
    fontSize: 12,
    color: '#64748B',
    lineHeight: 16,
    marginBottom: 8
  },
  chipsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: 6
  },
  senderChip: {
    backgroundColor: '#FFEFEA',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6
  },
  senderChipText: {
    fontSize: 10,
    fontWeight: '700',
    color: '#E06748'
  },
  folderChip: {
    backgroundColor: '#FEF9EE',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6
  },
  folderChipText: {
    fontSize: 10,
    fontWeight: '700',
    color: '#B48328'
  },
  starredChip: {
    backgroundColor: '#FFF4ED',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6
  },
  starredChipText: {
    fontSize: 10,
    fontWeight: '700',
    color: '#D96B43'
  },
  avatarContainer: {
    width: 48,
    height: 48,
    borderRadius: 12,
    backgroundColor: '#F1F5F9',
    alignItems: 'center',
    justifyContent: 'center',
    overflow: 'hidden',
    borderWidth: 1,
    borderColor: '#E2E8F0'
  },
  avatarImg: {
    width: 34,
    height: 34
  }
});
