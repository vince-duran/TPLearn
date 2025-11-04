<?php
/**
 * Philippine Locations Data
 * Provides provinces, cities/municipalities, and barangays data for address forms
 * Uses comprehensive PSA data from ph-location.json
 */

// Load the comprehensive Philippine location data
$philippineLocationData = null;

/**
 * Load location data from JSON file
 */
function loadLocationData() {
    global $philippineLocationData;
    
    if ($philippineLocationData === null) {
        $jsonFile = __DIR__ . '/ph-location.json';
        if (file_exists($jsonFile)) {
            $jsonContent = file_get_contents($jsonFile);
            $philippineLocationData = json_decode($jsonContent, true);
        } else {
            $philippineLocationData = [];
        }
    }
    
    return $philippineLocationData;
}

/**
 * Get all provinces from all regions
 */
function getProvinces() {
    $data = loadLocationData();
    $provinces = [];
    
    foreach ($data as $regionCode => $regionData) {
        if (isset($regionData['province_list'])) {
            foreach ($regionData['province_list'] as $provinceName => $provinceData) {
                $provinces[] = $provinceName;
            }
        }
    }
    
    // Sort provinces alphabetically
    sort($provinces);
    return $provinces;
}

/**
 * Get cities/municipalities by province
 */
function getCitiesByProvince($province) {
    $data = loadLocationData();
    $cities = [];
    
    foreach ($data as $regionCode => $regionData) {
        if (isset($regionData['province_list'][$province])) {
            $provinceData = $regionData['province_list'][$province];
            if (isset($provinceData['municipality_list'])) {
                foreach ($provinceData['municipality_list'] as $cityName => $cityData) {
                    $cities[] = $cityName;
                }
            }
            break;
        }
    }
    
    // Sort cities alphabetically
    sort($cities);
    return $cities;
}

/**
 * Get barangays by province and city
 */
function getBarangaysByCity($province, $city) {
    $data = loadLocationData();
    $barangays = [];
    
    foreach ($data as $regionCode => $regionData) {
        if (isset($regionData['province_list'][$province])) {
            $provinceData = $regionData['province_list'][$province];
            if (isset($provinceData['municipality_list'][$city])) {
                $cityData = $provinceData['municipality_list'][$city];
                if (isset($cityData['barangay_list'])) {
                    $barangays = $cityData['barangay_list'];
                }
                break;
            }
        }
    }
    
    // Sort barangays alphabetically
    sort($barangays);
    return $barangays;
}

/**
 * Get all location data as JSON for frontend
 */
function getLocationDataAsJson() {
    $data = loadLocationData();
    
    // Transform the data to a more frontend-friendly format
    $transformedData = [];
    
    foreach ($data as $regionCode => $regionData) {
        if (isset($regionData['province_list'])) {
            foreach ($regionData['province_list'] as $provinceName => $provinceData) {
                if (isset($provinceData['municipality_list'])) {
                    $transformedData[$provinceName] = [];
                    foreach ($provinceData['municipality_list'] as $cityName => $cityData) {
                        if (isset($cityData['barangay_list'])) {
                            $transformedData[$provinceName][$cityName] = $cityData['barangay_list'];
                        }
                    }
                }
            }
        }
    }
    
    return json_encode($transformedData);
}

/**
 * Search provinces by name (for autocomplete/search functionality)
 */
function searchProvinces($searchTerm) {
    $provinces = getProvinces();
    $results = [];
    
    foreach ($provinces as $province) {
        if (stripos($province, $searchTerm) !== false) {
            $results[] = $province;
        }
    }
    
    return $results;
}

/**
 * Search cities by name and optionally filter by province
 */
function searchCities($searchTerm, $province = null) {
    $data = loadLocationData();
    $results = [];
    
    foreach ($data as $regionCode => $regionData) {
        if (isset($regionData['province_list'])) {
            foreach ($regionData['province_list'] as $provinceName => $provinceData) {
                // If province filter is specified, only search within that province
                if ($province && $provinceName !== $province) {
                    continue;
                }
                
                if (isset($provinceData['municipality_list'])) {
                    foreach ($provinceData['municipality_list'] as $cityName => $cityData) {
                        if (stripos($cityName, $searchTerm) !== false) {
                            $results[] = [
                                'city' => $cityName,
                                'province' => $provinceName
                            ];
                        }
                    }
                }
            }
        }
    }
    
    return $results;
}

/**
 * Get region information for a given province
 */
function getRegionByProvince($province) {
    $data = loadLocationData();
    
    foreach ($data as $regionCode => $regionData) {
        if (isset($regionData['province_list'][$province])) {
            return [
                'region_code' => $regionCode,
                'region_name' => $regionData['region_name'] ?? ''
            ];
        }
    }
    
    return null;
}