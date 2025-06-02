// Admin Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
});

function initializeDashboard() {
    // Add loading animations
    addLoadingAnimations();
    
    // Initialize project cards
    initializeProjectCards();
    
    // Add smooth scrolling
    addSmoothScrolling();
    
    // Initialize tooltips
    initializeTooltips();
    
    // Add keyboard navigation
    addKeyboardNavigation();
    
    // Initialize responsive features
    initializeResponsiveFeatures();
    
    // Add performance monitoring
    monitorPerformance();
}

// Loading Animations
function addLoadingAnimations() {
    const cards = document.querySelectorAll('.project-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.classList.add('fade-in');
    });
    
    // Add stagger animation to sections
    const sections = document.querySelectorAll('.project-section');
    sections.forEach((section, index) => {
        section.style.animationDelay = `${index * 0.2}s`;
    });
}

// Project Cards Functionality
function initializeProjectCards() {
    const projectCards = document.querySelectorAll('.project-card');
    
    projectCards.forEach(card => {
        // Add hover sound effect (optional)
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
        
        // Add click animation
        card.addEventListener('mousedown', function() {
            this.style.transform = 'translateY(-3px) scale(0.98)';
        });
        
        card.addEventListener('mouseup', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        // Add focus for accessibility
        card.setAttribute('tabindex', '0');
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });
}

// View Project Function
function viewProject(project_id) {
    // Add loading state
    showLoadingOverlay();
    
    // Simulate navigation with smooth transition
    setTimeout(() => {
        // You can customize this URL based on your project structure
        window.location.href = `admin_project_details.php?id=${project_id}`;
    }, 300);
}

// Loading Overlay
function showLoadingOverlay() {
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.innerHTML = `
        <div class="loading-spinner">
            <div class="spinner"></div>
            <p>Loading project details...</p>
        </div>
    `;
    
    document.body.appendChild(overlay);
    
    // Style the overlay
    const style = document.createElement('style');
    style.textContent = `
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease-out;
        }
        
        .loading-spinner {
            text-align: center;
            color: white;
        }
        
        .loading-spinner p {
            margin-top: 1rem;
            font-weight: 500;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    `;
    document.head.appendChild(style);
}

// Smooth Scrolling
function addSmoothScrolling() {
    const containers = document.querySelectorAll('.projects-container');
    
    containers.forEach(container => {
        let isScrolling = false;
        
        container.addEventListener('scroll', function() {
            if (!isScrolling) {
                window.requestAnimationFrame(function() {
                    // Add scroll shadow effect
                    if (container.scrollTop > 10) {
                        container.style.boxShadow = 'inset 0 10px 10px -10px rgba(0,0,0,0.1)';
                    } else {
                        container.style.boxShadow = 'none';
                    }
                    isScrolling = false;
                });
                isScrolling = true;
            }
        });
    });
}

// Tooltips
function initializeTooltips() {
    const elements = document.querySelectorAll('[data-tooltip]');
    
    elements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = e.target.getAttribute('data-tooltip');
    
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
    
    // Add tooltip styles
    if (!document.querySelector('#tooltip-styles')) {
        const style = document.createElement('style');
        style.id = 'tooltip-styles';
        style.textContent = `
            .tooltip {
                position: absolute;
                background: var(--dark);
                color: white;
                padding: 0.5rem 0.75rem;
                border-radius: 6px;
                font-size: 0.8rem;
                z-index: 1000;
                opacity: 0;
                animation: tooltipFadeIn 0.2s ease-out forwards;
                pointer-events: none;
            }
            
            .tooltip::after {
                content: '';
                position: absolute;
                top: 100%;
                left: 50%;
                transform: translateX(-50%);
                border: 5px solid transparent;
                border-top-color: var(--dark);
            }
            
            @keyframes tooltipFadeIn {
                to { opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    }
}

function hideTooltip() {
    const tooltip = document.querySelector('.tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

// Keyboard Navigation
function addKeyboardNavigation() {
    document.addEventListener('keydown', function(e) {
        // Navigate between project cards with arrow keys
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            const focusedElement = document.activeElement;
            const projectCards = Array.from(document.querySelectorAll('.project-card'));
            const currentIndex = projectCards.indexOf(focusedElement);
            
            if (currentIndex !== -1) {
                e.preventDefault();
                let nextIndex;
                
                if (e.key === 'ArrowDown') {
                    nextIndex = (currentIndex + 1) % projectCards.length;
                } else {
                    nextIndex = (currentIndex - 1 + projectCards.length) % projectCards.length;
                }
                
                projectCards[nextIndex].focus();
            }
        }
        
        // Quick actions with keyboard shortcuts
        if (e.ctrlKey || e.metaKey) {
            switch(e.key) {
                case 'n':
                    e.preventDefault();
                    // Navigate to add project
                    window.location.href = 'admin_add_project.php';
                    break;
                case 'u':
                    e.preventDefault();
                    // Navigate to manage users
                    window.location.href = 'admin_manage_users.php';
                    break;
            }
        }
    });
}

// Responsive Features
function initializeResponsiveFeatures() {
    // Mobile navigation toggle
    const headerActions = document.querySelector('.header-actions');
    
    if (window.innerWidth <= 768) {
        addMobileToggle();
    }
    
    // Update on resize
    window.addEventListener('resize', debounce(function() {
        if (window.innerWidth <= 768) {
            addMobileToggle();
        } else {
            removeMobileToggle();
        }
    }, 250));
}

function addMobileToggle() {
    const headerContent = document.querySelector('.header-content');
    const headerActions = document.querySelector('.header-actions');
    
    if (!document.querySelector('.mobile-toggle')) {
        const toggle = document.createElement('button');
        toggle.className = 'mobile-toggle';
        toggle.innerHTML = '<i class="fas fa-bars"></i>';
        toggle.addEventListener('click', function() {
            headerActions.classList.toggle('mobile-open');
        });
        
        headerContent.insertBefore(toggle, headerActions);
        
        // Add mobile styles
        if (!document.querySelector('#mobile-styles')) {
            const style = document.createElement('style');
            style.id = 'mobile-styles';
            style.textContent = `
                .mobile-toggle {
                    display: none;
                    background: var(--primary);
                    color: white;
                    border: none;
                    padding: 0.75rem;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 1.2rem;
                }
                
                @media (max-width: 768px) {
                    .mobile-toggle {
                        display: block;
                    }
                    
                    .header-actions {
                        position: absolute;
                        top: 100%;
                        left: 0;
                        right: 0;
                        background: white;
                        flex-direction: column;
                        box-shadow: var(--shadow);
                        max-height: 0;
                        overflow: hidden;
                        transition: max-height 0.3s ease;
                    }
                    
                    .header-actions.mobile-open {
                        max-height: 300px;
                        padding: 1rem;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }
}

function removeMobileToggle() {
    const toggle = document.querySelector('.mobile-toggle');
    const mobileStyles = document.querySelector('#mobile-styles');
    
    if (toggle) toggle.remove();
    if (mobileStyles) mobileStyles.remove();
}

// Performance Monitoring
function monitorPerformance() {
    // Monitor page load time
    window.addEventListener('load', function() {
        const loadTime = performance.now();
        console.log(`Dashboard loaded in ${loadTime.toFixed(2)}ms`);
        
        // Add performance indicator for slow loads
        if (loadTime > 3000) {
            showPerformanceWarning();
        }
    });
    
    // Monitor scroll performance
    let scrollTimeout;
    document.addEventListener('scroll', function() {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(function() {
            // Optimize animations based on scroll position
            optimizeAnimations();
        }, 100);
    });
}

function showPerformanceWarning() {
    const warning = document.createElement('div');
    warning.className = 'performance-warning';
    warning.innerHTML = `
        <i class="fas fa-exclamation-triangle"></i>
        <span>Dashboard is loading slowly. Consider refreshing the page.</span>
        <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;
    
    document.body.appendChild(warning);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (warning.parentElement) {
            warning.remove();
        }
    }, 5000);
    
    // Add warning styles
    if (!document.querySelector('#performance-styles')) {
        const style = document.createElement('style');
        style.id = 'performance-styles';
        style.textContent = `
            .performance-warning {
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--warning);
                color: white;
                padding: 1rem;
                border-radius: 8px;
                display: flex;
                align-items: center;
                 gap: 0.5rem;
                z-index: 1001;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                animation: slideInRight 0.3s ease-out;
                max-width: 300px;
            }
            
            .performance-warning button {
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                padding: 0.25rem;
                border-radius: 4px;
                margin-left: auto;
            }
            
            .performance-warning button:hover {
                background: rgba(255,255,255,0.1);
            }
            
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// Optimize animations based on performance
function optimizeAnimations() {
    const cards = document.querySelectorAll('.project-card');
    const isVisible = (element) => {
        const rect = element.getBoundingClientRect();
        return rect.top < window.innerHeight && rect.bottom > 0;
    };
    
    cards.forEach(card => {
        if (isVisible(card)) {
            card.style.willChange = 'transform';
        } else {
            card.style.willChange = 'auto';
        }
    });
}

// Debounce utility function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Initialize spinner animation
const spinnerStyle = document.createElement('style');
spinnerStyle.textContent = `
    .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid rgba(255,255,255,0.3);
        border-top: 4px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .fade-in {
        animation: fadeInUp 0.6s ease-out forwards;
        opacity: 0;
        transform: translateY(20px);
    }
    
    @keyframes fadeInUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
document.head.appendChild(spinnerStyle);