<?php
// ai_auto_match.php – HYBRID AI + GEOSPATIAL FUSING ENGINE
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';
require_once 'send_notification.php';
require_once 'notifications.php';

define('DEBUG_MODE', true);

function debug_log($message) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        file_put_contents('ai_debug.log', date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
    }
}

// Haversine Great Circle Distance Formula (Returns distance in meters)
function calculateHaversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Radius of the earth in meters
    $latDelta = deg2rad($lat2 - $lat1);
    $lonDelta = deg2rad($lon2 - $lon1);
    $a = sin($latDelta / 2) * sin($latDelta / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lonDelta / 2) * sin($lonDelta / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

function aiAutoMatch($new_item_id, $new_report_type, $new_category, $new_location, $new_text, $new_image_path = null) {
    global $conn;

    // Auto-create log table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS ai_match_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        original_item_id INT NOT NULL,
        matched_item_id INT NOT NULL,
        score INT NOT NULL,
        reason TEXT,
        status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    debug_log("===== HYBRID AI + SPATIAL MATCH START =====");
    debug_log("New Item ID: $new_item_id, Type: $new_report_type, Category: $new_category");

    $GEMINI_API_KEY = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
    $model = 'gemini-3.6-flash';
    $GEMINI_URL = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $GEMINI_API_KEY;

    // 1. Fetch Spatial Data for the NEW item
    $stmt_new = $conn->prepare("SELECT latitude, longitude, search_radius, location_method FROM item_reports WHERE id = ?");
    $stmt_new->bind_param("i", $new_item_id);
    $stmt_new->execute();
    $new_spatial = $stmt_new->get_result()->fetch_assoc();
    
    $new_lat = floatval($new_spatial['latitude'] ?? 0);
    $new_lng = floatval($new_spatial['longitude'] ?? 0);
    $new_rad = intval($new_spatial['search_radius'] ?? 100);

    // 2. Fetch Candidates with their Spatial Data
    $opposite_type = ($new_report_type === 'lost') ? 'found' : 'lost';

    $stmt = $conn->prepare("SELECT id, item_name, category, description, image_path, user_id, location, latitude, longitude, search_radius 
                            FROM item_reports 
                            WHERE report_type = ? 
                              AND category = ? 
                              AND status != 'returned' 
                              AND id != ? 
                            ORDER BY created_at DESC 
                            LIMIT 5");
    $stmt->bind_param("ssi", $opposite_type, $new_category, $new_item_id);
    $stmt->execute();
    $candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($candidates)) {
        debug_log("No candidates found. Exiting.");
        return 0;
    }

    // ==========================================
    // BUILD INTERLEAVED VISION PROMPT
    // ==========================================
    $parts = [];
    
    $system_instruction = "You are an expert Computer Vision AI for a university lost and found.\n";
    $system_instruction .= "CRITICAL INSTRUCTION: Analyze the text and photos. Base your score SOLELY on Visual and Semantic (Text) similarity. Ignore physical distance, as the PHP backend handles spatial mathematics separately.\n\n";
    $system_instruction .= "=== NEWLY REPORTED ITEM (ID #$new_item_id) ===\n";
    $system_instruction .= "Text details: Type: ".strtoupper($new_report_type).", Category: $new_category, Desc: $new_text\n";
    $system_instruction .= "Here is the photo of the NEW ITEM:\n";
    $parts[] = ['text' => $system_instruction];

    if (!empty($new_image_path) && file_exists($new_image_path)) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => mime_content_type($new_image_path),
                'data' => base64_encode(file_get_contents($new_image_path))
            ]
        ];
    } else {
        $parts[] = ['text' => "(No photo provided for this new item)\n"];
    }

    $parts[] = ['text' => "\n\n=== DATABASE CANDIDATES TO COMPARE AGAINST ===\n"];

    foreach ($candidates as $c) {
        $candidate_text = "--- Candidate ID: {$c['id']} ---\n";
        $candidate_text .= "Text details: Name: {$c['item_name']}, Desc: {$c['description']}\n";
        $candidate_text .= "Photo of Candidate {$c['id']}:\n";
        $parts[] = ['text' => $candidate_text];

        if (!empty($c['image_path']) && file_exists($c['image_path'])) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => mime_content_type($c['image_path']),
                    'data' => base64_encode(file_get_contents($c['image_path']))
                ]
            ];
        } else {
            $parts[] = ['text' => "(No photo provided for this candidate)\n"];
        }
    }

    $scoring_rules = "\n\nINSTRUCTIONS FOR OUTPUT:\n";
    $scoring_rules .= "1. Give a score (0-100) based strictly on VISUAL and TEXT similarity.\n";
    $scoring_rules .= "2. Provide a 'reason' explaining what visual traits match.\n";
    $scoring_rules .= "3. Return ONLY a valid JSON object. Example:\n";
    $scoring_rules .= "{\"12\": {\"score\": 95, \"reason\": \"Visually identical. Both photos display a black leather wallet.\"}}\n";

    $parts[] = ['text' => $scoring_rules];
    $payload = ['contents' => [['parts' => $parts]]];

    // ==========================================
    // GEMINI API CALL
    // ==========================================
    $ch = curl_init($GEMINI_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) { return 0; }

    $data = json_decode($response, true);
    $raw_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $clean_text = trim(preg_replace('/```json\s*|```\s*/', '', $raw_text));
    $scores = json_decode($clean_text, true);

    if (!is_array($scores)) return 0;

    $matches_found = 0;
    
    // ==========================================
    // APPLY SPATIAL DECAY MATH (HAVERSINE)
    // ==========================================
    foreach ($scores as $candidate_id => $result_data) {
        $ai_score = intval($result_data['score'] ?? 0);
        $reason = $result_data['reason'] ?? 'High visual similarity detected by AI.';
        
        // Find matching candidate spatial data
        $cand = null;
        foreach ($candidates as $c) { if ($c['id'] == $candidate_id) { $cand = $c; break; } }
        
        $final_score = $ai_score;
        
        // If both items have valid GPS coordinates, apply spatial logic
        if ($cand && $new_lat != 0 && floatval($cand['latitude']) != 0) {
            $dist = calculateHaversineDistance($new_lat, $new_lng, floatval($cand['latitude']), floatval($cand['longitude']));
            $cand_rad = intval($cand['search_radius'] ?? 100);
            
            // If the search radius is huge (uncertain), bypass the penalty
            if ($new_rad >= 1000 || $cand_rad >= 1000) {
                $reason .= " [🌐 Spatial: Wide search area. Exact distance not penalized.]";
            } else {
                // Calculate Exponential Decay
                // Weight: AI (90%) + Distance (10%)
                $max_rad = max($new_rad, $cand_rad, 50); // Avoid div by zero
                $spatial_score = 10 * exp(-$dist / $max_rad); 
                
                $final_score = round(($ai_score * 0.9) + $spatial_score);
                $reason .= " [📍 Spatial: Items logged " . round($dist) . "m apart. Proximity score applied.]";
            }
        }

        // If final hybrid score is above threshold, trigger match
        if ($final_score >= 70) {
            $matches_found++;
            
            $log_stmt = $conn->prepare("INSERT INTO ai_match_logs (original_item_id, matched_item_id, score, reason) VALUES (?, ?, ?, ?)");
            $log_stmt->bind_param("iiis", $new_item_id, $candidate_id, $final_score, $reason);
            $log_stmt->execute();

            foreach ($candidates as $cand_user) {
                if ($cand_user['id'] == $candidate_id) {
                    $u_stmt = $conn->prepare("SELECT id, email, full_name FROM users WHERE id = ?");
                    $u_stmt->bind_param("i", $cand_user['user_id']);
                    $u_stmt->execute();
                    $user_data = $u_stmt->get_result()->fetch_assoc();

                    if ($user_data) {
                        notifyMatch($user_data['email'], $user_data['full_name'], $cand_user['item_name'], $candidate_id);
                        addNotification(
                            $user_data['id'],
                            'match',
                            "AI Match Found for: " . $cand_user['item_name'] . " (" . $final_score . "% match)",
                            'review_matches.php'
                        );
                    }
                    break;
                }
            }
        }
    }
    return $matches_found;
}
?>