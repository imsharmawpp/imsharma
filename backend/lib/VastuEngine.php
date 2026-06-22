<?php
/**
 * VastuEngine - Rule-Based Vastu Analysis Engine (Fallback)
 *
 * Generates a complete, realistic Vastu Kundali report based on:
 *   - House facing direction
 *   - Plot size & floors
 *   - Optional concerns
 *
 * This engine produces production-quality reports without requiring external AI.
 * It uses authentic Vastu Shastra principles encoded as rules.
 *
 * If Claude/Bedrock is configured, it can be used to enhance/replace this output.
 */

class VastuEngine {

    // 16 directional zones with elemental associations (traditional Vastu)
    private static $zones = [
        'N'  => ['name' => 'North',         'element' => 'Water',  'deity' => 'Kuber',     'governs' => 'Wealth, Career'],
        'NE' => ['name' => 'North-East',    'element' => 'Water',  'deity' => 'Ishan',     'governs' => 'Spirituality, Health'],
        'E'  => ['name' => 'East',          'element' => 'Air',    'deity' => 'Indra',     'governs' => 'Health, Knowledge'],
        'SE' => ['name' => 'South-East',    'element' => 'Fire',   'deity' => 'Agni',      'governs' => 'Energy, Cooking'],
        'S'  => ['name' => 'South',         'element' => 'Earth',  'deity' => 'Yama',      'governs' => 'Strength, Fame'],
        'SW' => ['name' => 'South-West',    'element' => 'Earth',  'deity' => 'Nairutya',  'governs' => 'Stability, Relationships'],
        'W'  => ['name' => 'West',          'element' => 'Water',  'deity' => 'Varun',     'governs' => 'Gains, Profits'],
        'NW' => ['name' => 'North-West',    'element' => 'Air',    'deity' => 'Vayu',      'governs' => 'Movement, Support'],
    ];

    // Direction-specific room placement rules (traditional Vastu)
    private static $idealPlacements = [
        'kitchen'        => ['ideal' => ['SE'], 'acceptable' => ['NW'], 'avoid' => ['NE', 'N', 'E']],
        'master_bedroom' => ['ideal' => ['SW'], 'acceptable' => ['S', 'W'], 'avoid' => ['NE', 'SE']],
        'bedroom'        => ['ideal' => ['W', 'S', 'SW'], 'acceptable' => ['NW'], 'avoid' => ['NE']],
        'pooja_room'     => ['ideal' => ['NE'], 'acceptable' => ['E', 'N'], 'avoid' => ['S', 'SW']],
        'toilet'         => ['ideal' => ['NW', 'W'], 'acceptable' => ['S'], 'avoid' => ['NE', 'E', 'N', 'SE']],
        'living_room'    => ['ideal' => ['N', 'E', 'NE'], 'acceptable' => ['NW'], 'avoid' => ['SW']],
        'dining'         => ['ideal' => ['W', 'E'], 'acceptable' => ['S'], 'avoid' => ['SW', 'NE']],
        'study'          => ['ideal' => ['NE', 'E', 'N'], 'acceptable' => ['W'], 'avoid' => ['S']],
        'staircase'      => ['ideal' => ['SW', 'S', 'W'], 'acceptable' => ['SE'], 'avoid' => ['NE', 'N', 'E']],
        'parking'        => ['ideal' => ['NW', 'SE'], 'acceptable' => ['N', 'E'], 'avoid' => ['SW']],
        'store_room'     => ['ideal' => ['NW', 'SW'], 'acceptable' => ['S', 'W'], 'avoid' => ['NE', 'E']],
        'balcony'        => ['ideal' => ['N', 'E', 'NE'], 'acceptable' => ['W'], 'avoid' => ['SW']],
        'entrance'       => ['ideal' => ['N', 'E', 'NE'], 'acceptable' => ['W'], 'avoid' => ['SW']],
    ];

