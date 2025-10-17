<?php
/**
 * Common Scripts Include for TPLearn System
 * Include this file in pages that need SweetAlert2 and other common JavaScript libraries
 */
?>

<!-- SweetAlert2 for beautiful alerts and notifications -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// TPLearn SweetAlert2 Helper Functions (Global)
window.TPAlert = {
  success: (title, text = '') => {
    return Swal.fire({
      title: title,
      text: text,
      icon: 'success',
      confirmButtonText: 'Great!',
      confirmButtonColor: '#10b981',
      timer: 3000,
      timerProgressBar: true,
      toast: false,
      position: 'center'
    });
  },
  
  error: (title, text = '') => {
    return Swal.fire({
      title: title,
      text: text,
      icon: 'error',
      confirmButtonText: 'OK',
      confirmButtonColor: '#ef4444'
    });
  },
  
  warning: (title, text = '') => {
    return Swal.fire({
      title: title,
      text: text,
      icon: 'warning',
      confirmButtonText: 'OK',
      confirmButtonColor: '#f59e0b'
    });
  },
  
  info: (title, text = '') => {
    return Swal.fire({
      title: title,
      text: text,
      icon: 'info',
      confirmButtonText: 'OK',
      confirmButtonColor: '#3b82f6'
    });
  },
  
  confirm: (title, text = '', confirmText = 'Yes', cancelText = 'Cancel') => {
    return Swal.fire({
      title: title,
      text: text,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: confirmText,
      cancelButtonText: cancelText,
      confirmButtonColor: '#10b981',
      cancelButtonColor: '#6b7280',
      reverseButtons: true
    });
  },
  
  loading: (title = 'Processing...', text = 'Please wait') => {
    return Swal.fire({
      title: title,
      text: text,
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });
  },
  
  toast: (message, type = 'success') => {
    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
      didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
      }
    });
    
    return Toast.fire({
      icon: type,
      title: message
    });
  },
  
  close: () => {
    Swal.close();
  },
  
  // Specific TPLearn functions
  deleteConfirm: (itemName = 'this item') => {
    return Swal.fire({
      title: 'Are you sure?',
      text: `You won't be able to recover ${itemName} after deletion!`,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      reverseButtons: true
    });
  },
  
  saveSuccess: (itemName = 'Item') => {
    return TPAlert.success(
      `${itemName} Saved!`,
      `Your ${itemName.toLowerCase()} has been saved successfully.`
    );
  },
  
  networkError: () => {
    return TPAlert.error(
      'Network Error',
      'There was a problem connecting to the server. Please check your internet connection and try again.'
    );
  }
};

// Custom TPLearn themed SweetAlert2 styles
const style = document.createElement('style');
style.textContent = `
  .swal2-popup {
    border-radius: 12px;
    font-family: system-ui, -apple-system, sans-serif;
  }
  .swal2-title {
    color: #1f2937;
    font-weight: 600;
  }
  .swal2-content {
    color: #4b5563;
  }
  .swal2-confirm {
    border-radius: 8px;
    font-weight: 500;
    padding: 0.5rem 1.5rem;
  }
  .swal2-cancel {
    border-radius: 8px;
    font-weight: 500;
    padding: 0.5rem 1.5rem;
  }
  .swal2-toast {
    border-radius: 8px;
  }
`;
document.head.appendChild(style);
</script>