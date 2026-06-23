<?php
/**
 * Claude AI Client - Direct Anthropic API integration
 *
 * Supports both:
 *   1. Direct Anthropic API (CLAUDE_API_KEY) - simpler, recommended for most users
 *   2. AWS Bedrock (AWS_ACCESS_KEY + AWS_SECRET_KEY) - for AWS-native deployments
 *
 * If neither is configured, calling generate() returns null and
 * the caller should fall back to VastuEngine (rule-based).
 */

class ClaudeAI {

    /**
     * Resolve which provider keys are ACTIVE, honoring an optional AI_PROVIDER
     * preference: 'anthropic' (or 'claude') | 'bedrock' | 'iam' | 'auto'.
     *
     * This lets an operator FORCE the direct Anthropic Claude API even if a
     * stale Bedrock key still exists in the database settings (which would
     * otherwise win by priority and keep routing calls to Bedrock).
     *
     * Set it in secrets.local.php / config.php:  define('AI_PROVIDER','anthropic');
     *
     * @return array [directKey, awsKey, bedrockApiKey]
     */
    private static function resolveKeys() {
        $directKey = getSetting('claude_api_key', defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : '');
        $awsKey = getSetting('aws_access_key', defined('AWS_ACCESS_KEY') ? AWS_ACCESS_KEY : '');
        $bedrockApiKey = defined('BEDROCK_API_KEY') ? getSetting('bedrock_api_key', BEDROCK_API_KEY) : getSetting('bedrock_api_key', '');

        $provider = strtolower(trim(getSetting('ai_provider', defined('AI_PROVIDER') ? AI_PROVIDER : 'auto')));
        if ($provider === 'anthropic' || $provider === 'claude') {
            $bedrockApiKey = ''; $awsKey = '';          // force direct Claude API
        } elseif ($provider === 'bedrock') {
            $directKey = ''; $awsKey = '';              // force Bedrock API key
        } elseif ($provider === 'iam') {
            $directKey = ''; $bedrockApiKey = '';       // force Bedrock IAM SigV4
        }
        return [$directKey, $awsKey, $bedrockApiKey];
    }

    /**
     * Check if any AI is configured.
     */
    public static function isConfigured() {
        list($directKey, $awsKey, $bedrockApiKey) = self::resolveKeys();
        return !empty($directKey) || !empty($awsKey) || !empty($bedrockApiKey);
    }

