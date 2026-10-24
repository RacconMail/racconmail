/**
 * 🦝 Raccon Mail - React Native + TypeScript Mobile Email Reader (Android APK)
 * Guarantees zero text overflow, centered reading layout, and isolated clean reply view
 */

import React, { useState } from 'react';
import {
  View,
  Text,
  ScrollView,
  Pressable,
  StyleSheet,
  Image,
  Alert
} from 'react-native';
import { MailMessage } from '../../types/mail.js';
import { cleanMimeEmailContent, parseEmailReplyAndQuote } from '../../shared/mailParser.js';

interface MobileReaderScreenProps {
  message: MailMessage;
  currentFolder: string;
  onBack: () => void;
  onDelete: (id: string) => Promise<void>;
  onStar: (id: string) => Promise<void>;
}

export const MobileReaderScreen: React.FC<MobileReaderScreenProps> = ({
  message,
  currentFolder,
  onBack,
  onDelete,
  onStar
}) => {
  const [showQuotedHistory, setShowQuotedHistory] = useState<boolean>(false);
  const isTrash = currentFolder.toLowerCase() === 'trash' || message.isTrashed;

  const handleDeletePress = () => {
    if (isTrash) {
      Alert.alert(
        'Delete Permanently',
        'Permanently delete this email from Trash?',
        [
          { text: 'Cancel', style: 'cancel' },
          {
            text: 'Delete Permanently',
            style: 'destructive',
            onPress: async () => {
              await onDelete(message.id);
              onBack();
            }
          }
        ]
      );
    } else {
      onDelete(message.id).then(() => onBack());
    }
  };

  return (
    <View style={styles.container}>
      {/* Top Header Bar */}
      <View style={styles.header}>
        <Pressable onPress={onBack} hitSlop={10} style={styles.backBtn}>
          <Text style={styles.backBtnText}>←</Text>
        </Pressable>
        <Text style={styles.headerTitle} numberOfLines={1} ellipsizeMode="tail">
          {message.subject || 'Email Reader'}
        </Text>
        <View style={styles.headerActions}>
          <Pressable onPress={() => onStar(message.id)} style={styles.actionBtn}>
            <Text style={[styles.actionIcon, message.isStarred && styles.starActive]}>
              {message.isStarred ? '★' : '☆'}
            </Text>
          </Pressable>
          <Pressable onPress={handleDeletePress} style={styles.actionBtn}>
            <Text style={styles.trashIcon}>🗑</Text>
          </Pressable>
        </View>
      </View>

      {/* Main Content Area */}
      <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
        {/* Prominent Centered Subject Title */}
        <View style={styles.subjectContainer}>
          <Text style={styles.fullSubject}>{message.subject || '(no subject)'}</Text>
          <View style={styles.folderRow}>
            <View style={styles.folderBadge}>
              <Text style={styles.folderBadgeText}>{currentFolder}</Text>
            </View>
            <Text style={styles.dateText}>{message.date || message.time || 'Recently'}</Text>
          </View>
        </View>

        {/* Thread Cards */}
        {(message.thread || []).map((card, idx) => {
          const cleaned = cleanMimeEmailContent(card.body);
          const parsed = parseEmailReplyAndQuote(cleaned);
          const isReply = Boolean(parsed.isReply || message.isReply || message.subject.toLowerCase().startsWith('re:'));
          const replyText = parsed.replyText || cleaned;
          const quotedText = parsed.quotedText || '';

          if (isReply && replyText) {
            return (
              <View key={idx} style={styles.replyCard}>
                {/* Reply Received Badge */}
                <View style={styles.replyHeader}>
                  <View style={styles.replyBadge}>
                    <Text style={styles.replyBadgeText}>↳ Reply Received</Text>
                  </View>
                  <Text style={styles.replyDate}>{card.date || 'Recently'}</Text>
                </View>

                {/* Sender Details */}
                <View style={styles.senderRow}>
                  <Image
                    source={{ uri: card.avatar || 'https://racconmail.eu/raccoon-logo.png' }}
                    style={styles.senderAvatar}
                  />
                  <View style={styles.senderInfo}>
                    <Text style={styles.senderName} numberOfLines={1}>{card.sender}</Text>
                    <Text style={styles.recipientInfo} numberOfLines={1}>to {card.to}</Text>
                  </View>
                </View>

                {/* The Clean Reply Box ("Chenar" - Bounded, No Overflow) */}
                <View style={styles.responseChenar}>
                  <Text style={styles.responseTitle}>RESPONSE MESSAGE</Text>
                  <Text style={styles.responseBody}>{replyText}</Text>
                </View>

                {/* Quoted History Toggle */}
                {quotedText ? (
                  <View style={styles.quotedContainer}>
                    <Pressable
                      onPress={() => setShowQuotedHistory(!showQuotedHistory)}
                      style={styles.quoteToggleBtn}
                    >
                      <Text style={styles.quoteToggleText}>
                        {showQuotedHistory ? 'Hide quoted history ▴' : 'Show quoted history ▾'}
                      </Text>
                    </Pressable>
                    {showQuotedHistory && (
                      <View style={styles.quotedPanel}>
                        <Text style={styles.quotedBody}>{quotedText}</Text>
                      </View>
                    )}
                  </View>
                ) : null}
              </View>
            );
          }

          // Standard Non-Reply Card
          return (
            <View key={idx} style={styles.standardCard}>
              <View style={styles.senderRow}>
                <Image
                  source={{ uri: card.avatar || 'https://racconmail.eu/raccoon-logo.png' }}
                  style={styles.senderAvatar}
                />
                <View style={styles.senderInfo}>
                  <Text style={styles.senderName} numberOfLines={1}>{card.sender}</Text>
                  <Text style={styles.recipientInfo} numberOfLines={1}>to {card.to}</Text>
                </View>
              </View>
              <Text style={styles.standardBody}>{cleaned}</Text>
            </View>
          );
        })}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#FFFFFF'
  },
  header: {
    height: 56,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#F1F5F9',
    backgroundColor: '#FFFFFF'
  },
  backBtn: {
    padding: 6,
    marginRight: 8
  },
  backBtnText: {
    fontSize: 20,
    color: '#334155',
    fontWeight: '700'
  },
  headerTitle: {
    flex: 1,
    fontSize: 15,
    fontWeight: '700',
    color: '#0F172A'
  },
  headerActions: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12
  },
  actionBtn: {
    padding: 4
  },
  actionIcon: {
    fontSize: 18,
    color: '#94A3B8'
  },
  starActive: {
    color: '#F59E0B'
  },
  trashIcon: {
    fontSize: 16,
    color: '#E11D48'
  },
  scrollContent: {
    padding: 16,
    paddingBottom: 40
  },
  subjectContainer: {
    alignItems: 'center',
    marginBottom: 20
  },
  fullSubject: {
    fontSize: 20,
    fontWeight: '800',
    color: '#0F172A',
    textAlign: 'center',
    lineHeight: 26,
    marginBottom: 8
  },
  folderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8
  },
  folderBadge: {
    backgroundColor: '#F1F5F9',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6
  },
  folderBadgeText: {
    fontSize: 10,
    fontWeight: '700',
    color: '#475569',
    textTransform: 'uppercase'
  },
  dateText: {
    fontSize: 11,
    color: '#94A3B8',
    fontFamily: 'monospace'
  },
  replyCard: {
    backgroundColor: '#F8FAFC',
    borderRadius: 20,
    padding: 16,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    marginBottom: 16,
    overflow: 'hidden'
  },
  replyHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 12,
    paddingBottom: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#E2E8F0'
  },
  replyBadge: {
    backgroundColor: '#6C4FF5',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 8
  },
  replyBadgeText: {
    color: '#FFFFFF',
    fontSize: 11,
    fontWeight: '800'
  },
  replyDate: {
    fontSize: 11,
    color: '#94A3B8'
  },
  senderRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12
  },
  senderAvatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
    marginRight: 10,
    backgroundColor: '#F1F5F9'
  },
  senderInfo: {
    flex: 1
  },
  senderName: {
    fontSize: 13,
    fontWeight: '700',
    color: '#0F172A'
  },
  recipientInfo: {
    fontSize: 11,
    color: '#64748B'
  },
  responseChenar: {
    backgroundColor: '#FFFFFF',
    borderRadius: 14,
    padding: 14,
    borderWidth: 1,
    borderColor: '#CBD5E1',
    marginBottom: 10,
    overflow: 'hidden'
  },
  responseTitle: {
    fontSize: 10,
    fontWeight: '800',
    color: '#6C4FF5',
    letterSpacing: 0.5,
    marginBottom: 6
  },
  responseBody: {
    fontSize: 15,
    fontWeight: '600',
    color: '#0F172A',
    lineHeight: 22
  },
  quotedContainer: {
    marginTop: 8
  },
  quoteToggleBtn: {
    paddingVertical: 6,
    paddingHorizontal: 10,
    backgroundColor: '#E2E8F0',
    borderRadius: 8,
    alignSelf: 'flex-start'
  },
  quoteToggleText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#475569'
  },
  quotedPanel: {
    marginTop: 8,
    backgroundColor: '#FFFFFF',
    padding: 10,
    borderRadius: 10,
    borderLeftWidth: 3,
    borderLeftColor: '#6C4FF5'
  },
  quotedBody: {
    fontSize: 11,
    color: '#64748B',
    fontFamily: 'monospace',
    lineHeight: 16
  },
  standardCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    padding: 16,
    borderWidth: 1,
    borderColor: '#E2E8F0',
    marginBottom: 16
  },
  standardBody: {
    fontSize: 14,
    color: '#334155',
    lineHeight: 20
  }
});
