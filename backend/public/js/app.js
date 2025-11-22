// CoinHit - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initForms();
    initModals();
    initTooltips();
    initCouponBuilder();
});

// Form handling
function initForms() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Add loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> İşleniyor...';
            }
        });
    });

    // Password confirmation
    const passwordConfirm = document.getElementById('password_confirm');
    if (passwordConfirm) {
        passwordConfirm.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            if (this.value !== password) {
                this.setCustomValidity('Şifreler eşleşmiyor');
            } else {
                this.setCustomValidity('');
            }
        });
    }
}

// Modal handling
function initModals() {
    const modalTriggers = document.querySelectorAll('[data-modal]');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.getAttribute('data-modal');
            openModal(modalId);
        });
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

// Tooltips
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const text = this.getAttribute('data-tooltip');
            showTooltip(this, text);
        });
        
        element.addEventListener('mouseleave', function() {
            hideTooltip();
        });
    });
}

function showTooltip(element, text) {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    tooltip.id = 'active-tooltip';
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.top = (rect.top - tooltip.offsetHeight - 5) + 'px';
    tooltip.style.left = (rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)) + 'px';
}

function hideTooltip() {
    const tooltip = document.getElementById('active-tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

// Coupon Builder
function initCouponBuilder() {
    const couponBuilder = document.getElementById('coupon-builder');
    if (!couponBuilder) return;

    const picks = [];
    
    window.addToCoupon = function(matchId, predictionType, odd) {
        const pick = { matchId, predictionType, odd };
        picks.push(pick);
        updateCouponDisplay();
        calculateTotalOdds();
    };
    
    window.removeFromCoupon = function(index) {
        picks.splice(index, 1);
        updateCouponDisplay();
        calculateTotalOdds();
    };
    
    function updateCouponDisplay() {
        const picksContainer = document.getElementById('coupon-picks');
        if (!picksContainer) return;
        
        picksContainer.innerHTML = picks.map((pick, index) => `
            <div class="coupon-pick-item">
                <span>${pick.predictionType}</span>
                <span>${pick.odd}</span>
                <button onclick="removeFromCoupon(${index})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');
    }
    
    function calculateTotalOdds() {
        const total = picks.reduce((acc, pick) => acc * parseFloat(pick.odd), 1);
        const totalElement = document.getElementById('total-odds');
        if (totalElement) {
            totalElement.textContent = total.toFixed(2);
        }
        
        const stakeInput = document.getElementById('stake-amount');
        if (stakeInput) {
            const stake = parseFloat(stakeInput.value) || 0;
            const potentialWin = total * stake;
            const potentialElement = document.getElementById('potential-win');
            if (potentialElement) {
                potentialElement.textContent = potentialWin.toFixed(2);
            }
        }
    }
    
    const stakeInput = document.getElementById('stake-amount');
    if (stakeInput) {
        stakeInput.addEventListener('input', calculateTotalOdds);
    }
}

// API Helper
async function apiRequest(url, method = 'GET', data = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
        }
    };
    
    // Get JWT token from localStorage
    const token = localStorage.getItem('jwt_token');
    if (token) {
        options.headers['Authorization'] = 'Bearer ' + token;
    }
    
    if (data) {
        options.body = JSON.stringify(data);
    }
    
    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Request failed');
        }
        
        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

// Notification helper
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Auto-hide alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
    });
}, 5000);

// Export functions for global use
window.apiRequest = apiRequest;
window.showNotification = showNotification;
window.openModal = openModal;
window.closeModal = closeModal;
