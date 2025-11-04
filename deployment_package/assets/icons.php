<?php

/**
 * Font Awesome Icon Library for TPLearn
 * Using Font Awesome v6.4.0 - https://fontawesome.com/
 * Provides unified icon system with standardized sizes and colors
 * Last updated: 2025-10-15
 */

// Standardized icon size presets (Font Awesome classes)
define('ICON_SIZES', [
  'xs' => 'fa-xs',        // Extra small
  'sm' => 'fa-sm',        // Small
  'md' => '',             // Default size (no class needed)
  'lg' => 'fa-lg',        // Large
  'xl' => 'fa-xl',        // Extra large
  '2xl' => 'fa-2xl',      // 2x large
  '3xl' => 'fa-2xl',      // 3x large (same as 2xl for Font Awesome)
]);

// Standardized color schemes by context
define('ICON_COLORS', [
  // Status colors
  'success' => 'text-green-600',
  'warning' => 'text-yellow-600', 
  'error' => 'text-red-600',
  'info' => 'text-blue-600',
  
  // UI element colors
  'primary' => 'text-blue-600',
  'secondary' => 'text-gray-600',
  'muted' => 'text-gray-400',
  'accent' => 'text-purple-600',
  
  // Dashboard specific
  'admin' => 'text-indigo-600',
  'tutor' => 'text-emerald-600',
  'student' => 'text-cyan-600',
  
  // Navigation
  'nav-active' => 'text-blue-600',
  'nav-inactive' => 'text-gray-500',
]);

// Font Awesome icon name mappings (Heroicons -> Font Awesome equivalents)
define('ICON_MAP', [
  // Authentication & User Icons
  'user' => 'fa-user',
  'user-circle' => 'fa-user-circle',
  'users' => 'fa-users',
  'user-group' => 'fa-users',
  'student_id' => 'fa-id-card',
  'lock' => 'fa-lock',
  'eye' => 'fa-eye',
  'eye-slash' => 'fa-eye-slash',
  
  // Navigation & Dashboard Icons
  'home' => 'fa-home',
  'academic-cap' => 'fa-graduation-cap',
  'book-open' => 'fa-book-open',
  'clipboard-document-list' => 'fa-clipboard-list',
  'chart-bar' => 'fa-chart-bar',
  'cog-6-tooth' => 'fa-cog',
  'currency-dollar' => 'fa-dollar-sign',
  
  // Action Icons
  'plus' => 'fa-plus',
  'pencil' => 'fa-edit',
  'trash' => 'fa-trash',
  'search' => 'fa-search',
  'filter' => 'fa-filter',
  'arrow-right' => 'fa-arrow-right',
  'arrow-right-on-rectangle' => 'fa-sign-out-alt',
  'arrow-down-tray' => 'fa-download',
  'folder-arrow-down' => 'fa-folder-open',
  'arrow-path' => 'fa-sync',
  'chevron-down' => 'fa-chevron-down',
  'chevron-left' => 'fa-chevron-left',
  'chevron-right' => 'fa-chevron-right',
  'magnifying-glass' => 'fa-search',
  'x-mark' => 'fa-times',
  'bars-3' => 'fa-bars',
  
  // Status & Notification Icons
  'check-circle' => 'fa-check-circle',
  'exclamation-triangle' => 'fa-exclamation-triangle',
  'bell' => 'fa-bell',
  'star' => 'fa-star',
  'calendar-days' => 'fa-calendar',
  'calendar' => 'fa-calendar',
  'clock' => 'fa-clock',
  
  // Communication Icons
  'envelope' => 'fa-envelope',
  'phone' => 'fa-phone',
  'chat-bubble-left-right' => 'fa-comments',
  
  // Media Icons
  'video-camera' => 'fa-video',
  'camera' => 'fa-camera',
  'microphone' => 'fa-microphone',
  'play' => 'fa-play',
  'pause' => 'fa-pause',
  'stop' => 'fa-stop',
  
  // Additional mappings
  'map-pin' => 'fa-map-marker-alt',
]);