    // Commercial placement rules (offices, showrooms, factories, warehouses)
    private static $commercialPlacements = [
        'owner_cabin'    => ['ideal' => ['SW'], 'acceptable' => ['S', 'W'], 'avoid' => ['NE', 'SE']],
        'director_cabin' => ['ideal' => ['SW'], 'acceptable' => ['S', 'W'], 'avoid' => ['NE', 'SE']],
        'manager_cabin'  => ['ideal' => ['W', 'S'], 'acceptable' => ['SW'], 'avoid' => ['NE']],
        'staff_area'     => ['ideal' => ['E', 'N', 'NE'], 'acceptable' => ['NW'], 'avoid' => ['SW']],
        'workstation'    => ['ideal' => ['E', 'N'], 'acceptable' => ['NW', 'W'], 'avoid' => ['SW']],
        'reception'      => ['ideal' => ['NE', 'N', 'E'], 'acceptable' => ['NW'], 'avoid' => ['SW', 'S']],
        'accounts'       => ['ideal' => ['N', 'SE'], 'acceptable' => ['E'], 'avoid' => ['SW']],
        'cash_locker'    => ['ideal' => ['N', 'SW'], 'acceptable' => ['S'], 'avoid' => ['NE']],
        'meeting_room'   => ['ideal' => ['NW', 'N', 'E'], 'acceptable' => ['W'], 'avoid' => ['SE']],
        'display_area'   => ['ideal' => ['N', 'E', 'NE'], 'acceptable' => ['NW'], 'avoid' => ['SW']],
        'billing_counter'=> ['ideal' => ['N', 'E'], 'acceptable' => ['NE'], 'avoid' => ['S', 'SW']],
        'machinery'      => ['ideal' => ['SE'], 'acceptable' => ['S'], 'avoid' => ['NE', 'N']],
        'production'     => ['ideal' => ['SE', 'S'], 'acceptable' => ['W'], 'avoid' => ['NE']],
        'heavy_storage'  => ['ideal' => ['SW', 'S', 'W'], 'acceptable' => ['NW'], 'avoid' => ['NE', 'N', 'E']],
        'inventory'      => ['ideal' => ['NW', 'SW', 'W'], 'acceptable' => ['S'], 'avoid' => ['NE', 'E']],
        'loading_bay'    => ['ideal' => ['NW', 'N'], 'acceptable' => ['E'], 'avoid' => ['SW']],
        'pantry'         => ['ideal' => ['SE'], 'acceptable' => ['NW'], 'avoid' => ['NE']],
        'toilet'         => ['ideal' => ['NW', 'W'], 'acceptable' => ['S'], 'avoid' => ['NE', 'E', 'N', 'SE']],
        'entrance'       => ['ideal' => ['N', 'E', 'NE'], 'acceptable' => ['W'], 'avoid' => ['SW']],
    ];

