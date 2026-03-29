/**
 * Secure Blog - Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    popoverTriggerList.forEach(function(popoverTriggerEl) {
        new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Confirm delete actions
    const deleteForms = document.querySelectorAll('form[data-confirm]');
    deleteForms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const message = form.getAttribute('data-confirm') || 'Вы уверены?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Rating system
    initRatingSystem();

    // Search autocomplete (if search input exists)
    initSearchAutocomplete();

    // Image preview for file uploads
    initImagePreview();

    // Character counter for textareas
    initCharacterCounter();
});

/**
 * Initialize rating system for posts
 */
function initRatingSystem() {
    const ratingStars = document.querySelectorAll('.rating-star');
    
    ratingStars.forEach(function(star) {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-value');
            const postId = this.getAttribute('data-post-id');
            
            if (!postId) return;
            
            // Send rating via AJAX
            fetch('/api/post/' + postId + '/rate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Csrf-Token': getCsrfToken()
                },
                body: JSON.stringify({ rating: parseInt(rating) })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update rating display
                    updateRatingDisplay(postId, data.average);
                    showNotification('Спасибо за оценку!', 'success');
                } else {
                    showNotification(data.error || 'Ошибка при оценке', 'danger');
                }
            })
            .catch(error => {
                console.error('Rating error:', error);
                showNotification('Произошла ошибка при отправке оценки', 'danger');
            });
        });
        
        // Hover effects
        star.addEventListener('mouseenter', function() {
            const value = parseInt(this.getAttribute('data-value'));
            highlightStars(this.closest('.rating-stars'), value);
        });
        
        star.addEventListener('mouseleave', function() {
            const ratedValue = this.closest('.rating-stars').getAttribute('data-rated-value') || 0;
            highlightStars(this.closest('.rating-stars'), parseInt(ratedValue));
        });
    });
}

/**
 * Highlight stars up to given value
 */
function highlightStars(container, value) {
    const stars = container.querySelectorAll('.rating-star');
    stars.forEach(function(star, index) {
        if (index < value) {
            star.classList.remove('far');
            star.classList.add('fas');
        } else {
            star.classList.remove('fas');
            star.classList.add('far');
        }
    });
}

/**
 * Update rating display after vote
 */
function updateRatingDisplay(postId, average) {
    const ratingElement = document.querySelector('[data-post-id="' + postId + '"].rating-average');
    if (ratingElement) {
        ratingElement.textContent = parseFloat(average).toFixed(2);
    }
}

/**
 * Initialize search autocomplete
 */
function initSearchAutocomplete() {
    const searchInput = document.querySelector('input[name="q"][data-autocomplete]');
    
    if (!searchInput) return;
    
    let debounceTimer;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        
        const query = this.value.trim();
        
        if (query.length < 3) {
            hideSearchSuggestions();
            return;
        }
        
        debounceTimer = setTimeout(function() {
            fetchSearchSuggestions(query);
        }, 300);
    });
}

/**
 * Fetch search suggestions
 */
function fetchSearchSuggestions(query) {
    fetch('/api/search/suggest?q=' + encodeURIComponent(query))
        .then(response => response.json())
        .then(data => {
            showSearchSuggestions(data.suggestions || []);
        })
        .catch(error => {
            console.error('Search suggestions error:', error);
        });
}

/**
 * Show search suggestions dropdown
 */
function showSearchSuggestions(suggestions) {
    // Implementation for showing suggestions
}

/**
 * Hide search suggestions dropdown
 */
function hideSearchSuggestions() {
    // Implementation for hiding suggestions
}

/**
 * Initialize image preview for file uploads
 */
function initImagePreview() {
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    
    imageInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            const file = this.files[0];
            const previewContainer = document.getElementById(this.getAttribute('data-preview') || 'image-preview');
            
            if (!file || !previewContainer) return;
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                previewContainer.innerHTML = '<img src="' + e.target.result + '" class="img-fluid rounded" alt="Preview">';
            };
            
            reader.readAsDataURL(file);
        });
    });
}

/**
 * Initialize character counter for textareas
 */
function initCharacterCounter() {
    const textareas = document.querySelectorAll('textarea[data-min-length]');
    
    textareas.forEach(function(textarea) {
        const minLength = parseInt(textarea.getAttribute('data-min-length'));
        const counter = document.getElementById(textarea.getAttribute('data-counter') || textarea.id + '-counter');
        
        if (!counter) return;
        
        textarea.addEventListener('input', function() {
            const length = this.value.trim().length;
            counter.textContent = length + ' символов';
            
            if (length > 0 && length < minLength) {
                counter.classList.add('text-warning');
                counter.classList.remove('text-success', 'text-danger');
            } else if (length >= minLength) {
                counter.classList.add('text-success');
                counter.classList.remove('text-warning', 'text-danger');
            } else {
                counter.classList.add('text-muted');
                counter.classList.remove('text-success', 'text-warning', 'text-danger');
            }
        });
    });
}

/**
 * Get CSRF token from meta tag or form
 */
function getCsrfToken() {
    const token = document.querySelector('meta[name="csrf-token"]');
    if (token) {
        return token.getAttribute('content');
    }
    
    const input = document.querySelector('input[name="csrf_token"]');
    if (input) {
        return input.value;
    }
    
    return '';
}

/**
 * Show notification toast
 */
function showNotification(message, type) {
    const container = document.getElementById('notifications-container') || createNotificationsContainer();
    
    const alert = document.createElement('div');
    alert.className = 'alert alert-' + type + ' alert-dismissible fade show';
    alert.role = 'alert';
    alert.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    
    container.appendChild(alert);
    
    setTimeout(function() {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    }, 5000);
}

/**
 * Create notifications container if not exists
 */
function createNotificationsContainer() {
    const container = document.createElement('div');
    container.id = 'notifications-container';
    container.style.position = 'fixed';
    container.style.top = '20px';
    container.style.right = '20px';
    container.style.zIndex = '9999';
    container.style.maxWidth = '400px';
    
    document.body.appendChild(container);
    
    return container;
}
