<?php
/**
 * PlanClassifier
 * ==============
 * Verifies that an uploaded floor plan actually MATCHES the property category
 * the user selected (commercial vs residential, and the specific sub-type).
 *
 * WHY THIS MATTERS:
 *   A residential home plan and a factory layout require completely different
 *   Vastu analysis. If a user uploads a house plan but selects "Factory", the
 *   report would be meaningless. To protect the business from spam / wrong
 *   reports, we screen the plan at upload time and REJECT mismatches.
 *
 * HOW IT WORKS:
 *   - Reliable category recognition from an image requires computer vision.
 *     We use the Claude vision model (already integrated in ClaudeAI) to
 *     classify the plan and compare against the user selection.
 *   - If vision AI is NOT configured, we cannot reliably distinguish an office
 *     plan from a house plan, so we DO NOT silently pass. Instead we return a
 *     "needs_manual_review" verdict and let the caller decide (the validator
 *     still applies the strict photo-rejection checks regardless).
 */

class PlanClassifier {

    // Map our internal sub-types to human descriptions for the AI prompt.
    private static $subTypeLabels = [
        // Commercial
        'land'                      => 'a vacant commercial plot / land',
        'office_space'              => 'an office space layout (cabins, workstations, meeting rooms, reception)',
        'retail_showroom'           => 'a retail shop or showroom (display area, billing counter, storage)',
        'factory'                   => 'a factory / industrial unit (production hall, machinery, loading bays)',
        'warehouse'                 => 'a warehouse / godown (large open storage, racks, loading docks)',
        // Residential
        'row_house_kothi'           => 'a residential row house / kothi (bedrooms, kitchen, living, toilets)',
        'builder_floor_apartment'   => 'a residential apartment / builder floor (flats with bedrooms, kitchen)',
        'villa'                     => 'a residential villa / bungalow (bedrooms, kitchen, living, garden)',
    ];

    private static $commercialTypes = ['land', 'office_space', 'retail_showroom', 'factory', 'warehouse'];
    private static $residentialTypes = ['row_house_kothi', 'builder_floor_apartment', 'villa'];

    /**
     * Classify the uploaded plan and verify it matches the selected category.
     *
     * @param string $imagePath
     * @param string $category    'commercial' | 'residential'
     * @param string $subType     e.g. 'office_space', 'villa'
     * @return array {
     *     match: bool,                 // does plan match selection?
     *     verdict: string,             // 'match' | 'mismatch' | 'needs_manual_review' | 'not_a_plan'
     *     detected_category: string,   // what AI thinks it is
     *     detected_subtype: string,
     *     confidence: float,
     *     message: string|null,        // user-facing message if rejected
     *     ai_used: bool
     * }
     */
    public static function classify($imagePath, $category, $subType) {
        $category = strtolower(trim($category));
        $subType = strtolower(trim($subType));

        // If no AI configured, we cannot reliably classify the building type.
        if (!class_exists('ClaudeAI') || !ClaudeAI::isConfigured()) {
            return [
                'match' => true, // don't block; structural checks already passed
                'verdict' => 'needs_manual_review',
                'detected_category' => null,
                'detected_subtype' => null,
                'confidence' => 0,
                'message' => null,
                'ai_used' => false,
            ];
        }

        $result = self::askVisionModel($imagePath, $category, $subType);
        if (!$result) {
            // AI call failed - fall back to manual review, don't block
            return [
                'match' => true,
                'verdict' => 'needs_manual_review',
                'detected_category' => null,
                'detected_subtype' => null,
                'confidence' => 0,
                'message' => null,
                'ai_used' => false,
            ];
        }

        return $result;
    }

    /**
     * Ask the Claude vision model to classify the plan.
     */
    private static function askVisionModel($imagePath, $category, $subType) {
        if (!file_exists($imagePath)) return null;
        $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) return null;

        $selectedDesc = self::$subTypeLabels[$subType] ?? $subType;