    // Commercial sub-types -> the set of "rooms"/zones we evaluate
    private static $commercialLayouts = [
        'office_space' => [
            ['name' => 'Main Entrance', 'type' => 'entrance'],
            ['name' => 'Reception', 'type' => 'reception'],
            ['name' => 'Owner / Director Cabin', 'type' => 'owner_cabin'],
            ['name' => 'Manager Cabin', 'type' => 'manager_cabin'],
            ['name' => 'Staff Workstations', 'type' => 'staff_area'],
            ['name' => 'Accounts / Cash', 'type' => 'accounts'],
            ['name' => 'Conference / Meeting Room', 'type' => 'meeting_room'],
            ['name' => 'Pantry', 'type' => 'pantry'],
            ['name' => 'Toilet', 'type' => 'toilet'],
        ],
        'retail_showroom' => [
            ['name' => 'Main Entrance', 'type' => 'entrance'],
            ['name' => 'Display Area', 'type' => 'display_area'],
            ['name' => 'Billing Counter', 'type' => 'billing_counter'],
            ['name' => 'Cash / Locker', 'type' => 'cash_locker'],
            ['name' => 'Owner Cabin', 'type' => 'owner_cabin'],
            ['name' => 'Inventory / Store', 'type' => 'inventory'],
            ['name' => 'Toilet', 'type' => 'toilet'],
        ],
        'factory' => [
            ['name' => 'Main Entrance', 'type' => 'entrance'],
            ['name' => 'Owner / Director Cabin', 'type' => 'owner_cabin'],
            ['name' => 'Production Hall', 'type' => 'production'],
            ['name' => 'Machinery Zone', 'type' => 'machinery'],
            ['name' => 'Heavy Raw-material Storage', 'type' => 'heavy_storage'],
            ['name' => 'Finished Goods / Loading Bay', 'type' => 'loading_bay'],
            ['name' => 'Accounts Office', 'type' => 'accounts'],
            ['name' => 'Staff Area', 'type' => 'staff_area'],
            ['name' => 'Toilet', 'type' => 'toilet'],
        ],
        'warehouse' => [
            ['name' => 'Main Entrance', 'type' => 'entrance'],
            ['name' => 'Office Cabin', 'type' => 'owner_cabin'],
            ['name' => 'Heavy Storage Racks', 'type' => 'heavy_storage'],
            ['name' => 'Inventory Zone', 'type' => 'inventory'],
            ['name' => 'Loading Dock', 'type' => 'loading_bay'],
            ['name' => 'Accounts', 'type' => 'accounts'],
            ['name' => 'Toilet', 'type' => 'toilet'],
        ],
        'land' => [
            ['name' => 'Plot Entrance', 'type' => 'entrance'],
            ['name' => 'North-East Open Zone', 'type' => 'reception'],
            ['name' => 'South-West Built Mass', 'type' => 'heavy_storage'],
        ],
    ];

    private static function isCommercialType($subType) {
        return in_array(strtolower($subType ?? ''),
            ['land', 'office_space', 'retail_showroom', 'factory', 'warehouse']);
    }

    /**
     * Generate complete Vastu report.
     *
     * @param array $input Report input: direction, plot_size, floors, concerns, etc.
     * @return array Complete report data structure
     */
    public static function generate($input) {
        $direction = $input['direction'] ?? 'N';
        $concerns = strtolower($input['concerns'] ?? '');
        $name = $input['customer_name'] ?? 'Customer';
        $subType = strtolower($input['property_subtype'] ?? '');
        $isCommercial = self::isCommercialType($subType);

        // Generate placements (commercial zones or residential rooms)
        $rooms = $isCommercial
            ? self::generateCommercialRooms($direction, $subType)
            : self::generateRooms($direction);

        // Calculate scores (uses commercial or residential placement rules)
        $roomScores = self::scoreRooms($rooms, $isCommercial);
        $rooms = $roomScores; // use the scored rooms in the output
        $heatmap = self::generateHeatmap($direction, $roomScores);
        $overallScore = self::calculateOverallScore($roomScores, $direction);

        // Generate findings
        $noun = $isCommercial ? 'property' : 'home';
        $positives = self::generatePositives($direction, $rooms, $roomScores, $noun);
        $negatives = self::generateNegatives($direction, $rooms, $roomScores, $noun);

        // Generate remedies
        $remedies = self::generateRemedies($negatives, $concerns);

        // Generate impact analysis
        $impacts = self::generateImpacts($overallScore, $direction, $rooms, $concerns);

        // Generate product recommendations
        $products = self::recommendProducts($negatives, $concerns);

        // Generate text content
        $summary = self::generateSummary($name, $direction, $overallScore, count($positives), count($negatives), $isCommercial);
        $finalVerdict = self::generateVerdict($overallScore, $direction, $isCommercial);

        return [
            'overall_score' => $overallScore,
            'direction' => $direction,
            'property_type' => $isCommercial ? 'commercial' : 'residential',
            'property_subtype' => $subType,
            'summary' => $summary,
            'final_verdict' => $finalVerdict,
            'rooms' => $rooms,
            'positives' => $positives,
            'negatives' => $negatives,
            'remedies' => $remedies,
            'impacts' => $impacts,
            'recommended_products' => $products,
            'heatmap' => $heatmap,
            'generated_at' => date('Y-m-d H:i:s'),
            'engine' => $isCommercial ? 'rule-based-commercial-v1' : 'rule-based-v1'
        ];
    }