function icon($name, $class = 'md', $type = 'solid')
{
  // Initialize variables
  $size_class = '';
  $color_class = '';
  $spacing_class = '';
  
  // Handle standardized size shortcuts
  if (array_key_exists($class, ICON_SIZES)) {
    $size_class = ICON_SIZES[$class];
  } elseif (array_key_exists($class, ICON_COLORS)) {
    $size_class = ICON_SIZES['md']; // default size
    $color_class = ICON_COLORS[$class];
  } else {
    // Parse custom classes
    $color_classes = [];
    $size_classes = [];
    $spacing_classes = [];
    
    // Split classes to separate color from size/layout
    $class_array = explode(' ', $class);
    foreach ($class_array as $single_class) {
      // Check for size shortcuts first
      if (array_key_exists($single_class, ICON_SIZES)) {
        $size_classes[] = ICON_SIZES[$single_class];
      } elseif (array_key_exists($single_class, ICON_COLORS)) {
        $color_classes[] = ICON_COLORS[$single_class];
      } elseif (strpos($single_class, 'text-') === 0) {
        $color_classes[] = $single_class;
      } elseif (strpos($single_class, 'fa-') === 0) {
        $size_classes[] = $single_class;
      } elseif (preg_match('/^(mr?|ml?|mt?|mb?|px?|py?|m|p)-/', $single_class)) {
        $spacing_classes[] = $single_class;
      } else {
        $spacing_classes[] = $single_class;
      }
    }
    
    $size_class = implode(' ', $size_classes) ?: ICON_SIZES['md'];
    $color_class = implode(' ', $color_classes);
    $spacing_class = implode(' ', $spacing_classes);
  }
  
  // Get Font Awesome icon name
  $fa_icon = ICON_MAP[$name] ?? 'fa-question-circle';
  
  // Determine Font Awesome style prefix
  $fa_prefix = $type === 'solid' ? 'fas' : ($type === 'regular' ? 'far' : ($type === 'light' ? 'fal' : 'fas'));
  
  // Build classes
  $icon_classes = [$fa_prefix, $fa_icon];
  if ($size_class) $icon_classes[] = $size_class;
  if (isset($spacing_class)) $icon_classes[] = $spacing_class;
  
  $all_classes = implode(' ', array_filter($icon_classes));
  
  // Create icon HTML
  $icon_html = "<i class=\"$all_classes\"></i>";
  
  // If we have color classes, wrap the icon in a span with those classes
  if (!empty($color_class)) {
    return "<span class=\"$color_class\">$icon_html</span>";
  }
  
  return $icon_html;
}

// Solid icon variants (Font Awesome solid style)
function iconSolid($name, $class = 'md')
{
  return icon($name, $class, 'solid');
}

// Regular icon variants (Font Awesome regular style)  
function iconRegular($name, $class = 'md')
{
  return icon($name, $class, 'regular');
}

// Helper functions for common icon patterns

/**
 * Navigation menu icons - standardized for consistent navbar/sidebar appearance
 */
function navIcon($name, $active = false)
{
  // For sidebar navigation, use white color with margin
  return icon($name, "md text-white mr-3");
}

/**
 * Action button icons - for buttons, links, and interactive elements
 */
function actionIcon($name, $size = 'sm', $color = 'secondary')
{
  return icon($name, "$size $color");
}

/**
 * Status indicator icons - for success, warning, error states
 */
function statusIcon($name, $status = 'info', $size = 'md')
{
  return icon($name, "$size $status");
}

/**
 * Dashboard stat icons - for metric cards and statistics
 */
function statIcon($name, $color = 'primary', $size = 'xl')
{
  return icon($name, "$size $color");
}

/**
 * Menu hamburger icon - standardized mobile menu toggle
 */
function menuIcon($size = 'lg')
{
  return icon('bars-3', $size);
}

/**
 * Loading/refresh icon - for loading states
 */
function loadingIcon($size = 'md')
{
  return icon('arrow-path', "$size secondary");
}

/**
 * User avatar fallback icon
 */
function userIcon($size = 'md', $color = 'muted')
{
  return icon('user', "$size $color");
}

/**
 * Get icon with utility classes (margin, padding, etc.)
 */
function iconWithSpacing($name, $size = 'md', $color = 'secondary', $spacing = 'mr-2')
{
  return icon($name, "$size $color $spacing");
}

/**
 * Contextual icons for different dashboard types
 */
function dashboardIcon($name, $context = 'admin', $size = 'lg')
{
  return icon($name, "$size $context");
}

/**
 * Get all available icon sizes
 */
function getIconSizes()
{
  return ICON_SIZES;
}

/**
 * Get all available icon colors
 */
function getIconColors()
{
  return ICON_COLORS;
}
