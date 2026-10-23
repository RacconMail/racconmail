<?php
declare(strict_types=1);

namespace RacconMail\Core;

/**
 * Clever Postcard Stationery Renderer & Template Formatter
 */
class PostcardEngine
{
    /**
     * Renders a responsive, authentic HTML postcard layout for email clients
     */
    public static function renderPostcard(string $senderName, string $senderEmail, string $subject, string $body, ?string $postmark = null): string
    {
        $escapedBody = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
        $escapedSender = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');
        $dateStr = $postmark ?? strtoupper(date('M Y'));

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{$subject}</title>
  <style>
    body { margin: 0; padding: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f8fafc; }
    .postcard-card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1.5px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .postcard-stripe { height: 8px; background: repeating-linear-gradient(45deg, #ef4444, #ef4444 12px, #3b82f6 12px, #3b82f6 24px, #ffffff 24px, #ffffff 36px); }
    .postcard-body { padding: 28px; line-height: 1.6; color: #1e293b; font-size: 15px; }
    .postmark-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; background: #ffedd5; color: #c2410c; font-size: 11px; font-weight: bold; border: 1px dashed #fdba74; }
  </style>
</head>
<body>
  <div class="postcard-card">
    <div class="postcard-stripe"></div>
    <div class="postcard-body">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
        <span class="postmark-badge">🦝 RACCON POSTMARK • {$dateStr}</span>
      </div>
      <div>{$escapedBody}</div>
      <div style="margin-top:24px; padding-top:16px; border-top:1px solid #e2e8f0; font-size:12px; color:#64748b;">
        Sent via <strong>Raccon Mail</strong> — Masked &amp; Nocturnal Webmail.
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    }
}
