// LaraCity Dashboard JavaScript Enhancements

document.addEventListener('DOMContentLoaded', function() {
    // Initialize dashboard components
    initializeDashboard();
});

function initializeDashboard() {
    // Auto-scroll chat to bottom when new messages arrive
    observeChatMessages();
    
    // Add keyboard shortcuts for chat
    addChatKeyboardShortcuts();
    
    // Initialize tooltips for accessibility
    initializeTooltips();
}

function observeChatMessages() {
    // Create a MutationObserver to watch for new chat messages
    const chatContainer = document.querySelector('[data-chat-container]');
    if (chatContainer) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // Smooth scroll to bottom when new messages are added
                    chatContainer.scrollTo({
                        top: chatContainer.scrollHeight,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        observer.observe(chatContainer, {
            childList: true,
            subtree: true
        });
    }
}

function addChatKeyboardShortcuts() {
    // Add keyboard shortcut for sending messages (Ctrl/Cmd + Enter)
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const chatInput = document.querySelector('input[wire\\:model\\.defer="userMessage"]');
            const sendButton = document.querySelector('button[type="submit"]');
            
            if (chatInput && sendButton && document.activeElement === chatInput) {
                e.preventDefault();
                sendButton.click();
            }
        }
    });
}

function initializeTooltips() {
    // Add tooltips for icon buttons
    const iconButtons = document.querySelectorAll('[data-tooltip]');
    
    iconButtons.forEach(button => {
        const tooltip = button.getAttribute('data-tooltip');
        button.setAttribute('title', tooltip);
        button.setAttribute('aria-label', tooltip);
    });
}

// Utility functions for enhanced user experience
window.dashboardUtils = {
    // Copy complaint number to clipboard
    copyComplaintNumber: function(complaintNumber) {
        navigator.clipboard.writeText(complaintNumber).then(function() {
            // Show temporary success message
            showNotification('Complaint number copied to clipboard', 'success');
        }).catch(function() {
            showNotification('Failed to copy complaint number', 'error');
        });
    },
    
    // Show notification (you can integrate with your notification system)
    showNotification: function(message, type = 'info') {
        // This is a simple implementation - you can enhance with your preferred notification library
        const notification = document.createElement('div');
        notification.className = `fixed bottom-4 left-4 p-4 rounded-lg shadow-lg z-50 ${
            type === 'success' ? 'bg-green-500 text-white' :
            type === 'error' ? 'bg-red-500 text-white' :
            'bg-blue-500 text-white'
        }`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            notification.remove();
        }, 3000);
    },
    
    // Format risk score for display
    formatRiskScore: function(score) {
        if (score >= 0.7) return { level: 'High', color: 'red' };
        if (score >= 0.4) return { level: 'Medium', color: 'yellow' };
        return { level: 'Low', color: 'green' };
    }
};

// Expose function globally for Livewire interactions
window.showNotification = window.dashboardUtils.showNotification;