    /**
     * Live connectivity diagnostic. Makes a minimal real call to the configured
     * provider and returns detailed status so an operator can confirm whether
     * AI vision actually works on this server (key valid, model id enabled,
     * region correct, network reachable). Never throws.
     *
     * @return array
     */
    public static function diagnose() {
        list($directKey, $awsKey, $bedrockApiKey) = self::resolveKeys();
        $region = getSetting('aws_region', defined('AWS_REGION') ? AWS_REGION : 'us-east-1');
        $model  = getSetting('bedrock_model', defined('BEDROCK_MODEL') ? BEDROCK_MODEL : 'anthropic.claude-3-sonnet-20240229-v1:0');

        $out = [
            'configured'      => self::isConfigured(),
            'method'          => null,
            'region'          => $region,
            'model'           => $model,
            'key_present'     => false,
            'key_preview'     => null,
            'http_status'     => null,
            'curl_error'      => null,
            'ok'              => false,
            'response_excerpt'=> null,
            'hint'            => null,
        ];

        // Pick provider in the same priority order as generate()/classifyImage().
        if (!empty($bedrockApiKey)) {
            $out['method'] = 'bedrock_api_key';
            $out['key_present'] = true;
            $out['key_preview'] = substr($bedrockApiKey, 0, 6) . '...(' . strlen($bedrockApiKey) . ' chars)';
            $url = "https://bedrock-runtime.{$region}.amazonaws.com/model/{$model}/invoke";
            $body = json_encode([
                'anthropic_version' => 'bedrock-2023-05-31',
                'max_tokens' => 16,
                'messages' => [['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Reply with the single word OK.']]]]
            ]);
            $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $bedrockApiKey];
        } elseif (!empty($directKey)) {
            $out['method'] = 'anthropic_direct';
            $out['key_present'] = true;
            $out['key_preview'] = substr($directKey, 0, 8) . '...(' . strlen($directKey) . ' chars)';
            $url = 'https://api.anthropic.com/v1/messages';
            $body = json_encode([
                'model' => defined('CLAUDE_MODEL') ? CLAUDE_MODEL : 'claude-3-5-sonnet-20241022',
                'max_tokens' => 16,
                'messages' => [['role' => 'user', 'content' => 'Reply with the single word OK.']]
            ]);
            $headers = ['Content-Type: application/json', 'x-api-key: ' . $directKey, 'anthropic-version: 2023-06-01'];
        } elseif (!empty($awsKey)) {
            $out['method'] = 'bedrock_iam';
            $out['key_present'] = true;
            $out['hint'] = 'IAM SigV4 path configured. This diagnostic does not test SigV4; upload a plan to verify, or switch to a Bedrock API key for simpler setup.';
            return $out;
        } else {
            $out['hint'] = 'No AI key configured. Set a Bedrock API key (ABSK...), an Anthropic key (sk-ant-...), or AWS IAM keys in admin settings / config.php. Without AI, validation uses the strict grayscale heuristic which cannot accept coloured CAD plans.';
            return $out;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        $out['http_status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $out['curl_error'] = $err;
            $out['hint'] = 'Network/TLS error reaching the AI endpoint. Check that the server can make outbound HTTPS calls (some shared hosts block this).';
            return $out;
        }

        $out['response_excerpt'] = substr((string)$response, 0, 300);
        $out['ok'] = ($out['http_status'] === 200);

        if (!$out['ok']) {
            $lower = strtolower((string)$response);
            if (strpos($lower, 'inference profile') !== false
                || strpos($lower, "on-demand throughput isn") !== false
                || strpos($lower, 'on-demand throughput is') !== false) {
                // Newer models (Claude Opus 4.x / Sonnet 4.x) cannot be invoked
                // by their bare model id with on-demand throughput — they require
                // a cross-Region INFERENCE PROFILE id (prefixed us. / eu. / apac.
                // / global.). e.g. us.anthropic.claude-3-5-sonnet-20241022-v2:0
                $out['hint'] = 'This model must be called via a cross-Region INFERENCE PROFILE id, not the bare model id. In the AWS Bedrock console open the model and copy its "Inference profile ID" (it starts with us. / global. / apac. etc.), then set BEDROCK_MODEL to that. Example: us.anthropic.claude-3-5-sonnet-20241022-v2:0';
            } elseif (strpos($lower, 'invalid') !== false && strpos($lower, 'model') !== false) {
                $out['hint'] = 'The BEDROCK_MODEL id is invalid/misformatted. A valid id looks like anthropic.claude-3-5-sonnet-20241022-v2:0 (or an inference profile like us.anthropic.claude-...). Copy the exact id from the AWS Bedrock console.';
            } elseif ($out['http_status'] === 403) {
                $out['hint'] = 'HTTP 403: the key is rejected or the model is not enabled for your account/region. In the AWS Bedrock console, request access to this model and confirm the region matches the key.';
            } elseif ($out['http_status'] === 404) {
                $out['hint'] = 'HTTP 404: the model id is wrong for this region. Verify BEDROCK_MODEL and AWS_REGION, or use an inference profile id.';
            } elseif ($out['http_status'] === 400) {
                $out['hint'] = 'HTTP 400: request rejected. Usually a wrong/misformatted model id or a model that needs an inference profile. See response_excerpt for the exact provider message.';
            } else {
                $out['hint'] = 'Non-200 response. See response_excerpt for the provider error message.';
            }
        }
        return $out;
    }


    /**
     * Generate Vastu analysis using Claude.
     *
     * @param array $input Report input (direction, image_path, etc.)
     * @return array|null Parsed JSON response or null on failure
     */
    public static function generate($input) {
        list($directKey, $awsKey, $bedrockApiKey) = self::resolveKeys();

        $prompt = self::buildPrompt($input);

        // Try Bedrock Long-Term API Key first (simplest, newest method)
        if (!empty($bedrockApiKey)) {
            $result = self::callBedrockWithApiKey($bedrockApiKey, $prompt, $input['image_path'] ?? null);
            if ($result) return $result;
        }

        // Try direct Anthropic API
        if (!empty($directKey)) {
            $result = self::callAnthropicAPI($directKey, $prompt, $input['image_path'] ?? null);
            if ($result) return $result;
        }

        // Try AWS Bedrock with IAM SigV4 (legacy)
        if (!empty($awsKey)) {
            $result = self::callBedrock($prompt, $input['image_path'] ?? null);
            if ($result) return $result;
        }

        return null;
    }

    /**
     * Generic image classification helper.
     * Sends an image + custom prompt to the configured vision model and
     * returns the parsed JSON response (or null on failure).
     *
     * Used by PlanClassifier to verify plan type matches the user selection.
     *
     * @param string $imagePath
     * @param string $prompt
     * @return array|null
     */
    public static function classifyImage($imagePath, $prompt) {
        list($directKey, $awsKey, $bedrockApiKey) = self::resolveKeys();

        if (!empty($bedrockApiKey)) {
            $r = self::callBedrockWithApiKey($bedrockApiKey, $prompt, $imagePath);
            if ($r) return $r;
        }
        if (!empty($directKey)) {
            $r = self::callAnthropicAPI($directKey, $prompt, $imagePath);
            if ($r) return $r;
        }
        if (!empty($awsKey)) {
            $r = self::callBedrock($prompt, $imagePath);
            if ($r) return $r;
        }
        return null;
    }

    /**
     * Build the Vastu analysis prompt.
     */
    private static function buildPrompt($input) {
        $direction = $input['direction'] ?? 'unknown';
        $plotSize = $input['plot_size'] ?? 'unspecified';
        $floors = $input['floors'] ?? 'unspecified';
        $concerns = $input['concerns'] ?? 'none specified';
        $name = $input['customer_name'] ?? 'Customer';
        $category = $input['property_category'] ?? '';
        $subType = str_replace('_', ' ', $input['property_subtype'] ?? '');
        $facingLabel = $input['facing_label'] ?? $direction;

        // Brahmasthan / entry hints from geometry analysis
        $geoHint = '';
        if (!empty($input['brahmasthan'])) {
            $geoHint .= "\n- The geometric centre of the plan (Brahmasthan) has been computed at pixel (" .
                intval($input['brahmasthan']['x']) . "," . intval($input['brahmasthan']['y']) . "). Pay special attention to what occupies this central zone.";
        }
        if (!empty($input['entry'])) {
            $geoHint .= "\n- The main entry/facing side is the {$facingLabel} side of the plan.";
        }

        // Category-specific analysis instructions
        $isCommercial = in_array(strtolower($input['property_subtype'] ?? ''),
            ['land','office_space','retail_showroom','factory','warehouse']);

        if ($isCommercial) {
            $typeContext = "This is a COMMERCIAL property ({$category} - {$subType}). " .
                "Apply commercial Vastu principles. Evaluate placement of: the owner/director/boss cabin (ideally South-West), " .
                "employee/staff workstations (East/North for productivity), reception (North/East), accounts/cash (North or South-East), " .
                "conference/meeting rooms, store/inventory (North-West/South-West), toilets (North-West/West), pantry (South-East), " .
                "and the main entrance. For factories/warehouses also assess machinery (South-East), heavy storage (South-West), and loading zones.";
            $roomGuidance = "Identify commercial zones (owner cabin, staff area, reception, accounts, meeting room, store, toilet, pantry, machinery, entrance).";
        } else {
            $typeContext = "This is a RESIDENTIAL property ({$category} - {$subType}). Apply residential Vastu principles.";
            $roomGuidance = "Identify all visible rooms (kitchen, bedrooms, master bedroom, toilet, pooja room, living room, dining, staircase, entrance).";
        }

        return <<<PROMPT
You are an expert Vastu Shastra consultant and architectural energy analyst with 25 years of experience.

{$typeContext}

Analyze the attached plan with the following details:
- Customer Name: {$name}
- Facing: {$facingLabel}
- Plot Size: {$plotSize}
- Floors: {$floors}
- Specific Concerns: {$concerns}{$geoHint}

Your task:
1. {$roomGuidance} Note each zone's directional placement.
2. Apply the 16-zone Vastu directional grid analysis, keyed to the {$facingLabel} facing.
3. Evaluate each zone's Vastu compliance based on traditional principles for this property type.
4. Assess the Brahmasthan (central zone) - it should ideally be open/clutter-free.
5. Generate an overall Vastu score (0-100).
6. List positive aspects and Vastu defects.
7. Provide prioritized remedies (high/medium/low priority).
8. Generate life/business impact scores (health, wealth, relations, career - each 0-100).
9. Recommend specific Vastu products from this catalog: copper pyramid, brass tortoise, sphatik shree yantra, kuber yantra, salt lamp, amethyst cluster, copper strips, money plant, brass laughing buddha.
10. Write an executive summary (2-3 sentences) and final verdict (4-5 sentences).

Output STRICTLY as valid JSON in this exact structure:
{
  "overall_score": 78,
  "summary": "...",
  "final_verdict": "...",
  "rooms": [{"name": "Kitchen", "type": "kitchen", "direction": "SE", "score": 85, "analysis": "...", "remedy": "..."}],
  "positives": ["..."],
  "negatives": ["..."],
  "remedies": [{"title": "...", "description": "...", "priority": "high", "icon": "fire"}],
  "impacts": {
    "health": {"score": 75, "note": "..."},
    "wealth": {"score": 80, "note": "..."},
    "relations": {"score": 72, "note": "..."},
    "career": {"score": 78, "note": "..."}
  },
  "recommended_products": [{"id": 1, "name": "Crystal Vastu Pyramid", "description": "...", "price": 899, "original_price": 1499, "icon": "gem"}],
  "heatmap": [{"name": "N1", "score": 82, "level": "good"}]
}

Generate exactly 16 heatmap entries representing the 16-zone grid (NW1, N1, N2, NE1, W2, C1, C2, E1, W1, C3, C4, E2, SW1, S1, S2, SE1).
Use levels: excellent (80+), good (65-79), average (45-64), poor (30-44), bad (<30).

Return ONLY the JSON, no preamble or explanation.
PROMPT;
    }

    /**
     * Call Anthropic API directly.
     */
    private static function callAnthropicAPI($apiKey, $prompt, $imagePath = null) {
        $messages = [];
        $content = [];

        // Attach image if available and is JPEG/PNG
        if ($imagePath && file_exists($imagePath)) {
            $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
                $imageData = base64_encode(file_get_contents($imagePath));
                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $mime,
                        'data' => $imageData
                    ]
                ];
            }
        }
        $content[] = ['type' => 'text', 'text' => $prompt];

        $messages[] = ['role' => 'user', 'content' => $content];

        $payload = [
            'model' => CLAUDE_MODEL,
            'max_tokens' => 4096,
            'messages' => $messages
        ];

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            logDebug('Claude API error', ['status' => $httpCode, 'response' => substr($response, 0, 500)]);
            return null;
        }

        $data = json_decode($response, true);
        $text = $data['content'][0]['text'] ?? '';

        // Extract JSON from response
        if (preg_match('/\{[\s\S]*\}/m', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if ($parsed) return $parsed;
        }
        return null;
    }

    /**
     * Call AWS Bedrock using Long-Term API Key (simplest method).
     *
     * Bedrock Long-Term API Keys use a simple Authorization header
     * instead of AWS SigV4 signing. Much simpler for shared hosting.
     *
     * Endpoint: https://bedrock-runtime.{region}.amazonaws.com/model/{model}/invoke
     * Auth header: Bearer {ABSK...key}
     */
    private static function callBedrockWithApiKey($apiKey, $prompt, $imagePath = null) {
        $region = getSetting('aws_region', defined('AWS_REGION') ? AWS_REGION : 'us-east-1');
        $model = getSetting('bedrock_model', defined('BEDROCK_MODEL') ? BEDROCK_MODEL : 'anthropic.claude-3-sonnet-20240229-v1:0');

        $content = [];
        if ($imagePath && file_exists($imagePath)) {
            $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $mime,
                        'data' => base64_encode(file_get_contents($imagePath))
                    ]
                ];
            }
        }
        $content[] = ['type' => 'text', 'text' => $prompt];

        $body = json_encode([
            'anthropic_version' => 'bedrock-2023-05-31',
            'max_tokens' => 4096,
            'messages' => [['role' => 'user', 'content' => $content]]
        ]);

        $url = "https://bedrock-runtime.{$region}.amazonaws.com/model/{$model}/invoke";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            logDebug('Bedrock API Key cURL error', ['error' => $error]);
            return null;
        }

        if ($httpCode !== 200) {
            logDebug('Bedrock API Key error', ['status' => $httpCode, 'response' => substr($response, 0, 500)]);
            return null;
        }

        $data = json_decode($response, true);
        $text = $data['content'][0]['text'] ?? '';

        if (preg_match('/\{[\s\S]*\}/m', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if ($parsed) {
                logDebug('Bedrock API Key success', ['score' => $parsed['overall_score'] ?? 'n/a']);
                return $parsed;
            }
        }

        logDebug('Bedrock API Key - could not parse JSON from response', ['text_len' => strlen($text)]);
        return null;
    }

    /**
     * Call AWS Bedrock with AWS Signature V4.
     * Simplified implementation - production users may prefer the official AWS SDK.
     */
    private static function callBedrock($prompt, $imagePath = null) {
        $awsKey = getSetting('aws_access_key', AWS_ACCESS_KEY);
        $awsSecret = getSetting('aws_secret_key', AWS_SECRET_KEY);
        $region = getSetting('aws_region', AWS_REGION);
        $model = getSetting('bedrock_model', BEDROCK_MODEL);

        if (!$awsKey || !$awsSecret) return null;

        $content = [];
        if ($imagePath && file_exists($imagePath)) {
            $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
                $content[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $mime,
                        'data' => base64_encode(file_get_contents($imagePath))
                    ]
                ];
            }
        }
        $content[] = ['type' => 'text', 'text' => $prompt];

        $body = json_encode([
            'anthropic_version' => 'bedrock-2023-05-31',
            'max_tokens' => 4096,
            'messages' => [['role' => 'user', 'content' => $content]]
        ]);

        $host = "bedrock-runtime.{$region}.amazonaws.com";
        $path = "/model/{$model}/invoke";
        $service = 'bedrock';
        $method = 'POST';

        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $contentSha = hash('sha256', $body);

        // Build canonical request
        $canonicalHeaders = "content-type:application/json\nhost:{$host}\nx-amz-content-sha256:{$contentSha}\nx-amz-date:{$now}\n";
        $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';
        $canonicalReq = "{$method}\n{$path}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$contentSha}";

        $credentialScope = "{$date}/{$region}/{$service}/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$now}\n{$credentialScope}\n" . hash('sha256', $canonicalReq);

        // Derive signing key
        $kDate = hash_hmac('sha256', $date, "AWS4{$awsSecret}", true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authHeader = "AWS4-HMAC-SHA256 Credential={$awsKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $ch = curl_init("https://{$host}{$path}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Amz-Content-Sha256: ' . $contentSha,
            'X-Amz-Date: ' . $now,
            'Authorization: ' . $authHeader
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            logDebug('Bedrock error', ['status' => $httpCode, 'response' => substr($response, 0, 500)]);
            return null;
        }

        $data = json_decode($response, true);
        $text = $data['content'][0]['text'] ?? '';
        if (preg_match('/\{[\s\S]*\}/m', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if ($parsed) return $parsed;
        }
        return null;
    }
}
