<?php
/**
 * AI / Bedrock Diagnostics
 * ========================
 * Run this on the LIVE server to confirm whether AI vision (Bedrock / Claude)
 * is actually reachable and working. The plan validator relies on this AI to
 * accept COLOURED CAD plans and to reject hand-drawn / handwritten pages, so if
 * it is misconfigured those judgements silently fall back to crude heuristics.
 *
 * USAGE (browser or curl):
 *   https://yourdomain.com/vastu/backend/api/ai_diagnostics.php?token=XXXX
 *
 * The token is derived from JWT_SECRET so this endpoint is not publicly open.
 * After deploying, compute the token by loading this URL without a token — it
 * tells you nothing sensitive but returns 403 with instructions; the simplest
 * path is to read the token printed in your server logs via logDebug, or set it
 * yourself: token = substr(sha1('ai-diag|' . JWT_SECRET), 0, 16).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/ClaudeAI.php';

header('Content-Type: application/json');

$expected = substr(sha1('ai-diag|' . (defined('JWT_SECRET') ? JWT_SECRET : '')), 0, 16);
$provided = $_GET['token'] ?? '';

if (!hash_equals($expected, (string)$provided)) {
    http_response_code(403);
    echo json_encode([
        'error' => 'forbidden',
        'message' => 'Provide ?token=<token>. Compute it as substr(sha1("ai-diag|" . JWT_SECRET), 0, 16) using your config JWT_SECRET.',
    ], JSON_PRETTY_PRINT);
    exit;
}

$diag = ClaudeAI::diagnose();

echo json_encode([
    'ai_diagnostics' => $diag,
    'summary' => $diag['ok']
        ? 'AI vision is WORKING. Coloured CAD plans will be accepted and hand-drawn/handwritten pages rejected by the AI.'
        : 'AI vision is NOT working. Fix the issue in "hint" above; until then validation uses the strict heuristic fallback (which cannot accept coloured CAD plans).',
], JSON_PRETTY_PRINT);