        $prompt = <<<PROMPT
You are an expert architectural plan classifier. Look at the attached image and determine what type of building/property plan it is.

The user claims this plan is: {$category} - {$selectedDesc}.

Analyse the layout, room labels, and structural features. Then respond STRICTLY as valid JSON:
{
  "is_floor_plan": true/false,
  "detected_category": "residential" or "commercial",
  "detected_subtype": one of ["land","office_space","retail_showroom","factory","warehouse","row_house_kothi","builder_floor_apartment","villa"],
  "confidence": 0.0 to 1.0,
  "matches_user_selection": true/false,
  "reasoning": "one short sentence"
}

Rules:
- is_floor_plan=false if the image is a photograph, random picture, or not an architectural plan.
- A residential plan has bedrooms, kitchen, living room, toilets, pooja room.
- An office has cabins, workstations, conference/meeting rooms, reception.
- A retail/showroom has open display areas and billing/counter zones.
- A factory has large production halls, machinery layouts, loading areas.
- A warehouse has large open storage with racks and loading docks.
- matches_user_selection=true only if the detected type is reasonably consistent with what the user claims.
- Be lenient between residential sub-types (villa vs row house vs apartment are often similar), but STRICT between residential and commercial, and between very different commercial types (e.g. office vs factory).

Return ONLY the JSON.
PROMPT;

        $raw = ClaudeAI::classifyImage($imagePath, $prompt);
        if (!$raw || !is_array($raw)) return null;

        $isPlan = !empty($raw['is_floor_plan']);
        if (!$isPlan) {
            return [
                'match' => false,
                'verdict' => 'not_a_plan',
                'detected_category' => $raw['detected_category'] ?? null,
                'detected_subtype' => $raw['detected_subtype'] ?? null,
                'confidence' => floatval($raw['confidence'] ?? 0),
                'message' => 'The uploaded image was not recognised as a valid architectural floor plan. Please upload a clear, digital floor plan. If you need assistance, please connect with our support team.',
                'ai_used' => true,
            ];
        }

        $detectedCat = strtolower($raw['detected_category'] ?? '');
        $detectedSub = strtolower($raw['detected_subtype'] ?? '');
        $confidence = floatval($raw['confidence'] ?? 0);
        $matches = !empty($raw['matches_user_selection']);

        // Hard guard: residential vs commercial mismatch is always rejected
        $userIsCommercial = ($category === 'commercial');
        $detectedIsCommercial = in_array($detectedSub, self::$commercialTypes)
            || $detectedCat === 'commercial';

        if ($userIsCommercial !== $detectedIsCommercial && $confidence >= 0.5) {
            $detectedLabel = self::$subTypeLabels[$detectedSub] ?? ($detectedCat ?: 'a different property type');
            $selectedLabel = self::$subTypeLabels[$subType] ?? $subType;
            return [
                'match' => false,
                'verdict' => 'mismatch',
                'detected_category' => $detectedCat,
                'detected_subtype' => $detectedSub,
                'confidence' => $confidence,
                'message' => "The uploaded plan appears to be {$detectedLabel}, but you selected {$selectedLabel}. " .
                             "Vastu analysis differs significantly between property types, so we cannot generate an accurate report. " .
                             "Please upload the correct plan for your selected category, or change your selection. If you need help, connect with our support team.",
                'ai_used' => true,
            ];
        }

        // Specific commercial mismatch (e.g. office uploaded for factory)
        if ($userIsCommercial && $detectedIsCommercial && !$matches && $confidence >= 0.6) {
            $detectedLabel = self::$subTypeLabels[$detectedSub] ?? $detectedCat;
            $selectedLabel = self::$subTypeLabels[$subType] ?? $subType;
            return [
                'match' => false,
                'verdict' => 'mismatch',
                'detected_category' => $detectedCat,
                'detected_subtype' => $detectedSub,
                'confidence' => $confidence,
                'message' => "The uploaded plan appears to be {$detectedLabel}, but you selected {$selectedLabel}. " .
                             "Please upload the correct plan, or change your selection. If you need help, connect with our support team.",
                'ai_used' => true,
            ];
        }

        // Passed
        return [
            'match' => true,
            'verdict' => 'match',
            'detected_category' => $detectedCat,
            'detected_subtype' => $detectedSub,
            'confidence' => $confidence,
            'message' => null,
            'ai_used' => true,
        ];
    }

    public static function isCommercial($subType) {
        return in_array(strtolower($subType), self::$commercialTypes);
    }
}