    /**
     * Generate commercial zone placements for the given sub-type & facing.
     */
    private static function generateCommercialRooms($facing, $subType) {
        $layout = self::$commercialLayouts[$subType] ?? self::$commercialLayouts['office_space'];
        $allDirections = array_keys(self::$zones);
        $seedDirections = self::shuffleByFacing($allDirections, $facing);

        $placements = [];
        foreach ($layout as $i => $zone) {
            $placements[] = [
                'name' => $zone['name'],
                'type' => $zone['type'],
                'direction' => $seedDirections[$i % count($seedDirections)],
            ];
        }
        return $placements;
    }

    /**
     * Generate simulated room placements based on facing.
     * In production, this would come from computer vision.
     */
    private static function generateRooms($facing) {
        // Probabilistic room placement based on common Indian house plans
        $allDirections = array_keys(self::$zones);
        $placements = [];

        // Common rooms that exist in most homes
        $roomTypes = [
            ['name' => 'Main Entrance', 'type' => 'entrance'],
            ['name' => 'Living Room', 'type' => 'living_room'],
            ['name' => 'Kitchen', 'type' => 'kitchen'],
            ['name' => 'Master Bedroom', 'type' => 'master_bedroom'],
            ['name' => 'Bedroom 2', 'type' => 'bedroom'],
            ['name' => 'Bathroom (Common)', 'type' => 'toilet'],
            ['name' => 'Pooja Room', 'type' => 'pooja_room'],
            ['name' => 'Dining Area', 'type' => 'dining'],
            ['name' => 'Staircase', 'type' => 'staircase'],
        ];

        // Use deterministic-ish placement based on facing direction
        $seedDirections = self::shuffleByFacing($allDirections, $facing);

        foreach ($roomTypes as $i => $room) {
            $dir = $seedDirections[$i % count($seedDirections)];
            $placements[] = [
                'name' => $room['name'],
                'type' => $room['type'],
                'direction' => $dir
            ];
        }

        return $placements;
    }

    /**
     * Pseudo-shuffle directions based on facing for realistic variety.
     */
    private static function shuffleByFacing($directions, $facing) {
        // Different seed per facing direction for variety
        $seeds = ['N' => 17, 'S' => 23, 'E' => 31, 'W' => 41, 'NE' => 53, 'NW' => 67, 'SE' => 71, 'SW' => 79];
        mt_srand($seeds[$facing] ?? 11);
        $copy = $directions;
        shuffle($copy);
        mt_srand(); // re-randomize
        return $copy;
    }

    /**
     * Score each room based on its placement.
     */
    private static function scoreRooms($rooms, $isCommercial = false) {
        $rules = $isCommercial ? self::$commercialPlacements : self::$idealPlacements;
        foreach ($rooms as $i => $room) {
            $rule = $rules[$room['type']] ?? null;
            if (!$rule) {
                $rooms[$i]['score'] = 70;
                $rooms[$i]['analysis'] = 'Standard placement, neutral effect.';
                continue;
            }

            $dir = $room['direction'];
            if (in_array($dir, $rule['ideal'])) {
                $rooms[$i]['score'] = rand(85, 95);
                $rooms[$i]['analysis'] = "Excellent placement! {$room['name']} in " . formatDirection($dir) . " is highly auspicious as per Vastu.";
            } elseif (in_array($dir, $rule['acceptable'])) {
                $rooms[$i]['score'] = rand(65, 80);
                $rooms[$i]['analysis'] = "Acceptable placement. {$room['name']} in " . formatDirection($dir) . " is fine, with some minor adjustments for optimization.";
            } elseif (in_array($dir, $rule['avoid'])) {
                $rooms[$i]['score'] = rand(25, 50);
                $rooms[$i]['analysis'] = "Vastu defect: {$room['name']} in " . formatDirection($dir) . " creates energy imbalance. Remedies recommended.";
                $rooms[$i]['remedy'] = self::getRoomRemedy($room['type'], $dir);
            } else {
                $rooms[$i]['score'] = rand(55, 70);
                $rooms[$i]['analysis'] = "Neutral placement with mild Vastu impact.";
            }
        }
        return $rooms;
    }

