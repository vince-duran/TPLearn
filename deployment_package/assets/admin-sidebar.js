// Admin Sidebar Mobile Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('mobile-menu-overlay');
  const menuButton = document.getElementById('mobile-menu-button');
  const closeButton = document.getElementById('mobile-close-button');

  // Function to open sidebar
  function openSidebar() {
    if (sidebar && overlay) {
      sidebar.classList.remove('-translate-x-full');
      overlay.classList.remove('hidden');
      document.body.classList.add('overflow-hidden');
    }
  }

  // Function to close sidebar
  function closeSidebar() {
    if (sidebar && overlay) {
      sidebar.classList.add('-translate-x-full');
      overlay.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');
    }
  }

  // Event listeners
  if (menuButton) {
    menuButton.addEventListener('click', openSidebar);
  }

  if (closeButton) {
    closeButton.addEventListener('click', closeSidebar);
  }

  if (overlay) {
    overlay.addEventListener('click', closeSidebar);
  }

  // Close sidebar when clicking on a navigation link (mobile only)
  if (sidebar) {
    const navLinks = sidebar.querySelectorAll('a');
    navLinks.forEach(link => {
      link.addEventListener('click', () => {
        if (window.innerWidth < 1024) { // Only on mobile
          setTimeout(closeSidebar, 100); // Small delay for better UX
        }
      });
    });
  }

  // Close sidebar on escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeSidebar();
    }
  });

  // Close sidebar on window resize to desktop size
  window.addEventListener('resize', function() {
    if (window.innerWidth >= 1024) {
      closeSidebar();
    }
  });
});