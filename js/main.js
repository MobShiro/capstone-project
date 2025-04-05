// Main JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', validateForm);
    });

    // Dynamic date pickers
    const datePickers = document.querySelectorAll('.date-picker');
    datePickers.forEach(input => {
        input.addEventListener('input', validateDate);
    });

    // Appointment scheduling
    const appointmentForm = document.getElementById('appointment-form');
    if (appointmentForm) {
        appointmentForm.addEventListener('submit', handleAppointmentSubmission);
    }

    // Real-time search functionality
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearch);
    }
});

// Form validation function
function validateForm(e) {
    let isValid = true;
    const requiredFields = e.target.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            showError(field, 'This field is required');
        } else {
            clearError(field);
        }
    });

    if (!isValid) {
        e.preventDefault();
    }
}

// Date validation
function validateDate(e) {
    const input = e.target;
    const selectedDate = new Date(input.value);
    const today = new Date();

    if (selectedDate < today) {
        showError(input, 'Please select a future date');
    } else {
        clearError(input);
    }
}

// Handle appointment submission
function handleAppointmentSubmission(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    fetch('appointments/process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('Appointment scheduled successfully!');
            e.target.reset();
        } else {
            showError(null, data.message);
        }
    })
    .catch(error => {
        showError(null, 'An error occurred. Please try again.');
    });
}

// Real-time search functionality
function handleSearch(e) {
    const searchTerm = e.target.value.trim();
    
    if (searchTerm.length < 2) return;

    fetch(`search.php?term=${encodeURIComponent(searchTerm)}`)
        .then(response => response.json())
        .then(data => {
            updateSearchResults(data);
        })
        .catch(error => {
            console.error('Search error:', error);
        });
}

// Show error message
function showError(element, message) {
    if (element) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        element.parentNode.appendChild(errorDiv);
        element.classList.add('error');
    } else {
        // Show global error message
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-error';
        alertDiv.textContent = message;
        document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.container').firstChild);
    }
}

// Clear error message
function clearError(element) {
    const errorDiv = element.parentNode.querySelector('.error-message');
    if (errorDiv) {
        errorDiv.remove();
    }
    element.classList.remove('error');
}

// Show success message
function showSuccess(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success';
    alertDiv.textContent = message;
    document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.container').firstChild);
    
    // Remove success message after 3 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}

// Update search results
function updateSearchResults(data) {
    const resultsContainer = document.getElementById('search-results');
    if (!resultsContainer) return;

    resultsContainer.innerHTML = '';
    
    if (data.length === 0) {
        resultsContainer.innerHTML = '<p>No results found</p>';
        return;
    }

    const ul = document.createElement('ul');
    data.forEach(item => {
        const li = document.createElement('li');
        li.textContent = item.name;
        li.addEventListener('click', () => selectSearchResult(item));
        ul.appendChild(li);
    });

    resultsContainer.appendChild(ul);
}

// Handle search result selection
function selectSearchResult(item) {
    // Navigate to the selected item's detail page
    window.location.href = `details.php?id=${item.id}`;
}