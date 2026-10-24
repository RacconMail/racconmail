/**
 * 🦝 Raccon Mail - NestJS Injectable Mail Parser Service
 * Enterprise TypeScript static typing for email MIME cleaning & quote stripping
 */

export interface ParsedReplyResult {
  isReply: boolean;
  replyText: string;
  quotedText: string;
}

export class MailParserService {
  /**
   * Scans lines backwards from quote indicator (e.g. "a scris:", "wrote:")
   * Isolates multiline wrapped quote headers from Romanian Gmail or Outlook
   */
  public parseEmailReplyAndQuote(rawContent: string | null | undefined): ParsedReplyResult {
    if (!rawContent) {
      return { isReply: false, replyText: '', quotedText: '' };
    }

    const text = String(rawContent).trim();
    const normalized = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
    const lines = normalized.split('\n');
    const count = lines.length;

    let quoteStartLine = -1;

    for (let i = 0; i < count; i++) {
      const line = lines[i].trim();

      // Check for quote indicator ending (e.g. "a scris:", "wrote:", "schrieb:")
      if (/(?:a\s*scris|wrote|a\s*écrit|schrieb)\s*:$/i.test(line)) {
        quoteStartLine = i;
        // Walk backwards up to 6 lines to capture author, date, and email tokens
        for (let j = i - 1; j >= Math.max(0, i - 6); j--) {
          const prev = lines[j].trim();
          if (prev === '') break;
          quoteStartLine = j;
        }
        break;
      }

      // Standard headers
      if (
        /^-{3,}\s*(?:Original Message|Forwarded message)\s*-{3,}$/i.test(line) ||
        /^_{20,}$/.test(line) ||
        /^From:\s+/i.test(line)
      ) {
        quoteStartLine = i;
        break;
      }
    }

    // Fallback: lines starting with >
    if (quoteStartLine === -1) {
      for (let i = 0; i < count; i++) {
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

  public cleanMimeEmailContent(raw: string | null | undefined): string {
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
}
