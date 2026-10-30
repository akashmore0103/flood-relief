<?php
/**
 * Flood Risk Rule Engine (Decision Support Model)
 * Complies with PRD Section 5.2 and Section 10
 */

require_once dirname(__DIR__) . '/core/Database.php';

class RiskRuleEngine {
    private static ?array $activeRule = null;

    /**
     * Load the active risk calculation rule from database
     */
    public static function getActiveRule(): array {
        if (self::$activeRule === null) {
            $db = Database::getInstance();
            $rule = $db->fetchOne("SELECT * FROM `risk_rules` WHERE `is_active` = TRUE ORDER BY `id` DESC LIMIT 1");

            if (!$rule) {
                // Fallback default rule if database is unseeded
                $rule = [
                    'id' => 1,
                    'rule_name' => 'Default Hardcoded Rule',
                    'rainfall_weight' => 0.50,
                    'river_weight' => 0.50,
                    'max_rainfall_baseline' => 250.00,
                    'max_river_baseline' => 15.00,
                    'low_max_score' => 25.00,
                    'mod_max_score' => 50.00,
                    'high_max_score' => 75.00
                ];
            }
            self::$activeRule = $rule;
        }
        return self::$activeRule;
    }

    /**
     * Calculate normalized scores, final composite risk score, and risk category
     * 
     * @param float $rainfallMm Observed rainfall in millimeters
     * @param float $riverLevelM Observed river water level in meters
     * @return array Calculated metrics
     */
    public static function calculate(float $rainfallMm, float $riverLevelM): array {
        $rule = self::getActiveRule();

        $maxRain = (float)($rule['max_rainfall_baseline'] > 0 ? $rule['max_rainfall_baseline'] : 250.00);
        $maxRiver = (float)($rule['max_river_baseline'] > 0 ? $rule['max_river_baseline'] : 15.00);

        $wRain = (float)$rule['rainfall_weight'];
        $wRiver = (float)$rule['river_weight'];

        // Normalize to 0 - 100 scale
        $normRain = min(100.0, max(0.0, ($rainfallMm / $maxRain) * 100.0));
        $normRiver = min(100.0, max(0.0, ($riverLevelM / $maxRiver) * 100.0));

        // Calculate composite score
        $compositeScore = round(($normRain * $wRain) + ($normRiver * $wRiver), 2);
        $compositeScore = min(100.0, max(0.0, $compositeScore));

        // Determine Category
        $lowMax = (float)$rule['low_max_score'];
        $modMax = (float)$rule['mod_max_score'];
        $highMax = (float)$rule['high_max_score'];

        if ($compositeScore <= $lowMax) {
            $category = 'Low';
        } elseif ($compositeScore <= $modMax) {
            $category = 'Moderate';
        } elseif ($compositeScore <= $highMax) {
            $category = 'High';
        } else {
            $category = 'Critical';
        }

        return [
            'score' => $compositeScore,
            'category' => $category,
            'rainfall_mm' => $rainfallMm,
            'river_level_m' => $riverLevelM,
            'rainfall_normalized' => round($normRain, 2),
            'river_normalized' => round($normRiver, 2),
            'weights' => [
                'rainfall' => $wRain,
                'river' => $wRiver
            ],
            'baselines' => [
                'max_rainfall' => $maxRain,
                'max_river' => $maxRiver
            ],
            'guidance' => self::getSafetyGuidance($category)
        ];
    }

    /**
     * Determine if observation data is stale (> 24 hours old) per PRD FR-08
     */
    public static function isStale(?string $observedAt): bool {
        if (!$observedAt) return true;
        $observedTimestamp = strtotime($observedAt);
        $diffHours = (time() - $observedTimestamp) / 3600;
        return $diffHours > DATA_STALE_HOURS;
    }

    /**
     * Get safety actions and recommendations for citizens and authorities
     */
    public static function getSafetyGuidance(string $category): array {
        return match (strtoupper($category)) {
            'CRITICAL' => [
                'summary' => 'Extreme Danger: Severe inundation imminent or actively occurring.',
                'color' => 'danger',
                'badge' => 'badge-risk-critical',
                'actions' => [
                    'Evacuate immediately to designated relief shelters on higher ground.',
                    'Turn off main electrical switches and gas valves before leaving.',
                    'Do not attempt to walk, swim, or drive through flowing floodwaters.',
                    'Submit an Emergency Help Request or dial 112 if trapped.'
                ],
                'coordinator_action' => 'Mobilize rescue boats, open all standby relief centers, and alert district disaster response teams.'
            ],
            'HIGH' => [
                'summary' => 'High Danger: Rapidly rising river levels and intense rainfall.',
                'color' => 'warning',
                'badge' => 'badge-risk-high',
                'actions' => [
                    'Prepare emergency grab-bags with documents, medicines, and drinking water.',
                    'Move elderly family members, children, and livestock to safe locations.',
                    'Identify your nearest operational relief camp using the camp locator map.',
                    'Keep mobile phones fully charged and monitor hourly broadcasts.'
                ],
                'coordinator_action' => 'Ensure relief camps have stocked dry rations, clean drinking water, and medical first-aid kits.'
            ],
            'MODERATE' => [
                'summary' => 'Elevated Alert: Saturated soil and waterlogging in low-lying catchments.',
                'color' => 'info',
                'badge' => 'badge-risk-moderate',
                'actions' => [
                    'Clear localized drainage channels around residential premises.',
                    'Avoid unnecessary travel across bridges and low-lying causeways.',
                    'Store 3 days of non-perishable provisions and clean drinking water.',
                    'Review flood risk bulletins regularly.'
                ],
                'coordinator_action' => 'Inspect shelter standby preparedness and verify communication channels.'
            ],
            default => [
                'summary' => 'Normal / Low Risk: Hydrological levels within safe seasonal parameters.',
                'color' => 'success',
                'badge' => 'badge-risk-low',
                'actions' => [
                    'Conditions are stable. Continue monitoring routine meteorological forecasts.',
                    'Keep local emergency helpline numbers saved on family mobile phones.'
                ],
                'coordinator_action' => 'Standard routine monitoring.'
            ]
        };
    }
}
