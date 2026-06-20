/* ==========================================================
   Checkout Page Logic
   ========================================================== */

const checkoutState = {
    cart: JSON.parse(localStorage.getItem('vastu_cart') || '[]'),
    contact: { name: '', email: '', phone: '' },
    verificationToken: null,
    address: null,
    paymentMethod: 'online',
    pricing: { subtotal: 0, shipping: 0, codCharge: 0, total: 0 },
    config: { codEnabled: true, codCharge: 40, codMaxAmount: 5000, freeShippingAbove: 999, shippingFlat: 50 },
    otpTimer: null
};

const API = (window.location.pathname.includes('/pages/') ? '../../backend/api' : 'backend/api');

document.addEventListener('DOMContentLoaded', async () => {
    if (!checkoutState.cart.length) {
        document.getElementById('checkoutMain').style.display = 'none';
        document.querySelector('.checkout-summary').style.display = 'none';
        document.getElementById('emptyCartState').style.display = 'block';
        return;
    }

    await loadConfig();
    renderSummary();
    setupHandlers();
    prefillFromUser();
});

async function loadConfig() {
    // We use stable defaults; backend will enforce the real values
    try {
        // Optional: read from settings via products endpoint or a config endpoint
    } catch (e) {}
}

function prefillFromUser() {
    const user = vastuAuth.getUser();
    if (user) {
        document.getElementById('contactName').value = user.name || '';
        document.getElementById('contactEmail').value = user.email || '';
        if (user.phone) document.getElementById('contactPhone').value = user.phone;
    }
}

function renderSummary() {
    const list = document.getElementById('cartItemsList');
    list.innerHTML = checkoutState.cart.map(i => `
        <div class="summary-item">
            <div class="summary-icon"><i class="fas fa-${i.icon || 'gem'}"></i></div>
            <div style="flex:1;">
                <div style="font-weight:600;font-size:14px;">${escapeHtml(i.name)}</div>
                <div style="font-size:13px;color:var(--gray-600);">Qty: ${i.qty} × ₹${i.price}</div>
            </div>
            <div style="font-weight:700;color:var(--gold-dark);">₹${i.price * i.qty}</div>
        </div>
    `).join('');

    recalc();
}

function recalc() {
    const subtotal = checkoutState.cart.reduce((s, i) => s + i.price * i.qty, 0);
    const shipping = subtotal >= checkoutState.config.freeShippingAbove ? 0 : checkoutState.config.shippingFlat;
    const codCharge = checkoutState.paymentMethod === 'cod' ? checkoutState.config.codCharge : 0;
    const total = subtotal + shipping + codCharge;

    checkoutState.pricing = { subtotal, shipping, codCharge, total };

    document.getElementById('subTotal').textContent = '₹' + subtotal;
    document.getElementById('shipCost').textContent = shipping === 0 ? 'FREE' : '₹' + shipping;
    document.getElementById('codRow').style.display = codCharge > 0 ? 'flex' : 'none';
    document.getElementById('codCost').textContent = '₹' + codCharge;
    document.getElementById('grandTotal').textContent = '₹' + total;

    // Toggle COD availability based on subtotal
    const codOption = document.getElementById('codOption');
    if (subtotal > checkoutState.config.codMaxAmount) {
        codOption.style.opacity = '0.5';
        codOption.style.pointerEvents = 'none';
        codOption.querySelector('input').disabled = true;
        document.getElementById('codSubtext').textContent = `COD not available for orders above ₹${checkoutState.config.codMaxAmount}`;
    } else {
        document.getElementById('codSubtext').textContent = `Pay ₹${total} cash on delivery (₹${checkoutState.config.codCharge} extra charge)`;
    }
}

