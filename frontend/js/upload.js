/* ==========================================================
   Upload Wizard + Razorpay Payment Integration
   ========================================================== */

// Wizard state
const wizardState = {
    currentStep: 1,
    totalSteps: 5,
    uploadedFile: null,
    uploadedFileUrl: null,
    direction: null,
    details: {},
    orderId: null,
    reportId: null
};

// ===== Step Navigation =====
function goToStep(stepNum) {
    // Hide all steps
    document.querySelectorAll('.wizard-step').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.progress-step').forEach(p => p.classList.remove('active'));

    // Mark previous as complete
    document.querySelectorAll('.progress-step').forEach(p => {
        const stepNo = parseInt(p.dataset.step);
        if (stepNo < stepNum) p.classList.add('complete');
        else p.classList.remove('complete');
    });

    // Activate current
    document.querySelector(`.wizard-step[data-step="${stepNum}"]`).classList.add('active');
    document.querySelector(`.progress-step[data-step="${stepNum}"]`).classList.add('active');

    wizardState.currentStep = stepNum;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Back buttons
document.querySelectorAll('[data-prev]').forEach(btn => {
    btn.addEventListener('click', () => {
        if (wizardState.currentStep > 1) goToStep(wizardState.currentStep - 1);
    });
});

// ===== Step 1: File Upload =====
const uploadZone = document.getElementById('uploadZone');
const planFile = document.getElementById('planFile');
const uploadPreview = document.getElementById('uploadPreview');
const step1Next = document.getElementById('step1Next');

if (planFile) {
    planFile.addEventListener('change', (e) => handleFile(e.target.files[0]));
}

['dragenter', 'dragover'].forEach(evt => {
    uploadZone.addEventListener(evt, (e) => {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });
});
['dragleave', 'drop'].forEach(evt => {
    uploadZone.addEventListener(evt, (e) => {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
    });
});
uploadZone.addEventListener('drop', (e) => {
    e.preventDefault();
    if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
});

function handleFile(file) {
    if (!file) return;

    const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
    if (!validTypes.includes(file.type)) {
        showToast('Please upload a JPG, PNG, or PDF file.', 'error');
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        showToast('File size must be under 10MB.', 'error');
        return;
    }

    wizardState.uploadedFile = file;

    // Show preview
    const isImage = file.type.startsWith('image/');
    let previewSrc = isImage ? URL.createObjectURL(file) : '';

    uploadPreview.style.display = 'block';
    uploadPreview.innerHTML = `
        <div class="upload-preview">
            ${isImage 
                ? `<img src="${previewSrc}" alt="preview">` 
                : `<div style="width:80px;height:80px;background:var(--gold-gradient);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:32px;color:var(--dark);"><i class="fas fa-file-pdf"></i></div>`}
            <div class="file-info">
                <strong>${file.name}</strong>
                <span>${(file.size / 1024 / 1024).toFixed(2)} MB</span>
            </div>
            <button class="file-remove" onclick="removeFile()"><i class="fas fa-times"></i></button>
        </div>
    `;

    step1Next.disabled = false;
    showToast('File ready! Click Next to continue.', 'success');
}

window.removeFile = function() {
    wizardState.uploadedFile = null;
    uploadPreview.style.display = 'none';
    uploadPreview.innerHTML = '';
    planFile.value = '';
    step1Next.disabled = true;
};

step1Next.addEventListener('click', () => {
    if (!wizardState.uploadedFile) {
        showToast('Please upload your house plan first.', 'error');
        return;
    }
    goToStep(2);
});

// ===== Step 2: Direction =====
const step2Next = document.getElementById('step2Next');
document.querySelectorAll('.direction-cell[data-dir]').forEach(cell => {
    cell.addEventListener('click', () => {
        document.querySelectorAll('.direction-cell').forEach(c => c.classList.remove('selected'));
        cell.classList.add('selected');
        wizardState.direction = cell.dataset.dir;
        step2Next.disabled = false;
    });
});

step2Next.addEventListener('click', () => {
    if (!wizardState.direction) {
        showToast('Please select your house facing direction.', 'error');
        return;
    }
    goToStep(3);
});

// ===== Step 3: Details =====
document.getElementById('step3Next').addEventListener('click', () => {
    const form = document.getElementById('detailsForm');
    const formData = new FormData(form);

    // Validate required
    const required = ['name', 'phone', 'email'];
    for (const field of required) {
        if (!formData.get(field) || !formData.get(field).trim()) {
            showToast(`Please fill in ${field}`, 'error');
            return;
        }
    }

    // Validate phone
    const phone = formData.get('phone').replace(/\D/g, '');
    if (phone.length !== 10) {
        showToast('Please enter a valid 10-digit phone number.', 'error');
        return;
    }

    // Validate email
    const email = formData.get('email');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showToast('Please enter a valid email address.', 'error');
        return;
    }

    // Save details
    wizardState.details = {};
    formData.forEach((value, key) => wizardState.details[key] = value);

    goToStep(4);
});

// ===== Step 4: Payment =====
document.getElementById('payNow').addEventListener('click', initiatePayment);

