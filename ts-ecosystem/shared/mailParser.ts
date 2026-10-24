/**
 * 🦝 Raccon Mail - Robust Multi-Line Email Reply & Quote Parser
 * Isolates clean reply text and strips multiline wrapped headers (e.g. Gmail Romanian quotes)
 */

import type { ParsedEmailReply } from '../types/mail.ts';

export function parseEmailReplyAndQuote(rawContent: string | null | undefined): ParsedEmailReply {
  if (!rawContent) {
    return { isReply: false, replyText: '', quotedText: '' };
  }

  const text = String(rawContent).trim();
  const normalized = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
  const lines = normalized.split('\n');
  const totalLines = lines.length;

  let quoteStartLine = -1;

  for (let i = 0; i < totalLines; i++) {
    const line = lines[i].trim();

    // 1. Detect quote header ending line (e.g., "a scris:", "wrote:", "schrieb:", "a écrit:")
    if (/(?:a\s*scris|wrote|a\s*écrit|schrieb)\s*:$/i.test(line)) {
      quoteStartLine = i;
      // Walk backwards up to 6 lines to capture author, date, and email header block
      for (let j = i - 1; j >= Math.max(0, i - 6); j--) {
        const prev = lines[j].trim();
        if (prev === '') break; // blank line indicates boundary
        quoteStartLine = j;
      }
      break;
    }

    // 2. Standard email delimiter headers
    if (
      /^-{3,}\s*(?:Original Message|Forwarded message)\s*-{3,}$/i.test(line) ||
      /^_{20,}$/.test(line) ||
      /^From:\s+/i.test(line)
    ) {
      quoteStartLine = i;
      break;
    }
  }

  // 3. Fallback: lines starting with quote symbol > or &gt;
  if (quoteStartLine === -1) {
    for (let i = 0; i < totalLines; i++) {
      const line = lines[i].trim();
      if (line.startsWith('>') || line.startsWith('&gt;')) {
        quoteStartLine = i;
        if (i > 0 && lines[i - 1].trim() !== '') {
          if (/:\s*$/.test(lines[i - 1].trim())) {
            quoteStartLine = i - 1;
          }
        }
        break;
      }
    }
  }

  if (quoteStartLine > 0) {
    const replyPart = lines.slice(0, quoteStartLine).join('\n').trim();
    const quotedPart = lines.slice(quoteStartLine).join('\n').trim();
    if (replyPart.length > 0) {
      return {
        isReply: true,
        replyText: replyPart,
        quotedText: quotedPart
      };
    }
  }

  if (text.startsWith('>') || text.startsWith('&gt;')) {
    return {
      isReply: true,
      replyText: '',
      quotedText: text
    };
  }

  return {
    isReply: false,
    replyText: text,
    quotedText: ''
  };
}

export function cleanMimeEmailContent(raw: string | null | undefined): string {
  if (!raw || typeof raw !== 'string') return '';
  let str = raw.trim();

  if (str.includes('--') || /content-type:\s*(text\/plain|text\/html|multipart)/i.test(str)) {
    const plainMatch = str.match(/content-type:\s*text\/plain[^]*?\r?\n\r?\n([\s\S]*?)(?=--[a-zA-Z0-9_\-\.]+|\z)/i);
    if (plainMatch && plainMatch[1] && plainMatch[1].trim()) {
      str = plainMatch[1].trim();
    } else {
      const htmlMatch = str.match(/content-type:\s*text\/html[^]*?\r?\n\r?\n([\s\S]*?)(?=--[a-zA-Z0-9_\-\.]+|\z)/i);
      if (htmlMatch && htmlMatch[1] && htmlMatch[1].trim()) {
        str = htmlMatch[1].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
      } else {
        str = str.replace(/--[a-zA-Z0-9_\-\.]+(?:--)?/g, '');
        str = str.replace(/content-type:[^\r\n]+/gi, '');
        str = str.replace(/content-transfer-encoding:[^\r\n]+/gi, '');
        str = str.replace(/charset=["']?[^"'\r\n]+["']?/gi, '');
      }
    }
  }

  str = str.replace(/^--[a-zA-Z0-9_\-\.]+/gm, '');
  str = str.replace(/--[a-zA-Z0-9_\-\.]+--\s*$/gm, '');
  str = str.replace(/^[ \t]*Content-Type:[^\r\n]*/gmi, '');
  str = str.replace(/^[ \t]*Content-Transfer-Encoding:[^\r\n]*/gmi, '');
  return str.trim();
}
