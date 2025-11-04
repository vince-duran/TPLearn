<?php
/**
 * Location API Endpoint
 * Provides provinces, cities, and barangays data for cascading dropdowns
 * Now using comprehensive PSA data from ph-location.json
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/philippine_locations.php';

// Get the request type
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'provinces':
            // Return all provinces
            $provinces = getProvinces();
            echo json_encode([
                'success' => true,
                'data' => $provinces,
                'count' => count($provinces)
            ]);
            break;
            
        case 'cities':
            // Return cities for a specific province
            $province = $_GET['province'] ?? '';
            if (empty($province)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Province parameter is required'
                ]);
                break;
            }
            
            $cities = getCitiesByProvince($province);
            echo json_encode([
                'success' => true,
                'data' => $cities,
                'province' => $province,
                'count' => count($cities)
            ]);
            break;
            
        case 'barangays':
            // Return barangays for a specific province and city
            $province = $_GET['province'] ?? '';
            $city = $_GET['city'] ?? '';
            
            if (empty($province) || empty($city)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Province and city parameters are required'
                ]);
                break;
            }
            
            $barangays = getBarangaysByCity($province, $city);
            echo json_encode([
                'success' => true,
                'data' => $barangays,
                'province' => $province,
                'city' => $city,
                'count' => count($barangays)
            ]);
            break;
            
        case 'search_provinces':
            // Search provinces by name
            $searchTerm = $_GET['q'] ?? '';
            if (empty($searchTerm)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Search term parameter (q) is required'
                ]);
                break;
            }
            
            $results = searchProvinces($searchTerm);
            echo json_encode([
                'success' => true,
                'data' => $results,
                'search_term' => $searchTerm,
                'count' => count($results)
            ]);
            break;
            
        case 'search_cities':
            // Search cities by name with optional province filter
            $searchTerm = $_GET['q'] ?? '';
            $province = $_GET['province'] ?? null;
            
            if (empty($searchTerm)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Search term parameter (q) is required'
                ]);
                break;
            }
            
            $results = searchCities($searchTerm, $province);
            echo json_encode([
                'success' => true,
                'data' => $results,
                'search_term' => $searchTerm,
                'province_filter' => $province,
                'count' => count($results)
            ]);
            break;
            
        case 'region_info':
            // Get region information for a province
            $province = $_GET['province'] ?? '';
            if (empty($province)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Province parameter is required'
                ]);
                break;
            }
            
            $regionInfo = getRegionByProvince($province);
            if ($regionInfo) {
                echo json_encode([
                    'success' => true,
                    'data' => $regionInfo,
                    'province' => $province
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Province not found'
                ]);
            }
            break;
            
        case 'all':
            // Return all location data (use with caution - large dataset)
            echo json_encode([
                'success' => true,
                'data' => json_decode(getLocationDataAsJson(), true),
                'warning' => 'This endpoint returns a large dataset. Consider using specific endpoints for better performance.'
            ]);
            break;
            
        case 'stats':
            // Return statistics about the location data
            $provinces = getProvinces();
            $totalCities = 0;
            $totalBarangays = 0;
            
            foreach ($provinces as $province) {
                $cities = getCitiesByProvince($province);
                $totalCities += count($cities);
                
                foreach ($cities as $city) {
                    $barangays = getBarangaysByCity($province, $city);
                    $totalBarangays += count($barangays);
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_provinces' => count($provinces),
                    'total_cities' => $totalCities,
                    'total_barangays' => $totalBarangays,
                    'data_source' => 'Philippine Statistics Authority (PSA)'
                ]
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action. Supported actions: provinces, cities, barangays, search_provinces, search_cities, region_info, all, stats'
            ]);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>