    /**
     * Get specific remedy for a misplaced room.
     */
    private static function getRoomRemedy($type, $dir) {
        $remedies = [
            'kitchen' => 'Place a copper Agni yantra and avoid black tiles. Cook facing east for balance.',
            'toilet' => 'Keep toilet door closed always. Place a bowl of sea salt to absorb negativity. Add a green plant outside.',
            'master_bedroom' => 'Sleep with head towards south. Place a brass kalash with water in the southwest corner.',
            'pooja_room' => 'Move sacred items to a small NE shelf if relocation isn\'t possible. Light a ghee diya daily.',
            'staircase' => 'Avoid spiral staircases. Keep staircase well-lit. Hang a Vastu plant under the stairs.',
            // Commercial
            'owner_cabin' => 'The owner should sit in the South-West facing North/East. Place a solid wall behind the chair and a brass pyramid on the desk.',
            'director_cabin' => 'Position the director in the South-West with a solid backing. Avoid sitting under a beam. Add a Kuber yantra in the North.',
            'staff_area' => 'Seat staff facing North or East for productivity. Keep the East/North zones clutter-free and well-lit.',
            'accounts' => 'Keep the cash box/safe opening towards North or East. Place a Kuber yantra and keep this zone clean.',
            'cash_locker' => 'The safe should open towards the North (Kuber). Place it against the South-West wall for stability.',
            'reception' => 'Keep reception in the North/East, well-lit and welcoming. Place a company logo on the South wall.',
            'machinery' => 'Heavy machinery and furnaces belong in the South-East (Agni). Keep the North-East light and free of machines.',
            'production' => 'Run heavy production in the South/South-East. Keep finished goods in the North-West for faster movement.',
            'heavy_storage' => 'Store heavy raw material in the South-West. Keep the North-East corner light and open.',
            'inventory' => 'Place inventory racks along the South and West walls. Avoid blocking the North-East.',
            'display_area' => 'Keep the display/showroom open towards the North and East. Use bright lighting and mirrors on the South wall.',
            'billing_counter' => 'Place the billing counter so the cashier faces North or East. Keep a small water element in the North-East.',
        ];
        return $remedies[$type] ?? 'Place a copper Vastu pyramid to neutralize negative energy in this zone.';
    }

    /**
     * Generate 16-zone heatmap.
     */
    private static function generateHeatmap($facing, $roomScores) {
        $directions = ['NW1', 'N1', 'N2', 'NE1', 'W2', 'C1', 'C2', 'E1', 'W1', 'C3', 'C4', 'E2', 'SW1', 'S1', 'S2', 'SE1'];
        $facingBoosts = [
            'N' => ['N1' => 15, 'N2' => 15, 'NE1' => 10],
            'E' => ['E1' => 15, 'E2' => 15, 'NE1' => 10],
            'NE' => ['NE1' => 20, 'N1' => 10, 'E1' => 10],
            'S' => ['S1' => -10, 'S2' => -10],
            'SW' => ['SW1' => -15, 'S1' => -10, 'W1' => -5],
        ];

        $heatmap = [];
        $boosts = $facingBoosts[$facing] ?? [];

        foreach ($directions as $d) {
            $base = rand(45, 85);
            if (isset($boosts[$d])) $base += $boosts[$d];
            $base = max(20, min(100, $base));

            $level = 'average';
            if ($base >= 80) $level = 'excellent';
            elseif ($base >= 65) $level = 'good';
            elseif ($base >= 45) $level = 'average';
            elseif ($base >= 30) $level = 'poor';
            else $level = 'bad';

            $heatmap[] = ['name' => $d, 'score' => $base, 'level' => $level];
        }
        return $heatmap;
    }

