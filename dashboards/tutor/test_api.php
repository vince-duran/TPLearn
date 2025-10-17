<?php
session_start();
require_once '../../includes/db.php';

// Set tutor session
$_SESSION['user_id'] = 8;
$_SESSION['role'] = 'tutor';

echo "<!DOCTYPE html>";
echo "<html><head><title>API Test</title></head><body>";
echo "<h1>Assignment Submissions API Test</h1>";

echo "<h2>Session Info:</h2>";
echo "<p>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p>Role: " . ($_SESSION['role'] ?? 'Not set') . "</p>";

echo "<h2>Test API Call:</h2>";
echo "<button onclick='testAPI()'>Test API</button>";
echo "<div id='result'></div>";

echo "<script>";
echo "function testAPI() {";
echo "  const resultDiv = document.getElementById('result');";
echo "  resultDiv.innerHTML = 'Testing API...';";
echo "  ";
echo "  fetch('../../api/get-assignment-submissions.php?material_id=9')";
echo "    .then(response => {";
echo "      console.log('Response status:', response.status);";
echo "      return response.text();";
echo "    })";
echo "    .then(text => {";
echo "      console.log('Raw response:', text);";
echo "      resultDiv.innerHTML = '<h3>Raw Response:</h3><pre>' + text + '</pre>';";
echo "      try {";
echo "        const data = JSON.parse(text);";
echo "        resultDiv.innerHTML += '<h3>Parsed JSON:</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>';";
echo "      } catch (e) {";
echo "        resultDiv.innerHTML += '<h3>JSON Parse Error:</h3><p>' + e.message + '</p>';";
echo "      }";
echo "    })";
echo "    .catch(error => {";
echo "      console.error('Error:', error);";
echo "      resultDiv.innerHTML = '<h3>Error:</h3><p>' + error.message + '</p>';";
echo "    });";
echo "}";
echo "</script>";

echo "</body></html>";
?>