async function initiatePayment() {
    const btn = document.getElementById('payNow');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    try {
        // Step A: Upload file first
        showToast('Uploading your plan...', 'info');
        const formData = new FormData();
        formData.append('plan', wizardState.uploadedFile);
        formData.append('name', wizardState.details.name);
        formData.append('email', wizardState.details.email);
        formData.append('phone', wizardState.details.phone);
        formData.append('direction', wizardState.direction);
        formData.append('plot_size', wizardState.details.plot_size || '');
        formData.append('floors', wizardState.details.floors || '');
        formData.append('concerns', wizardState.details.concerns || '');
        formData.append('city', wizardState.details.city || '');

        const uploadRes = await fetch('../../backend/api/upload.php', {
            method: 'POST',
            body: formData
        });
        const uploadText = await uploadRes.text();
        let uploadData;
        try {
            uploadData = JSON.parse(uploadText);
        } catch (e) {
            console.error('Upload response (not JSON):', uploadText);
            throw new Error('Server error during upload. Check backend/debug.log or enable APP_ENV=development in config.php');
        }

        if (!uploadData.success) {
            throw new Error(uploadData.message || 'Upload failed');
        }

        wizardState.uploadedFileUrl = uploadData.file_url;
        wizardState.reportId = uploadData.report_id;

        // Step B: Create Razorpay order
        const orderRes = await fetch('../../backend/api/payment_create_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                amount: 99,
                report_id: wizardState.reportId,
                customer: {
                    name: wizardState.details.name,
                    email: wizardState.details.email,
                    contact: wizardState.details.phone
                }
            })
        });
        const orderText = await orderRes.text();
        let orderData;
        try {
            orderData = JSON.parse(orderText);
        } catch (e) {
            console.error('Order response (not JSON):', orderText);
            throw new Error('Server error creating payment order. Check backend/debug.log');
        }

        if (!orderData.success) {
            throw new Error(orderData.message || 'Failed to create order');
        }

        wizardState.orderId = orderData.order_id;

        // Step C: Open Razorpay checkout
        const options = {
            key: orderData.razorpay_key,
            amount: orderData.amount,
            currency: 'INR',
            name: 'VastuKundali AI',
            description: 'AI Vastu Home Kundali Report',
            order_id: orderData.order_id,
            image: '',
            handler: function(response) {
                verifyPayment(response);
            },
            prefill: {
                name: wizardState.details.name,
                email: wizardState.details.email,
                contact: wizardState.details.phone
            },
            notes: {
                report_id: wizardState.reportId
            },
            theme: {
                color: '#D4AF37'
            },
            modal: {
                ondismiss: function() {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-lock"></i> Pay ₹99 Securely';
                    showToast('Payment cancelled. Try again when ready.', 'info');
                }
            }
        };

        // If Razorpay key is configured, open real checkout; otherwise use demo mode
        if (orderData.razorpay_key && orderData.razorpay_key !== 'DEMO_MODE') {
            const rzp = new Razorpay(options);
            rzp.open();
        } else {
            // DEMO MODE: simulate successful payment
            showToast('Demo mode: simulating payment...', 'info');
            setTimeout(() => {
                verifyPayment({
                    razorpay_payment_id: 'pay_demo_' + Date.now(),
                    razorpay_order_id: orderData.order_id,
                    razorpay_signature: 'demo_signature'
                });
            }, 1500);
        }

    } catch (err) {
        console.error(err);
        showToast(err.message || 'Something went wrong. Please try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock"></i> Pay ₹99 Securely';
    }
}

async function verifyPayment(response) {
    showToast('Payment successful! Generating your report...', 'success');
    goToStep(5);

    try {
        const verifyRes = await fetch('../../backend/api/payment_verify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                razorpay_payment_id: response.razorpay_payment_id,
                razorpay_order_id: response.razorpay_order_id,
                razorpay_signature: response.razorpay_signature,
                report_id: wizardState.reportId
            })
        });
        const verifyText = await verifyRes.text();
        let verifyData;
        try {
            verifyData = JSON.parse(verifyText);
        } catch (e) {
            console.error('Verify response (not JSON):', verifyText);
            throw new Error('Payment verification server error');
        }

        if (!verifyData.success) {
            throw new Error(verifyData.message || 'Payment verification failed');
        }

        // Animate loading steps
        await animateLoadingSteps();

        // Trigger report generation
        const genRes = await fetch('../../backend/api/generate_report.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ report_id: wizardState.reportId })
        });
        const genText = await genRes.text();
        let genData;
        try {
            genData = JSON.parse(genText);
        } catch (e) {
            console.error('Generate response (not JSON):', genText);
            throw new Error('Report generation server error. The report may still be processing - check your dashboard.');
        }

        if (genData.success) {
            // Redirect to report view
            setTimeout(() => {
                window.location.href = `report.html?id=${wizardState.reportId}`;
            }, 1500);
        } else {
            throw new Error(genData.message || 'Report generation failed');
        }

    } catch (err) {
        console.error(err);
        showToast('Error: ' + (err.message || 'Please contact support'), 'error');
    }
}

async function animateLoadingSteps() {
    const steps = document.querySelectorAll('.loading-step');
    const tasks = ['upload', 'ocr', 'analyze', 'score', 'remedies', 'pdf'];

    for (let i = 1; i < tasks.length; i++) {
        await new Promise(resolve => setTimeout(resolve, 1200 + Math.random() * 800));
        const prev = steps[i - 1];
        prev.classList.remove('active');
        prev.classList.add('done');
        prev.querySelector('i').className = 'fas fa-check-circle';

        if (i < steps.length) {
            steps[i].classList.add('active');
            steps[i].querySelector('i').className = 'fas fa-spinner';
        }
    }
    // Mark last as done
    await new Promise(resolve => setTimeout(resolve, 1000));
    const lastStep = steps[steps.length - 1];
    lastStep.classList.remove('active');
    lastStep.classList.add('done');
    lastStep.querySelector('i').className = 'fas fa-check-circle';
}