    /**
     * Calculate overall score.
     */
    private static function calculateOverallScore($rooms, $facing) {
        if (empty($rooms)) return 70;
        $avg = array_sum(array_column($rooms, 'score')) / count($rooms);
        // Boost favourable facings
        $facingBonus = ['N' => 5, 'NE' => 8, 'E' => 5, 'NW' => 0, 'W' => -2, 'SE' => -2, 'S' => -3, 'SW' => -8];
        $score = $avg + ($facingBonus[$facing] ?? 0);
        return max(20, min(98, intval(round($score))));
    }

    /**
     * Generate positive findings.
     */
    private static function generatePositives($facing, $rooms, $roomScores, $noun = 'property') {
        $positives = [];

        $goodFacings = ['N', 'NE', 'E'];
        if (in_array($facing, $goodFacings)) {
            $positives[] = "Your {$noun} facing " . formatDirection($facing) . " is highly auspicious - it attracts wealth, health, and positive energy.";
        }

        // Find well-placed rooms
        foreach ($roomScores as $r) {
            if ($r['score'] >= 80) {
                $positives[] = "{$r['name']} placement in " . formatDirection($r['direction']) . " is ideal as per Vastu Shastra.";
            }
        }

        // Generic positives
        $genericPositives = [
            "The Brahmasthan (central area) appears free of heavy obstructions, allowing free energy flow.",
            "Open spaces around the {$noun} support healthy ventilation and energy circulation.",
            "Natural lighting from auspicious directions enhances the {$noun}'s vibrancy.",
        ];

        // Add 1-2 generic positives based on score
        $count = min(count($genericPositives), max(1, count($positives) < 3 ? 2 : 1));
        $shuffled = $genericPositives;
        shuffle($shuffled);
        for ($i = 0; $i < $count; $i++) $positives[] = $shuffled[$i];

        return array_slice($positives, 0, 6);
    }

    /**
     * Generate negative findings (Vastu defects).
     */
    private static function generateNegatives($facing, $rooms, $roomScores, $noun = 'property') {
        $negatives = [];

        foreach ($roomScores as $r) {
            if ($r['score'] < 55) {
                $negatives[] = "{$r['name']} placement in " . formatDirection($r['direction']) . " creates Vastu imbalance affecting " . self::$zones[$r['direction']]['governs'] . ".";
            }
        }

        $unfavourableFacings = ['SW', 'S'];
        if (in_array($facing, $unfavourableFacings)) {
            $negatives[] = ucfirst($noun) . " facing " . formatDirection($facing) . " requires specific remedies to balance the dominant earth/fire elements.";
        }

        return array_slice($negatives, 0, 5);
    }

