/**
 * Dashboard JavaScript for Vet Anywhere
 */

document.addEventListener('DOMContentLoaded', function() {
    // Current date and time for display
    const currentDate = new Date('2025-04-05T07:42:04Z');
    const currentUser = 'MobShiro';
    
    console.log(`Dashboard initialized at ${currentDate.toLocaleString()} for user ${currentUser}`);
    
    // Toggle sidebar on mobile
    setupMobileSidebar();
    
    // Setup user dropdown menu
    setupUserMenu();
    
    // Setup notifications dropdown
    setupNotifications();
    
    // Setup pet profile tabs
    setupProfileTabs();
    
    // Setup chart data if charts exist
    setupCharts();
    
    // Setup date pickers
    setupDatePickers();
    
    // Setup reminder completion
    setupReminderCompletion();
});

/**
 * Setup mobile sidebar toggle
 */
function setupMobileSidebar() {
    const toggleBtn = document.querySelector('.mobile-sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (toggleBtn && sidebar && mainContent) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            document.body.classList.toggle('sidebar-open');
        });
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('show') && 
                !sidebar.contains(e.target) && 
                !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('show');
                document.body.classList.remove('sidebar-open');
            }
        });
    }
}

/**
 * Setup user dropdown menu
 */
function setupUserMenu() {
    const userButton = document.querySelector('.user-button');
    const dropdownContent = document.querySelector('.user-menu .dropdown-content');
    
    if (userButton && dropdownContent) {
        userButton.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            dropdownContent.style.display = 'none';
        });
    }
}

/**
 * Setup notifications dropdown
 */
function setupNotifications() {
    const notificationBtn = document.querySelector('.notifications .icon-button');
    const notificationsDropdown = document.querySelector('.notifications-dropdown');
    
    if (notificationBtn && notificationsDropdown) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationsDropdown.style.display = notificationsDropdown.style.display === 'block' ? 'none' : 'block';
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            notificationsDropdown.style.display = 'none';
        });
    }
}

/**
 * Setup pet profile tabs
 */
function setupProfileTabs() {
    const tabLinks = document.querySelectorAll('.tab-links .tab-link');
    const tabContents = document.querySelectorAll('.tab-content');
    
    if (tabLinks.length > 0 && tabContents.length > 0) {
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Deactivate all tabs
                tabLinks.forEach(item => item.classList.remove('active'));
                tabContents.forEach(item => item.classList.remove('active'));
                
                // Activate the clicked tab
                this.classList.add('active');
                
                const tabId = this.getAttribute('data-tab');
                const activeTab = document.getElementById(tabId);
                if (activeTab) {
                    activeTab.classList.add('active');
                }
            });
        });
    }
}

/**
 * Setup charts if Chart.js is loaded
 */
function setupCharts() {
    if (typeof Chart !== 'undefined') {
        // Weight tracking chart
        const weightCtx = document.getElementById('weightChart');
        if (weightCtx) {
            const weightData = JSON.parse(weightCtx.getAttribute('data-values') || '[]');
            const weightLabels = JSON.parse(weightCtx.getAttribute('data-labels') || '[]');
            
            new Chart(weightCtx, {
                type: 'line',
                data: {
                    labels: weightLabels,
                    datasets: [{
                        label: 'Weight (kg)',
                        data: weightData,
                        backgroundColor: 'rgba(74, 144, 226, 0.2)',
                        borderColor: 'rgba(74, 144, 226, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(74, 144, 226, 1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false
                        }
                    }
                }
            });
        }
        
        // Appointment statistics chart
        const appointmentCtx = document.getElementById('appointmentChart');
        if (appointmentCtx) {
            const appointmentData = JSON.parse(appointmentCtx.getAttribute('data-values') || '[]');
            const appointmentLabels = JSON.parse(appointmentCtx.getAttribute('data-labels') || '[]');
            
            new Chart(appointmentCtx, {
                type: 'bar',
                data: {
                    labels: appointmentLabels,
                    datasets: [{
                        label: 'Appointments',
                        data: appointmentData,
                        backgroundColor: [
                            'rgba(74, 144, 226, 0.7)',
                            'rgba(106, 192, 69, 0.7)',
                            'rgba(243, 156, 18, 0.7)',
                            'rgba(231, 76, 60, 0.7)'
                        ],
                        borderColor: [
                            'rgba(74, 144, 226, 1)',
                            'rgba(106, 192, 69, 1)',
                            'rgba(243, 156, 18, 1)',
                            'rgba(231, 76, 60, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    }
}

/**
 * Setup date pickers with constraints
 */
function setupDatePickers() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    
    dateInputs.forEach(input => {
        // Set max date to today for birth dates
        if (input.id.includes('birth') || input.id.includes('dob')) {
            const today = new Date().toISOString().split('T')[0];
            input.setAttribute('max', today);
        }
        
        // Set min date to today for appointment dates
        if (input.id.includes('appointment') && !input.hasAttribute('min')) {
            const today = new Date().toISOString().split('T')[0];
            input.setAttribute('min', today);
        }
    });
}

/**
 * Setup reminder completion functionality
 */
function setupReminderCompletion() {
    const completeButtons = document.querySelectorAll('.mark-complete');
    
    completeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const reminderId = this.getAttribute('data-id');
            
            // This would typically be an AJAX call to update the reminder status
            console.log(`Marking reminder ${reminderId} as complete`);
            
            // Visual feedback
            const reminderCard = this.closest('.reminder-card');
            if (reminderCard) {
                reminderCard.style.opacity = '0.5';
                reminderCard.style.pointerEvents = 'none';
                
                // Show success message
                const message = document.createElement('div');
                message.className = 'alert alert-success';
                message.textContent = 'Reminder marked as complete';
                reminderCard.parentNode.insertBefore(message, reminderCard);
                
                // Remove message after 3 seconds
                setTimeout(() => {
                    message.remove();
                    reminderCard.remove();
                }, 3000);
            }
        });
    });
}