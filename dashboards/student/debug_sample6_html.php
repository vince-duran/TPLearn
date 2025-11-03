<?php
/**
 * Debug the exact HTML output for Sample 6 in student enrollment
 */

require_once '../../includes/data-helpers.php';
require_once '../../assets/icons.php';

// Get the exact same data as student enrollment
$programs = getProgramsWithCalculatedStatus();

// Find Sample 6
$sample6 = null;
foreach ($programs as $program) {
    if ($program['name'] === 'Sample 6') {
        $sample6 = $program;
        break;
    }
}

if (!$sample6) {
    echo "<h2>❌ Sample 6 Not Found</h2>";
    exit;
}

echo "<h2>Sample 6 HTML Generation Debug</h2>";

// Replicate the exact logic from student enrollment
$status_badge = getStatusBadge($sample6['calculated_status']);
$enrollment_badge = $sample6['enrollment_status'] ? getStatusBadge($sample6['enrollment_status']) : null;

// Category gradients (copied from student enrollment)
$categoryGradients = [
    'Technology' => 'from-blue-500 to-purple-600',
    'Mathematics' => 'from-green-500 to-blue-600',
    'Science' => 'from-purple-500 to-pink-600',
    'Language' => 'from-yellow-500 to-orange-600',
    'Arts' => 'from-pink-500 to-red-600',
    'Music' => 'from-indigo-500 to-purple-600',
    'Sports' => 'from-green-500 to-teal-600',
    'Early Childhood' => 'from-tplearn-green to-tplearn-light-green',
    'Primary Education' => 'from-blue-400 to-indigo-500',
    'Language Arts' => 'from-yellow-400 to-orange-500',
    'General' => 'from-gray-500 to-gray-600'
];
$gradient = $categoryGradients[$sample6['category']] ?? $categoryGradients['General'];

echo "<h3>Sample 6 Data</h3>";
echo "<pre>";
echo "Name: " . htmlspecialchars($sample6['name']) . "\n";
echo "Category: " . htmlspecialchars($sample6['category']) . "\n";
echo "Cover Image: " . htmlspecialchars($sample6['cover_image'] ?? 'NULL') . "\n";
echo "Empty Check: " . var_export(empty($sample6['cover_image']), true) . "\n";
echo "Gradient: " . htmlspecialchars($gradient) . "\n";
echo "</pre>";

echo "<h3>Generated HTML</h3>";
echo "<div style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; font-family: monospace; white-space: pre-wrap;'>";

// Generate the exact HTML that would be produced
ob_start();
?>
<div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border border-gray-100">
  <!-- Program Image -->
  <?php if (!empty($sample6['cover_image'])): ?>
    <!-- Use cover image -->
    <div class="h-48 relative rounded-t-lg overflow-hidden">
      <img src="../../serve_image.php?file=<?= htmlspecialchars(basename($sample6['cover_image'])) ?>" 
           alt="<?= htmlspecialchars($sample6['name']) ?> cover" 
           class="w-full h-full object-cover"
           onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
      <!-- Fallback gradient background (hidden by default) -->
      <div class="h-48 bg-gradient-to-br <?= $gradient ?> relative rounded-t-lg" style="display: none;">
        <div class="absolute inset-0 bg-black bg-opacity-10 rounded-t-lg"></div>
        <div class="absolute inset-0 flex items-center justify-center">
          <?= icon('book-open', '3xl text-white opacity-80') ?>
        </div>
      </div>
      <div class="absolute inset-0 bg-black bg-opacity-20 rounded-t-lg"></div>
      <div class="absolute top-4 left-4">
        <span class="inline-block <?= $status_badge['bg'] ?> <?= $status_badge['text'] ?> text-xs px-3 py-1 rounded-full font-medium shadow-sm">
          <?= htmlspecialchars($status_badge['label']) ?>
        </span>
      </div>
    </div>
  <?php else: ?>
    <!-- Use gradient background (fallback) -->
    <div class="h-48 bg-gradient-to-br <?= $gradient ?> relative rounded-t-lg">
      <div class="absolute inset-0 bg-black bg-opacity-10 rounded-t-lg"></div>
      <div class="absolute top-4 left-4">
        <span class="inline-block <?= $status_badge['bg'] ?> <?= $status_badge['text'] ?> text-xs px-3 py-1 rounded-full font-medium shadow-sm">
          <?= htmlspecialchars($status_badge['label']) ?>
        </span>
      </div>
      <!-- Book Icon -->
      <div class="absolute inset-0 flex items-center justify-center">
        <?= icon('book-open', '3xl text-white opacity-80') ?>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php
$html = ob_get_clean();
echo htmlspecialchars($html);
echo "</div>";

echo "<h3>Rendered Result</h3>";
echo "<div style='border: 2px solid #007bff; padding: 20px; background: white;'>";
echo $html;
echo "</div>";

echo "<h3>Direct Image Test</h3>";
if (!empty($sample6['cover_image'])) {
    $fileName = basename($sample6['cover_image']);
    $imageUrl = "../../serve_image.php?file=" . htmlspecialchars($fileName);
    
    echo "<p><strong>Image URL:</strong> $imageUrl</p>";
    echo "<img src='$imageUrl' alt='Direct test' style='max-width: 200px; border: 2px solid blue;' ";
    echo "onload=\"this.style.border='2px solid green';\" ";
    echo "onerror=\"this.style.border='2px solid red';\">";
}
?>