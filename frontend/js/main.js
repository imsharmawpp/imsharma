/* ==========================================================
   Vastu Home Kundali AI - Main JavaScript
   ========================================================== */

// API base path - update this to match your hosting setup
const API_BASE = '../backend/api';
const API_BASE_ROOT = 'backend/api'; // for index page

// ===== Preloader =====
window.addEventListener('load', () => {
    const preloader = document.getElementById('preloader');
    if (preloader) {
        setTimeout(() => preloader.classList.add('hidden'), 600);
    }
});

// ===== Navbar Scroll Effect =====
const navbar = document.getElementById('navbar');
if (navbar) {
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
}

// ===== Mobile Menu Toggle =====
const mobileToggle = document.getElementById('mobileToggle');
const navLinks = document.getElementById('navLinks');
if (mobileToggle && navLinks) {
    mobileToggle.addEventListener('click', () => {
        navLinks.classList.toggle('active');
    });
    // Close on link click
    navLinks.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => navLinks.classList.remove('active'));
    });
}

// ===== Smooth Scroll =====
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        const target = document.querySelector(targetId);
        if (target) {
            e.preventDefault();
            const offset = 80;
            const top = target.getBoundingClientRect().top + window.scrollY - offset;
            window.scrollTo({ top, behavior: 'smooth' });
        }
    });
});

// ===== FAQ Accordion =====
document.querySelectorAll('.faq-question').forEach(btn => {
    btn.addEventListener('click', () => {
        const item = btn.closest('.faq-item');
        const isActive = item.classList.contains('active');
        document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('active'));
        if (!isActive) item.classList.add('active');
    });
});

// ===== Scroll Reveal =====
const revealElements = document.querySelectorAll('.feature-card, .step-card, .pricing-card, .testimonial-card, .product-card, .section-header');
revealElements.forEach(el => el.classList.add('reveal'));

const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            revealObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

revealElements.forEach(el => revealObserver.observe(el));

// ===== Exit Intent Popup =====
const exitPopup = document.getElementById('exitPopup');
const popupClose = document.getElementById('popupClose');
let exitShown = false;

if (exitPopup) {
    document.addEventListener('mouseleave', (e) => {
        if (e.clientY < 0 && !exitShown && !sessionStorage.getItem('exitPopupShown')) {
            exitPopup.classList.add('active');
            exitShown = true;
            sessionStorage.setItem('exitPopupShown', 'true');
        }
    });
    if (popupClose) {
        popupClose.addEventListener('click', () => exitPopup.classList.remove('active'));
    }
    exitPopup.addEventListener('click', (e) => {
        if (e.target === exitPopup) exitPopup.classList.remove('active');
    });
}

// ===== Toast Notification =====
function showToast(message, type = 'info', duration = 4000) {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    const icon = type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
    toast.innerHTML = `<i class="fas ${icon}"></i> &nbsp; ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, duration);
}
window.showToast = showToast;

// ===== API Helper =====
async function apiCall(endpoint, method = 'GET', data = null, isFormData = false) {
    const opts = { method, headers: {} };
    const token = localStorage.getItem('vastu_token');
    if (token) opts.headers['Authorization'] = `Bearer ${token}`;

    if (data) {
        if (isFormData) {
            opts.body = data;
        } else {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(data);
        }
    }
    try {
        // Detect base path from current location
        const base = window.location.pathname.includes('/pages/') ? '../../backend/api' : 'backend/api';
        const res = await fetch(`${base}/${endpoint}`, opts);
        return await res.json();
    } catch (err) {
        console.error('API error:', err);
        return { success: false, message: 'Network error. Please try again.' };
    }
}
window.apiCall = apiCall;

// ===== Animated Counter =====
function animateCounter(el) {
    const text = el.textContent.trim();
    const match = text.match(/^(\d+)/);
    if (!match) return;
    const target = parseInt(match[1]);
    const suffix = text.substring(match[1].length);
    let current = 0;
    const duration = 2000;
    const step = target / (duration / 16);
    const timer = setInterval(() => {
        current += step;
        if (current >= target) { current = target; clearInterval(timer); }
        el.textContent = Math.floor(current) + suffix;
    }, 16);
}
const counterObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounter(entry.target);
            counterObserver.unobserve(entry.target);
        }
    });
});
document.querySelectorAll('.stat-number').forEach(el => counterObserver.observe(el));

// ===== Auth Helpers =====
window.vastuAuth = {
    isLoggedIn: () => !!localStorage.getItem('vastu_token'),
    getUser: () => {
        try { return JSON.parse(localStorage.getItem('vastu_user') || 'null'); }
        catch (e) { return null; }
    },
    login: (token, user) => {
        localStorage.setItem('vastu_token', token);
        localStorage.setItem('vastu_user', JSON.stringify(user));
    },
    logout: () => {
        localStorage.removeItem('vastu_token');
        localStorage.removeItem('vastu_user');
        window.location.href = '/';
    }
};

console.log('%c🏛️ VastuKundali AI', 'color: #D4AF37; font-size: 20px; font-weight: bold;');
console.log('%cAlign your home with cosmic energies.', 'color: #666; font-style: italic;');