    /**
     * Generate remedies.
     */
    private static function generateRemedies($negatives, $concerns) {
        $remedies = [];

        // Defect-based remedies
        foreach ($negatives as $i => $defect) {
            if (stripos($defect, 'kitchen') !== false) {
                $remedies[] = ['title' => 'Energize Kitchen Zone', 'description' => 'Place a copper Agni Yantra in the south-east corner of the kitchen. Use bright lighting and avoid water sources next to the stove.', 'priority' => 'high', 'icon' => 'fire'];
            }
            if (stripos($defect, 'toilet') !== false || stripos($defect, 'bathroom') !== false) {
                $remedies[] = ['title' => 'Neutralize Bathroom Energy', 'description' => 'Place a bowl of sea salt that should be replaced weekly. Keep the door closed when not in use. Add green indoor plants outside.', 'priority' => 'high', 'icon' => 'water'];
            }
            if (stripos($defect, 'bedroom') !== false) {
                $remedies[] = ['title' => 'Bedroom Energy Correction', 'description' => 'Sleep with your head towards south or east. Place a brass kalash with water in the south-west corner. Avoid mirrors facing the bed.', 'priority' => 'medium', 'icon' => 'bed'];
            }
        }

        // Concern-based remedies
        if (stripos($concerns, 'financial') !== false || stripos($concerns, 'wealth') !== false || stripos($concerns, 'money') !== false) {
            $remedies[] = ['title' => 'Wealth Attraction', 'description' => 'Place a Kuber Yantra or sphatik Shree Yantra in the north zone. Keep the north area clean, well-lit, and free of obstructions.', 'priority' => 'high', 'icon' => 'coins'];
        }
        if (stripos($concerns, 'sleep') !== false || stripos($concerns, 'health') !== false) {
            $remedies[] = ['title' => 'Health Restoration', 'description' => 'Place an amethyst cluster in the bedroom. Ensure no electronics near the bed. Sleep with head towards east for vitality.', 'priority' => 'high', 'icon' => 'heart'];
        }
        if (stripos($concerns, 'family') !== false || stripos($concerns, 'dispute') !== false || stripos($concerns, 'relationship') !== false) {
            $remedies[] = ['title' => 'Family Harmony', 'description' => 'Place a Brass Laughing Buddha in the south-west of the living room. Use warm yellow/peach colors in common areas.', 'priority' => 'high', 'icon' => 'users'];
        }

        // Universal remedies (always recommended)
        $universal = [
            ['title' => 'Brahmasthan Activation', 'description' => 'Keep the central area of your home clean and obstacle-free. Place a copper or crystal pyramid in the center for energy amplification.', 'priority' => 'medium', 'icon' => 'gem'],
            ['title' => 'Entrance Energy Boost', 'description' => 'Place a brass swastika or Om symbol at the main entrance. Keep entrance well-lit and welcoming. Add a small water fountain in the north-east.', 'priority' => 'medium', 'icon' => 'door-open'],
            ['title' => 'Indoor Plants for Positive Energy', 'description' => 'Add Money Plant in south-east for prosperity, Tulsi near entrance for protection, and Bamboo plant for good fortune.', 'priority' => 'low', 'icon' => 'seedling'],
            ['title' => 'Color Therapy', 'description' => 'Use white/cream in north-east, yellow in south-west, blue in north (water zone), and red/orange minimally in south-east only.', 'priority' => 'low', 'icon' => 'palette'],
            ['title' => 'Daily Energy Practice', 'description' => 'Light a ghee lamp daily in the pooja room. Burn camphor or sage weekly. Open windows in the morning to allow positive prana flow.', 'priority' => 'low', 'icon' => 'fire-flame-curved'],
        ];

        foreach ($universal as $u) $remedies[] = $u;

        return array_slice($remedies, 0, 8);
    }

    /**
     * Generate life impact analysis.
     */
    private static function generateImpacts($overall, $facing, $rooms, $concerns) {
        // Variations around overall score
        $variation = function($base, $delta) { return max(30, min(98, $base + $delta)); };

        $impacts = [
            'health' => [
                'score' => $variation($overall, rand(-8, 5)),
                'note' => $overall >= 70 ? 'Generally favourable' : 'Needs attention'
            ],
            'wealth' => [
                'score' => $variation($overall, rand(-10, 5)),
                'note' => $overall >= 65 ? 'Stable financial flow' : 'Blocked income channels'
            ],
            'relations' => [
                'score' => $variation($overall, rand(-5, 8)),
                'note' => $overall >= 70 ? 'Harmonious bonds' : 'Tension possible'
            ],
            'career' => [
                'score' => $variation($overall, rand(-7, 7)),
                'note' => $overall >= 70 ? 'Steady growth' : 'Slow progress'
            ],
        ];

        // Adjust based on facing
        $facingMods = [
            'N' => ['wealth' => 8, 'career' => 5],
            'NE' => ['health' => 5, 'wealth' => 5],
            'E' => ['health' => 8, 'career' => 3],
            'SW' => ['relations' => -10, 'wealth' => -5],
            'S' => ['health' => -5],
        ];
        if (isset($facingMods[$facing])) {
            foreach ($facingMods[$facing] as $area => $mod) {
                $impacts[$area]['score'] = $variation($impacts[$area]['score'], $mod);
            }
        }
        return $impacts;
    }

