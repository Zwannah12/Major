<?php
// Weather API endpoint for districts
header('Content-Type: application/json');
require_once "../includes/functions.php";
require_once "../includes/districts.php";

function weather_response_for_district($district) {
    $coords = get_district_coordinates($district);
    $lat = $coords['lat'];
    $lon = $coords['lon'];

    $weatherUrl = "http://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&current=temperature_2m,weather_code,wind_speed_10m,relative_humidity_2m";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $weatherUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        return ['success' => false, 'district' => $district, 'error' => 'Weather API unavailable'];
    }

    $data = json_decode($response, true);
    if (!isset($data['current'])) {
        return ['success' => false, 'district' => $district, 'error' => 'Invalid response'];
    }

    return [
        'success' => true,
        'district' => $district,
        'province' => get_district_province($district),
        'temperature' => $data['current']['temperature_2m'],
        'weather_code' => $data['current']['weather_code'],
        'wind_speed' => $data['current']['wind_speed_10m'],
        'humidity' => $data['current']['relative_humidity_2m'],
        'coordinates' => $coords
    ];
}

if (isset($_GET['district'])) {
    $district = sanitize_input($_GET['district']);
    if ($district === 'all') {
        $weather = [];
        foreach (get_all_districts() as $district_name) {
            $weather[] = weather_response_for_district($district_name);
        }
        echo json_encode(['success' => true, 'weather' => $weather]);
        exit;
    }

    echo json_encode(weather_response_for_district($district));
} elseif (isset($_GET['province'])) {
    $province = sanitize_input($_GET['province']);
    echo json_encode([
        'success' => true,
        'province' => $province,
        'districts' => get_districts_by_province($province)
    ]);
} else {
    echo json_encode([
        'success' => true,
        'provinces' => get_all_provinces(),
        'districts' => get_all_districts(),
        'data' => get_province_district_data()
    ]);
}
?>
