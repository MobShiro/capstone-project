/**
 * Main JavaScript file for Vet Anywhere public-facing pages
 */

document.addEventListener('DOMContentLoaded', function() {
    // Current date and time for display
    const currentDate = new Date('2025-04-05T07:42:04Z');
    const currentUser = 'MobShiro';
    
    // Modal functionality
    setupModals();
    
    // Mobile menu toggle
    setupMobileMenu();
    
    // Testimonial slider
    setupTestimonialSlider();
    
    // File upload preview
    setupFileUploads();
    
    // Form validation
    setupFormValidation();
    
    console.log(`System initialized at ${currentDate.toLocaleString()} for user ${currentUser}`);
});

/**
 * Setup modal functionality
 */
function setupModals() {
    // Get all modal triggers
    const modalTriggers = document.querySelectorAll('.modal-trigger');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get the target modal
            const modalId = this.getAttribute('href').substring(1);
            const modal = document.getElementById(modalId);
            
            if (modal) {
                // Show the modal
                modal.style.display = 'block';
                
                // Close button functionality
                const closeBtn = modal.querySelector('.close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        modal.style.display = 'none';
                    });
                }
                
                // Close when clicking outside of modal content
                window.addEventListener('click', function(event) {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
            }
        });
    });
}

/**
 * Setup mobile menu toggle
 */
function setupMobileMenu() {
    const mobileMenuBtn = document.querySelector('.mobile-menu');
    const nav = document.querySelector('nav');
    
    if (mobileMenuBtn && nav) {
        mobileMenuBtn.addEventListener('click', function() {
            nav.classList.toggle('active');
        });
    }
}

/**
 * Setup testimonial slider
 */
function setupTestimonialSlider() {
    const slider = document.querySelector('.testimonial-slider');
    const testimonials = document.querySelectorAll('.testimonial');
    const dots = document.querySelectorAll('.dot');
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    
    if (!slider || testimonials.length === 0) return;
    
    let currentIndex = 0;
    
    // Show only the current testimonial
    function showTestimonial(index) {
        testimonials.forEach((testimonial, i) => {
            testimonial.style.display = i === index ? 'block' : 'none';
        });
        
        // Update active dot
        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === index);
        });
    }
    
    // Initial display
    showTestimonial(currentIndex);
    
    // Previous button
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            currentIndex = (currentIndex - 1 + testimonials.length) % testimonials.length;
            showTestimonial(currentIndex);
        });
    }
    
    // Next button
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            currentIndex = (currentIndex + 1) % testimonials.length;
            showTestimonial(currentIndex);
        });
    }
    
    // Dot navigation
    dots.forEach((dot, i) => {
        dot.addEventListener('click', function() {
            currentIndex = i;
            showTestimonial(currentIndex);
        });
    });
    
    // Auto-advance every 5 seconds
    setInterval(function() {
        currentIndex = (currentIndex + 1) % testimonials.length;
        showTestimonial(currentIndex);
    }, 5000);
}

/**
 * Setup file upload previews
 */
function setupFileUploads() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const fileNameElement = this.nextElementSibling.nextElementSibling;
            const fileName = this.files[0]?.name || 'No file chosen';
            
            if (fileNameElement) {
                fileNameElement.textContent = fileName;
            }
            
            // Image preview if available
            const previewElement = document.getElementById('imagePreview');
            if (previewElement && this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewElement.innerHTML = '';
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    previewElement.appendChild(img);
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
}

/**
 * Setup form validation
 */
function setupFormValidation() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Check required fields
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    
                    // Create error message if it doesn't exist
                    let errorMsg = field.parentNode.querySelector('.error-text');
                    if (!errorMsg) {
                        errorMsg = document.createElement('span');
                        errorMsg.className = 'error-text';
                        errorMsg.textContent = 'This field is required';
                        field.parentNode.appendChild(errorMsg);
                    }
                } else {
                    field.classList.remove('error');
                    const errorMsg = field.parentNode.querySelector('.error-text');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
            });
            
            // Check email format
            const emailFields = form.querySelectorAll('input[type="email"]');
            emailFields.forEach(field => {
                if (field.value.trim() && !isValidEmail(field.value)) {
                    isValid = false;
                    field.classList.add('error');
                    
                    // Create error message if it doesn't exist
                    let errorMsg = field.parentNode.querySelector('.error-text');
                    if (!errorMsg) {
                        errorMsg = document.createElement('span');
                        errorMsg.className = 'error-text';
                        errorMsg.textContent = 'Please enter a valid email address';
                        field.parentNode.appendChild(errorMsg);
                    }
                }
            });
            
            // Check password matching
            const passwordField = form.querySelector('input[name="password"]');
            const confirmPasswordField = form.querySelector('input[name="confirm_password"]');
            
            if (passwordField && confirmPasswordField) {
                if (passwordField.value !== confirmPasswordField.value) {
                    isValid = false;
                    confirmPasswordField.classList.add('error');
                    
                    // Create error message if it doesn't exist
                    let errorMsg = confirmPasswordField.parentNode.querySelector('.error-text');
                    if (!errorMsg) {
                        errorMsg = document.createElement('span');
                        errorMsg.className = 'error-text';
                        errorMsg.textContent = 'Passwords do not match';
                        confirmPasswordField.parentNode.appendChild(errorMsg);
                    }
                }
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Validate email format
 */
function isValidEmail(email) {
    const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}