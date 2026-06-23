<?php
/**
 * AI / Bedrock Diagnostics
 * ========================
 * Run this on the LIVE server to confirm whether AI vision (Bedrock / Claude)
 * is actually reachable and working. The plan validator relies on this AI to
 * accept COLOURED CAD plans and to reject hand-drawn / handwritten pages, so if
 * it is misconfigured those judgements silently fall back to crude heuristics.
 *
 * USAGE (just open in a browser):
 *   https://yourdomain.com/vastu/backend/api/ai_diagnostics.php
 *
 * Output is intentionally NON-SENSITIVE: it never reveals the API key (only
 * whether one is present), the model id, region, the HTTP status of a real test
 * call, and a fix hint. Delete this file once AI is confirmed working if you
 * prefer not to leave a diagnostic endpoint exposed.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/ClaudeAI.php';

header('Content-Type: application/json');

$diag = ClaudeAI::diagnose();

// Never expose even a preview of the key over an open endpoint.
unset($diag['key_preview']);

echo json_encode([
    'ai_diagnostics' => $diag,
    'summary' => $diag['ok']
        ? 'AI vision is WORKING. Coloured CAD plans will be accepted and hand-drawn/handwritten pages rejected by the AI.'
        : ($diag['configured']
            ? 'A key is configured but the test call FAILED. See "http_status" and "hint" to fix (usually model not enabled, wrong model id, or wrong region).'
            : 'No AI key detected on the server. Set a Bedrock API key (ABSK...) in admin settings or config.php. Until then validation uses the heuristic fallback.'),
], JSON_PRETTY_PRINT);