function setupHandlers() {
    document.getElementById('contactPhone').addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '').slice(0, 10);
    });

    document.getElementById('sendOtpBtn').addEventListener('click', sendOtp);
    document.getElementById('verifyOtpBtn').addEventListener('click', verifyOtp);
    document.getElementById('saveAddressBtn').addEventListener('click', saveAddressAndContinue);
    document.getElementById('placeOrderBtn').addEventListener('click', placeOrder);

    // OTP input auto-advance
    document.querySelectorAll('[data-otp-index]').forEach((input, i, all) => {
        input.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 1);
            if (e.target.value && all[i + 1]) all[i + 1].focus();
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && all[i - 1]) all[i - 1].focus();
        });
    });

    // Payment method
    document.querySelectorAll('.payment-option').forEach(opt => {
        opt.addEventListener('click', (e) => {
            const method = opt.dataset.method;
            if (opt.querySelector('input').disabled) return;
            document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
            opt.querySelector('input').checked = true;
            checkoutState.paymentMethod = method;
            updatePlaceOrderButton();
            recalc();
        });
    });
}

function updatePlaceOrderButton() {
    const btn = document.getElementById('placeOrderBtn');
    if (checkoutState.paymentMethod === 'cod') {
        btn.innerHTML = `<i class="fas fa-money-bill-wave"></i> Place Order (Pay ₹${checkoutState.pricing.total} on Delivery)`;
    } else {
        btn.innerHTML = `<i class="fas fa-lock"></i> Pay ₹${checkoutState.pricing.total} Securely`;
    }
}

// ===== Section 1: OTP =====
async function sendOtp() {
    const name = document.getElementById('contactName').value.trim();
    const email = document.getElementById('contactEmail').value.trim();
    const phone = document.getElementById('contactPhone').value.trim();

    if (!name) return showToast('Please enter your name', 'error');
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return showToast('Please enter valid email', 'error');
    if (phone.length !== 10) return showToast('Please enter 10-digit phone number', 'error');

    checkoutState.contact = { name, email, phone };

    const btn = document.getElementById('sendOtpBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    try {
        const res = await fetch(`${API}/send_otp.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone, purpose: 'checkout' })
        });
        const data = await res.json();

        if (!data.success) throw new Error(data.message || 'Failed to send OTP');

        // Switch to OTP form
        document.getElementById('contactForm').style.display = 'none';
        document.getElementById('otpForm').style.display = 'block';
        document.getElementById('phoneDisplay').textContent = phone;

        // Show demo OTP if in demo mode
        if (data._demo_mode && data._demo_otp) {
            document.getElementById('demoOtpHint').style.display = 'block';
            document.getElementById('demoOtpValue').textContent = data._demo_otp;
        }

        document.querySelector('[data-otp-index="0"]').focus();
        startOtpTimer(data.expires_in || 600);
        showToast('OTP sent to your WhatsApp!', 'success');

    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fab fa-whatsapp"></i> Send OTP via WhatsApp';
    }
}

window.changePhone = function() {
    document.getElementById('otpForm').style.display = 'none';
    document.getElementById('contactForm').style.display = 'block';
    if (checkoutState.otpTimer) clearInterval(checkoutState.otpTimer);
};

window.resendOtp = sendOtp;

function startOtpTimer(seconds) {
    if (checkoutState.otpTimer) clearInterval(checkoutState.otpTimer);
    let remaining = seconds;
    const timer = document.getElementById('otpTimer');
    const resend = document.getElementById('resendBtn');
    resend.style.display = 'none';

    checkoutState.otpTimer = setInterval(() => {
        if (remaining <= 0) {
            clearInterval(checkoutState.otpTimer);
            timer.textContent = 'OTP expired. ';
            resend.style.display = 'inline';
            return;
        }
        const m = Math.floor(remaining / 60);
        const s = remaining % 60;
        timer.textContent = `Resend in ${m}:${s.toString().padStart(2, '0')}`;
        if (remaining <= 540) resend.style.display = 'inline';
        remaining--;
    }, 1000);
}

async function verifyOtp() {
    const otp = Array.from(document.querySelectorAll('[data-otp-index]')).map(i => i.value).join('');
    if (otp.length !== 6) return showToast('Enter the 6-digit OTP', 'error');

    const btn = document.getElementById('verifyOtpBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';

    try {
        const res = await fetch(`${API}/verify_otp.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: checkoutState.contact.phone, otp, purpose: 'checkout' })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Verification failed');

        checkoutState.verificationToken = data.verification_token;

        // Mark section 1 complete
        document.getElementById('otpForm').style.display = 'none';
        document.getElementById('verifiedDisplay').style.display = 'block';
        document.getElementById('verifiedPhone').textContent = checkoutState.contact.phone;
        document.querySelector('#sec1 .section-title').classList.add('complete');
        document.getElementById('sec1').classList.remove('active');
        document.getElementById('sec1').classList.add('complete');

        // Open section 2
        document.getElementById('sec2').classList.add('active');
        document.querySelector('#sec2 .section-title').classList.remove('locked');

        // Prefill address phone (read-only)
        document.getElementById('addrPhone').value = checkoutState.contact.phone;
        document.querySelector('#addressForm input[name="name"]').value = checkoutState.contact.name;

        if (checkoutState.otpTimer) clearInterval(checkoutState.otpTimer);
        showToast('Phone verified successfully!', 'success');

        // Try to load saved addresses
        await loadSavedAddresses();

    } catch (err) {
        showToast(err.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Verify OTP';
    }
}

