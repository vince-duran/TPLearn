<?php
require_once __DIR__ . '/../assets/icons.php';
// Get current page for active nav highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile menu overlay -->
<div id="mobile-menu-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden"></div>

<!-- Sidebar -->
<div id="sidebar" class="w-64 bg-tplearn-green min-h-screen fixed left-0 top-0 z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
  <!-- Logo Section -->
  <div class="p-4 sm:p-6 border-b border-green-600">
    <div class="flex items-center justify-between">
      <div class="flex items-center space-x-2 sm:space-x-3">
        <img src="../../assets/logonew.png" alt="TPLearn Logo" class="w-8 h-8 object-contain rounded-lg">
        <div class="text-white min-w-0 sidebar-text">
          <h1 class="font-bold text-base sm:text-lg">TPLearn</h1>
          <p class="text-xs text-green-200 hidden sm:block">Tisa at Pisara's Academic and Tutorial Services</p>
          <p class="text-xs text-green-200 sm:hidden">Academic Services</p>
        </div>
      </div>
      <!-- Mobile close button -->
      <button id="mobile-close-button" class="lg:hidden text-white p-1 hover:bg-green-600 rounded">
        <i class="fas fa-times text-lg"></i>
      </button>
    </div>
  </div>

  <!-- Navigation Menu -->
  <nav class="mt-4 sm:mt-6">
    <a href="tutor.php" class="nav-item flex items-center px-4 sm:px-6 py-3 text-white <?= $current_page === 'tutor.php' ? 'bg-green-600 border-r-4 border-white' : 'hover:bg-green-600' ?> transition-colors" title="Home">
      <?= navIcon('home', $current_page === 'tutor.php') ?>
      <span class="text-sm sm:text-base sidebar-text ml-3">Home</span>
    </a>

    <a href="tutor-programs.php" class="nav-item flex items-center px-4 sm:px-6 py-3 text-white <?= $current_page === 'tutor-programs.php' ? 'bg-green-600 border-r-4 border-white' : 'hover:bg-green-600' ?> transition-colors" title="My Programs">
      <?= navIcon('book-open', $current_page === 'tutor-programs.php') ?>
      <span class="text-sm sm:text-base sidebar-text ml-3">My Programs</span>
    </a>

    <a href="tutor-students.php" class="nav-item flex items-center px-4 sm:px-6 py-3 text-white <?= $current_page === 'tutor-students.php' ? 'bg-green-600 border-r-4 border-white' : 'hover:bg-green-600' ?> transition-colors" title="My Students">
      <?= navIcon('users', $current_page === 'tutor-students.php') ?>
      <span class="text-sm sm:text-base sidebar-text ml-3">My Students</span>
    </a>

    <a href="tutor-profile.php" class="nav-item flex items-center px-4 sm:px-6 py-3 text-white <?= $current_page === 'tutor-profile.php' ? 'bg-green-600 border-r-4 border-white' : 'hover:bg-green-600' ?> transition-colors" title="My Profile">
      <?= navIcon('user-circle', $current_page === 'tutor-profile.php') ?>
      <span class="text-sm sm:text-base sidebar-text ml-3">My Profile</span>
    </a>
  </nav>

  <!-- Logout Button -->
  <div class="absolute bottom-6 w-full px-6">
    <a href="../../logout.php" class="nav-item flex items-center px-4 py-3 text-white bg-red-500 hover:bg-red-600 rounded-lg transition-colors" title="Logout">
      <?= icon('arrow-right-on-rectangle', 'md text-white mr-3') ?>
      <span class="text-sm sidebar-text">Logout</span>
    </a>
  </div>
</div>

<!-- Sidebar CSS -->
<style>
.bg-tplearn-green {
  background-color: #10b981 !important;
}

.text-tplearn-green {
  color: #10b981;
}
</style>