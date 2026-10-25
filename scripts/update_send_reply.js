const fs = require('fs');
const file = 'public/inbox.php';
let content = fs.readFileSync(file, 'utf8');

const targetOld = `    async function sendQuickReply() {
      const inp = document.getElementById('quick-reply-input');
      const val = inp?.value.trim();
      if (!val) return;

      const list = mailDatabase[currentFolder] || mailDatabase['Inbox'];
      const msg = list[currentSelectedThreadIndex];
      if (!msg) return;

      const toEmail = msg.sender || msg.sender_email;
      const origSubj = msg.subject || '(no subject)';
      const replySubj = origSubj.toLowerCase().startsWith('re:') ? origSubj : 'Re: ' + origSubj;

      inp.disabled = true;
      try {
        const res = await fetch('/api/messages/send', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            to: toEmail,
            subject: replySubj,
            body: val,
            in_reply_to: msg.id,
            parent_id: msg.id
          })
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
          throw new Error(data.error || 'Failed to dispatch reply');
        }

        msg.thread.push({
          sender: '<?php echo htmlspecialchars($currentUser[\'email\']); ?>',
          to: toEmail,
          date: 'Just now',
          avatar: '<?php echo htmlspecialchars($currentUser[\'avatar\']); ?>',
          body: val
        });
        inp.value = '';
        playRaccoonSound('happy');
        showRaccoonSuccessModal();
        renderConversationThread(msg);
        refreshFolderCounts();
        fetchFolderMessages(currentFolder);
      } catch (err) {
        alert('Could not send reply: ' + (err.message || err));
      } finally {
        inp.disabled = false;
        inp.focus();
      }
    }`;

const replacementNew = `    async function sendQuickReply() {
      const inp = document.getElementById('quick-reply-input');
      const val = inp?.value.trim();
      if (!val) return;

      const list = getCurrentFilteredList();
      const msg = list[currentSelectedThreadIndex];
      if (!msg) return;

      const toEmail = msg.sender || msg.sender_email;
      const origSubj = msg.subject || '(no subject)';
      const replySubj = origSubj.toLowerCase().startsWith('re:') ? origSubj : 'Re: ' + origSubj;
      const ccVal = document.getElementById('inline-cc-input')?.value.trim() || undefined;
      const bccVal = document.getElementById('inline-bcc-input')?.value.trim() || undefined;

      inp.disabled = true;
      const sendBtn = document.getElementById('inline-send-btn');
      if (sendBtn) sendBtn.disabled = true;

      try {
        const res = await fetch('/api/messages/send', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            to: toEmail,
            cc: ccVal,
            bcc: bccVal,
            subject: replySubj,
            body: val,
            in_reply_to: msg.id,
            parent_id: msg.id
          })
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
          throw new Error(data.error || 'Failed to dispatch reply');
        }

        if (!Array.isArray(msg.thread)) msg.thread = [];
        msg.thread.push({
          sender: '<?php echo htmlspecialchars($currentUser[\'email\']); ?>',
          to: toEmail,
          date: 'Just now',
          avatar: '<?php echo htmlspecialchars($currentUser[\'avatar\']); ?>',
          body: val
        });
        inp.value = '';
        const ccRow = document.getElementById('inline-cc-row');
        const bccRow = document.getElementById('inline-bcc-row');
        if (ccRow) ccRow.classList.add('hidden');
        if (bccRow) bccRow.classList.add('hidden');

        if (typeof playRaccoonSound === 'function') playRaccoonSound('happy');
        if (typeof showRaccoonSuccessModal === 'function') showRaccoonSuccessModal();
        if (typeof showToast === 'function') showToast('Reply sent successfully', 'success');
        renderConversationThread(msg);
        refreshFolderCounts();
        fetchFolderMessages(currentFolder);
      } catch (err) {
        alert('Could not send reply: ' + (err.message || err));
      } finally {
        inp.disabled = false;
        if (sendBtn) sendBtn.disabled = false;
        inp.focus();
      }
    }`;

if (content.includes(targetOld)) {
  content = content.replace(targetOld, replacementNew);
  fs.writeFileSync(file, content, 'utf8');
  console.log('Updated sendQuickReply successfully!');
} else {
  console.error('targetOld not found');
}
