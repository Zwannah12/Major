<?php
// Rwanda provinces, districts, and approximate district-center coordinates.
$rwanda_provinces = [
    "Kigali City" => [
        "Gasabo" => ["lat" => -1.8840, "lon" => 30.1240],
        "Kicukiro" => ["lat" => -1.9950, "lon" => 30.1190],
        "Nyarugenge" => ["lat" => -1.9500, "lon" => 30.0588],
    ],
    "Eastern Province" => [
        "Bugesera" => ["lat" => -2.2345, "lon" => 30.1576],
        "Gatsibo" => ["lat" => -1.7736, "lon" => 30.4567],
        "Kayonza" => ["lat" => -1.8919, "lon" => 30.6435],
        "Kirehe" => ["lat" => -2.2572, "lon" => 30.7382],
        "Ngoma" => ["lat" => -2.2096, "lon" => 30.5338],
        "Nyagatare" => ["lat" => -1.2917, "lon" => 30.3276],
        "Rwamagana" => ["lat" => -1.9487, "lon" => 30.4347],
    ],
    "Northern Province" => [
        "Burera" => ["lat" => -1.4837, "lon" => 29.8597],
        "Gakenke" => ["lat" => -1.7042, "lon" => 29.7850],
        "Gicumbi" => ["lat" => -1.5589, "lon" => 30.0644],
        "Musanze" => ["lat" => -1.4998, "lon" => 29.6349],
        "Rulindo" => ["lat" => -1.6766, "lon" => 29.9711],
    ],
    "Southern Province" => [
        "Gisagara" => ["lat" => -2.6066, "lon" => 29.8306],
        "Huye" => ["lat" => -2.5967, "lon" => 29.7394],
        "Kamonyi" => ["lat" => -2.0060, "lon" => 29.8837],
        "Muhanga" => ["lat" => -2.0856, "lon" => 29.7532],
        "Nyamagabe" => ["lat" => -2.4705, "lon" => 29.5803],
        "Nyanza" => ["lat" => -2.3519, "lon" => 29.7509],
        "Nyaruguru" => ["lat" => -2.7437, "lon" => 29.5720],
        "Ruhango" => ["lat" => -2.2227, "lon" => 29.7800],
    ],
    "Western Province" => [
        "Karongi" => ["lat" => -2.0604, "lon" => 29.3478],
        "Ngororero" => ["lat" => -1.8608, "lon" => 29.6278],
        "Nyabihu" => ["lat" => -1.6561, "lon" => 29.5572],
        "Nyamasheke" => ["lat" => -2.3286, "lon" => 29.1477],
        "Rubavu" => ["lat" => -1.6837, "lon" => 29.3284],
        "Rusizi" => ["lat" => -2.4833, "lon" => 28.9075],
        "Rutsiro" => ["lat" => -1.9480, "lon" => 29.3258],
    ],
];

$rwanda_districts = [];
foreach ($rwanda_provinces as $province => $districts) {
    foreach ($districts as $district => $coordinates) {
        $rwanda_districts[$district] = $coordinates + ["province" => $province];
    }
}

function get_all_provinces() {
    global $rwanda_provinces;
    return array_keys($rwanda_provinces);
}

function get_districts_by_province($province) {
    global $rwanda_provinces;
    if (!isset($rwanda_provinces[$province])) {
        return [];
    }

    return array_keys($rwanda_provinces[$province]);
}

function get_all_districts() {
    global $rwanda_districts;
    return array_keys($rwanda_districts);
}

function get_all_district_data() {
    global $rwanda_districts;
    return $rwanda_districts;
}

function get_province_district_data() {
    global $rwanda_provinces;
    return $rwanda_provinces;
}

function get_district_coordinates($district) {
    global $rwanda_districts;
    if (isset($rwanda_districts[$district])) {
        return [
            "lat" => $rwanda_districts[$district]["lat"],
            "lon" => $rwanda_districts[$district]["lon"],
        ];
    }

    return ["lat" => -1.9500, "lon" => 30.0588];
}

function get_district_province($district) {
    global $rwanda_districts;
    return $rwanda_districts[$district]["province"] ?? "";
}
?>