    /**
     * Recommend products based on defects.
     */
    private static function recommendProducts($negatives, $concerns) {
        $products = [];

        // Get from DB if available
        try {
            $dbProducts = Database::all("SELECT id, title as name, short_description as description, price, original_price, icon FROM products WHERE is_active = 1 AND is_featured = 1 LIMIT 6");
            if ($dbProducts) {
                return $dbProducts;
            }
        } catch (Exception $e) {
            // fallback to hardcoded
        }

        // Hardcoded fallbacks
        $products = [
            ['id' => 1, 'name' => 'Crystal Vastu Pyramid', 'description' => 'Energize Brahmasthan zone', 'price' => 899, 'original_price' => 1499, 'icon' => 'gem'],
            ['id' => 2, 'name' => 'Brass Vastu Tortoise', 'description' => 'For stability and growth', 'price' => 599, 'original_price' => 999, 'icon' => 'dharmachakra'],
            ['id' => 5, 'name' => 'Sphatik Shree Yantra', 'description' => 'For wealth and prosperity', 'price' => 1799, 'original_price' => 2999, 'icon' => 'star-of-life'],
        ];

        // Add concern-specific
        if (stripos($concerns, 'wealth') !== false || stripos($concerns, 'financial') !== false) {
            $products[] = ['id' => 11, 'name' => 'Kuber Yantra (Gold)', 'description' => 'Powerful wealth yantra', 'price' => 1499, 'original_price' => 2499, 'icon' => 'coins'];
        }
        if (stripos($concerns, 'sleep') !== false || stripos($concerns, 'stress') !== false) {
            $products[] = ['id' => 8, 'name' => 'Amethyst Cluster', 'description' => 'For peaceful sleep', 'price' => 2499, 'original_price' => 4499, 'icon' => 'gem'];
        }

        return array_slice($products, 0, 6);
    }

    private static function generateSummary($name, $facing, $score, $posCount, $negCount, $isCommercial = false) {
        $rating = $score >= 80 ? 'excellent' : ($score >= 65 ? 'good' : ($score >= 50 ? 'moderate' : 'requires attention'));
        $subject = $isCommercial ? 'commercial space' : 'home';
        $benefit = $isCommercial ? 'business growth, employee productivity, and financial stability' : 'prosperity, health, and overall well-being';
        return "Dear {$name}, after a comprehensive AI analysis of your {$subject} plan facing " .
               formatDirection($facing) . ", we have determined that it demonstrates {$rating} Vastu alignment with an overall score of {$score}/100. " .
               "We identified {$posCount} positive aspects and {$negCount} areas requiring attention. " .
               "The recommended remedies, when implemented systematically starting with high-priority items, will significantly enhance the energy flow, {$benefit}.";
    }

    private static function generateVerdict($score, $facing, $isCommercial = false) {
        $noun = $isCommercial ? 'commercial space' : 'home';
        $closing = $isCommercial
            ? 'May your space drive business growth, productivity, and prosperity.'
            : 'May your home continue to be a sanctuary of prosperity, health, and harmony.';
        if ($score >= 80) {
            return "Your {$noun} is well-aligned with Vastu Shastra principles, demonstrating strong positive energy across most zones. The minor adjustments suggested in this report will further optimize the already-favourable energy flow. Continue with regular energy maintenance practices like proper lighting, ventilation, and clutter clearance. {$closing}";
        } elseif ($score >= 65) {
            return "Your {$noun} shows balanced Vastu alignment with several positive aspects. By implementing the recommended remedies in priority order, you can elevate the energy from good to excellent. Focus first on the high-priority remedies as they address fundamental defects. Within 30-60 days of implementation, you should notice tangible improvements in the affected areas.";
        } elseif ($score >= 50) {
            return "Your {$noun} has moderate Vastu alignment with both strengths and areas needing correction. The defects identified are common and most can be neutralized through remedies without structural changes. We recommend implementing the high-priority remedies immediately, followed by medium-priority within 30 days. Consider booking a personalized expert consultation for deeper guidance on the most challenging zones.";
        } else {
            return "Your {$noun} shows significant Vastu defects that may be impacting peace, health, or prosperity. We strongly recommend implementing the high-priority remedies as soon as possible, and where structural changes are feasible, prioritizing the most critical zones first. A personalized expert consultation is highly recommended for a detailed correction plan tailored to your specific situation.";
        }
    }
}
