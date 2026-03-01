<?php

namespace App\Service;

use App\Entity\Post;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PostRiskAlertNotifier
{
    public function __construct(
        private readonly MailerInterface   $mailer,
        private readonly string            $alertRecipient,
        private readonly string            $senderEmail,
        private readonly ?LoggerInterface  $logger = null,
    ) {}

    public function notifyHighRiskPost(Post $post, array $matchedSignals = []): void
    {
        $author      = $post->getUtilisateur();
        $authorLabel = $author ? ($author->getPrenom() . ' ' . $author->getNom()) : 'Unknown';
        $authorEmail = $author?->getEmail();
        $signals     = $matchedSignals === [] ? 'None detected' : implode(', ', $matchedSignals);
        $postId      = $post->getId() ?? 0;
        $riskLevel   = strtoupper($post->getRiskLevel());
        $emotion     = ucfirst($post->getEmotion() ?? 'unknown');
        $content     = htmlspecialchars($post->getContenu(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $date        = (new \DateTimeImmutable())->format('d/m/Y à H:i');

        // ── Validate sender ───────────────────────────────────────
        $senderEmail = trim($this->senderEmail);
        if ($senderEmail === '' || !filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
            $senderEmail = 'noreply@localhost';
        }

        // ── Build recipient list ──────────────────────────────────
        $recipients = [];
        if (is_string($authorEmail) && filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $authorEmail;
        }
        $alertRecipient = trim($this->alertRecipient);
        if ($alertRecipient !== '' && filter_var($alertRecipient, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $alertRecipient;
        }
        $recipients = array_values(array_unique($recipients));

        if ($recipients === []) {
            $this->logger?->warning('[RiskAlert] No valid recipients, skipping email', ['postId' => $postId]);
            return;
        }

        // ── Risk badge color ──────────────────────────────────────
        $badgeColor = match(strtolower($post->getRiskLevel())) {
            'high'   => '#dc2626',
            'medium' => '#d97706',
            default  => '#16a34a',
        };

        // ── HTML email ────────────────────────────────────────────
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Risk Alert — Bekri</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Inter',Arial,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#1a2332 0%,#20b2aa 100%);border-radius:16px 16px 0 0;padding:32px 36px;text-align:center;">
            <h1 style="margin:0;color:white;font-size:24px;font-weight:800;letter-spacing:-0.5px;">
              🚨 Bekri Risk Alert
            </h1>
            <p style="margin:8px 0 0;color:rgba(255,255,255,0.75);font-size:14px;">
              AI-powered content moderation system
            </p>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="background:white;padding:32px 36px;">

            <!-- Risk badge -->
            <div style="text-align:center;margin-bottom:24px;">
              <span style="display:inline-block;background:{$badgeColor};color:white;font-size:13px;font-weight:700;padding:6px 20px;border-radius:50px;text-transform:uppercase;letter-spacing:1px;">
                {$riskLevel} RISK
              </span>
            </div>

            <p style="margin:0 0 20px;font-size:15px;color:#334155;line-height:1.6;">
              Our AI has detected a <strong>potentially sensitive post</strong> that requires your attention.
            </p>

            <!-- Details table -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;margin-bottom:24px;">
              <tr>
                <td style="padding:14px 18px;border-bottom:1px solid #e2e8f0;">
                  <span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:700;">Post ID</span><br>
                  <span style="font-size:15px;color:#1a2332;font-weight:600;">#{$postId}</span>
                </td>
                <td style="padding:14px 18px;border-bottom:1px solid #e2e8f0;">
                  <span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:700;">Author</span><br>
                  <span style="font-size:15px;color:#1a2332;font-weight:600;">{$authorLabel}</span>
                </td>
              </tr>
              <tr>
                <td style="padding:14px 18px;border-bottom:1px solid #e2e8f0;">
                  <span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:700;">Emotion Detected</span><br>
                  <span style="font-size:15px;color:#1a2332;font-weight:600;">{$emotion}</span>
                </td>
                <td style="padding:14px 18px;border-bottom:1px solid #e2e8f0;">
                  <span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:700;">Detected At</span><br>
                  <span style="font-size:15px;color:#1a2332;font-weight:600;">{$date}</span>
                </td>
              </tr>
              <tr>
                <td colspan="2" style="padding:14px 18px;">
                  <span style="font-size:12px;color:#64748b;text-transform:uppercase;font-weight:700;">AI Signals</span><br>
                  <span style="font-size:14px;color:#dc2626;font-weight:600;">{$signals}</span>
                </td>
              </tr>
            </table>

            <!-- Post content -->
            <div style="margin-bottom:24px;">
              <p style="margin:0 0 8px;font-size:12px;color:#64748b;text-transform:uppercase;font-weight:700;">Post Content</p>
              <div style="background:#fff7ed;border-left:4px solid {$badgeColor};border-radius:0 8px 8px 0;padding:16px 18px;font-size:14px;color:#334155;line-height:1.7;">
                {$content}
              </div>
            </div>

            <!-- Recommendation -->
            <div style="background:#f0fdf9;border:1px solid #6ee7b7;border-radius:12px;padding:16px 18px;margin-bottom:24px;">
              <p style="margin:0;font-size:14px;color:#065f46;line-height:1.6;">
                <strong>💡 Recommended action:</strong>
                Please review this post and reach out to the author if needed.
                If the content violates community guidelines, consider removing it and offering support resources.
              </p>
            </div>

          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f8fafc;border-radius:0 0 16px 16px;padding:20px 36px;text-align:center;border-top:1px solid #e2e8f0;">
            <p style="margin:0;font-size:12px;color:#94a3b8;">
              This alert was generated automatically by Bekri's AI moderation system.<br>
              © {$date} Bekri Wellbeing Platform
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>

</body>
</html>
HTML;

// ── Plain text fallback ───────────────────────────────────
$sensitive = $post->isSensitive() ? 'Yes' : 'No';

$text = <<<TEXT
BEKRI RISK ALERT — {$riskLevel} RISK
=====================================
Post ID   : #{$postId}
Author    : {$authorLabel}
Emotion   : {$emotion}
Risk Level: {$riskLevel}
Sensitive : {$sensitive}
Signals   : {$signals}
Detected  : {$date}

Post Content:
-------------
{$post->getContenu()}

Please review this post and take appropriate action.
TEXT;

        // ── Send ──────────────────────────────────────────────────
        try {
            $email = (new Email())
                ->from($senderEmail)
                ->to(...$recipients)
                ->subject(sprintf('[Bekri Alert] %s risk post detected — #%d', ucfirst(strtolower($riskLevel)), $postId))
                ->html($html)
                ->text($text);

            $this->mailer->send($email);
            $this->logger?->info('[RiskAlert] Alert email sent', [
                'postId'     => $postId,
                'recipients' => $recipients,
                'riskLevel'  => $riskLevel,
            ]);
        } catch (\Throwable $e) {
            $this->logger?->error('[RiskAlert] Failed to send email', [
                'error'  => $e->getMessage(),
                'postId' => $postId,
            ]);
        }
    }
}