async function loadSavedAddresses() {
    try {
        const res = await fetch(`${API}/addresses.php?phone=${checkoutState.contact.phone}`);
        const data = await res.json();
        if (data.success && data.addresses && data.addresses.length) {
            const list = document.getElementById('savedAddressList');
            list.innerHTML = data.addresses.map(a => `
                <div onclick="selectSavedAddress(${a.id})" data-addr-id="${a.id}" style="padding:14px;border:2px solid rgba(10,14,39,0.1);border-radius:10px;cursor:pointer;margin-bottom:10px;transition:all 0.3s;">
                    <div style="display:flex;justify-content:space-between;align-items:start;">
                        <div>
                            <strong>${escapeHtml(a.name)}</strong>
                            <span style="margin-left:8px;background:var(--cream);padding:2px 8px;border-radius:100px;font-size:11px;color:var(--gold-dark);">${escapeHtml(a.label)}</span>
                            <div style="color:var(--gray-600);font-size:13px;margin-top:4px;">
                                ${escapeHtml(a.address_line1)}${a.address_line2 ? ', ' + escapeHtml(a.address_line2) : ''}<br>
                                ${escapeHtml(a.city)}, ${escapeHtml(a.state)} - ${escapeHtml(a.pincode)}<br>
                                <i class="fas fa-phone" style="font-size:11px;"></i> ${escapeHtml(a.phone)}
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
            document.getElementById('savedAddresses').style.display = 'block';
            document.getElementById('addressForm').style.display = 'none';
            // Auto-select first
            list._addresses = data.addresses;
            window._savedAddresses = data.addresses;
        }
    } catch (e) {}
}

window.selectSavedAddress = function(id) {
    const addr = (window._savedAddresses || []).find(a => a.id == id);
    if (!addr) return;
    document.querySelectorAll('[data-addr-id]').forEach(el => {
        el.style.borderColor = 'rgba(10,14,39,0.1)';
        el.style.background = 'transparent';
    });
    const el = document.querySelector(`[data-addr-id="${id}"]`);
    el.style.borderColor = 'var(--gold)';
    el.style.background = 'rgba(212,175,55,0.05)';
    checkoutState.address = addr;
    // Auto-advance
    setTimeout(advanceToPayment, 400);
};

window.showNewAddressForm = function() {
    document.getElementById('savedAddresses').style.display = 'none';
    document.getElementById('addressForm').style.display = 'block';
};

// ===== Section 2: Address =====
function saveAddressAndContinue() {
    const form = document.getElementById('addressForm');
    const data = {
        name: form.querySelector('[name="name"]').value.trim(),
        phone: form.querySelector('[name="phone"]').value.trim(),
        address_line1: form.querySelector('[name="address_line1"]').value.trim(),
        address_line2: form.querySelector('[name="address_line2"]').value.trim(),
        city: form.querySelector('[name="city"]').value.trim(),
        state: form.querySelector('[name="state"]').value,
        pincode: form.querySelector('[name="pincode"]').value.trim(),
        email: checkoutState.contact.email
    };

    for (const [k, v] of Object.entries(data)) {
        if (k !== 'address_line2' && k !== 'email' && !v) {
            return showToast(`Please fill in ${k.replace('_', ' ')}`, 'error');
        }
    }
    if (!/^\d{6}$/.test(data.pincode)) return showToast('Pincode must be 6 digits', 'error');

    checkoutState.address = data;
    advanceToPayment();
}

function advanceToPayment() {
    document.querySelector('#sec2 .section-title').classList.add('complete');
    document.getElementById('sec2').classList.remove('active');
    document.getElementById('sec2').classList.add('complete');
    document.getElementById('sec3').classList.add('active');
    document.querySelector('#sec3 .section-title').classList.remove('locked');
    updatePlaceOrderButton();
    document.getElementById('sec3').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ===== Section 3: Place Order =====
async function placeOrder() {
    const btn = document.getElementById('placeOrderBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    try {
        const res = await fetch(`${API}/checkout.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                items: checkoutState.cart,
                payment_method: checkoutState.paymentMethod,
                address: checkoutState.address,
                customer: checkoutState.contact,
                verification_token: checkoutState.verificationToken
            })
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Checkout failed');

        // COD - order placed directly
        if (data.payment_method === 'cod') {
            localStorage.removeItem('vastu_cart');
            window.location.href = `order_success.html?id=${data.order_id}&method=cod&amount=${data.amount}`;
            return;
        }

        // Online - open Razorpay
        const options = {
            key: data.razorpay_key,
            amount: data.amount,
            currency: 'INR',
            name: 'VastuKundali Store',
            description: `${checkoutState.cart.length} item(s)`,
            order_id: data.order_id,
            handler: async function(response) {
                await verifyOnlinePayment(response, data.internal_order_id);
            },
            prefill: {
                name: checkoutState.contact.name,
                email: checkoutState.contact.email,
                contact: checkoutState.contact.phone
            },
            theme: { color: '#D4AF37' },
            modal: {
                ondismiss: () => {
                    btn.disabled = false;
                    updatePlaceOrderButton();
                    showToast('Payment cancelled', 'info');
                }
            }
        };

        if (data.razorpay_key && data.razorpay_key !== 'DEMO_MODE') {
            const rzp = new Razorpay(options);
            rzp.open();
        } else {
            // Demo mode
            showToast('Demo mode: simulating payment...', 'info');
            setTimeout(() => verifyOnlinePayment({
                razorpay_payment_id: 'pay_demo_' + Date.now(),
                razorpay_order_id: data.order_id,
                razorpay_signature: 'demo_sig'
            }, data.internal_order_id), 1500);
        }
    } catch (err) {
        showToast(err.message, 'error');
        btn.disabled = false;
        updatePlaceOrderButton();
    }
}

async function verifyOnlinePayment(response, internalOrderId) {
    try {
        const res = await fetch(`${API}/order_verify.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                razorpay_payment_id: response.razorpay_payment_id,
                razorpay_order_id: response.razorpay_order_id,
                razorpay_signature: response.razorpay_signature,
                order_id: internalOrderId
            })
        });
        const data = await res.json();
        if (data.success) {
            localStorage.removeItem('vastu_cart');
            window.location.href = `order_success.html?id=${internalOrderId}&method=online&amount=${checkoutState.pricing.total}`;
        } else {
            showToast('Payment verification failed', 'error');
            const btn = document.getElementById('placeOrderBtn');
            btn.disabled = false;
            updatePlaceOrderButton();
        }
    } catch (e) {
        showToast('Verification error', 'error');
    }
}

function escapeHtml(s) {
    if (!s) return '';
    return String(s).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}
