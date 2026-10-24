/**
 * 🦝 Raccon Mail - TypeScript Ecosystem Verification Test Suite
 */

import assert from 'node:assert';
import { parseEmailReplyAndQuote } from './shared/mailParser.ts';
import { MessagesService } from './server/messages.service.ts';
import { MessagesController } from './server/messages.controller.ts';

console.log('=== RUNNING TYPESCRIPT FULL-STACK ECOSYSTEM TESTS ===\n');

// Test 1: Parser Multi-line Wrapped Romanian Quote Test
console.log('1. Testing MailParserService with wrapped Romanian quote...');
const rawRomanianEmail = `Hgghjkhh

dum., 25 oct. 2026, 01:08
Cristian Aeroaiei
<cristianaeroaiei1989@gmail.com
a scris:

> Salut Cristian,
> Test email de verificare`;

const parsed = parseEmailReplyAndQuote(rawRomanianEmail);
console.log('Isolated Reply:', JSON.stringify(parsed.replyText));
console.log('Isolated Quoted Header Start:', parsed.quotedText.split('\n')[0]);
assert.strictEqual(parsed.isReply, true, 'Must identify as reply');
assert.strictEqual(parsed.replyText, 'Hgghjkhh', 'Reply text must be exactly Hgghjkhh without any metadata leakage');
assert.ok(parsed.quotedText.startsWith('dum., 25 oct. 2026'), 'Quoted header must be cleanly captured in quotedText');
console.log('  [PASS] Clean reply text isolated with ZERO header leakage!\n');

// Test 2: Server MessagesService & Controller Lifecycle
console.log('2. Testing MessagesService & Controller with Permanent Trash Purge...');
const service = new MessagesService();
const controller = new MessagesController(service);

// Ingest a test message
const msg = service.ingestIncomingMessage({
  id: 'test_msg_456',
  sender: 'test@example.com',
  senderName: 'Test Sender',
  subject: 'Test Subject',
  preview: rawRomanianEmail
});
assert.strictEqual(msg.isReply, true, 'Should auto-tag as reply');
assert.strictEqual(msg.category, 'updates', 'Replies must be routed to updates category');
assert.strictEqual(msg.folder, 'Updates', 'Reply folder must be Updates');
console.log('  [PASS] Auto-categorized as Updates category & reply');

// Fetch inbox
const inboxRes = controller.getMessages('updates');
assert.strictEqual(inboxRes.total, 1, 'Should find 1 message in updates');
console.log('  [PASS] Found 1 message in updates folder');

// Step A: Move to trash
console.log('Step A: Moving message to Trash...');
const trashRes1 = controller.trashMessage('test_msg_456');
assert.strictEqual(trashRes1.deleted, 'trash', 'First call must move to trash');
assert.strictEqual(service.getMessagesByFolder('Trash').length, 1, 'Trash must have 1 message');
console.log('  [PASS] Message moved to Trash successfully');

// Step B: Call delete while in Trash (must permanently delete!)
console.log('Step B: Deleting while in Trash...');
const trashRes2 = controller.trashMessage('test_msg_456');
assert.strictEqual(trashRes2.deleted, 'permanent', 'Second call while in trash must permanently delete');
assert.strictEqual(service.getMessagesByFolder('Trash').length, 0, 'Trash must be empty after permanent delete');
assert.strictEqual(service.getMessageById('test_msg_456'), null, 'Message must be permanently erased');
console.log('  [PASS] Message permanently purged from storage!\n');

// Test 3: Empty Trash
console.log('3. Testing Empty Trash functionality...');
service.ingestIncomingMessage({ id: 'trash_1', subject: 'T1', preview: 'B1' });
service.ingestIncomingMessage({ id: 'trash_2', subject: 'T2', preview: 'B2' });
service.deleteMessage('trash_1');
service.deleteMessage('trash_2');
assert.strictEqual(service.getMessagesByFolder('Trash').length, 2, 'Should have 2 messages in trash');

const emptyRes = controller.emptyTrash();
assert.strictEqual(emptyRes.success, true);
assert.strictEqual(emptyRes.purgedCount, 2, 'Should purge both messages');
assert.strictEqual(service.getMessagesByFolder('Trash').length, 0, 'Trash must now be completely empty');
console.log('  [PASS] Empty Trash purged all messages permanently!\n');

console.log('=== ALL TYPESCRIPT FULL-STACK SUITE TESTS PASSED 100% ===');
