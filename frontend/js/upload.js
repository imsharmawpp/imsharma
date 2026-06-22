/* ==========================================================
   Upload Wizard - Questionnaire + Upload/Direction + Payment
   
   Flow:
   Step 1: Questionnaire (property type, subtypes, problem areas, contact + OTP)
   Step 2: Upload + Direction (split screen - left: upload, right: direction)
   Step 3: Payment (Razorpay)
   Step 4: Report generation
   ========================================================== */

// ===== Wizard State =====
const wizardState = {
    currentStep: 1,
    totalSteps: 4,
    // Questionnaire
    propertyCategory: null, // 'commercial' | 'residential'
    propertySubType: null,
    sizeSqft: null,
    problemAreas: [],      // max 2
    otherProblemText: '',
    name: '',
    phone: '',
    email: '',
    phoneVerified: false,
    // Upload + Direction
    uploadedFile: null,
    uploadedFileUrl: null,
    direction: null,
    planValidated: false,
    planValidating: false,
    markers: [],            // [{ type, label, nx, ny }]  nx/ny normalized [0..1]
    activeMarkerType: null, // currently selected palette element
    // Payment
    orderId: null,
    reportId: null
};

// ===== Constants =====
// NOTE: 'Land' removed from commercial — a vacant plot has no plan to upload.
const COMMERCIAL_SUBTYPES = [
    { value: 'office_space', label: 'Office Space', icon: 'building' },
    { value: 'retail_showroom', label: 'Retail / Showroom', icon: 'store' },
    { value: 'factory', label: 'Factory', icon: 'industry' },
    { value: 'warehouse', label: 'Warehouse', icon: 'warehouse' }
];

const RESIDENTIAL_SUBTYPES = [
    { value: 'row_house_kothi', label: 'Row House / Kothi', icon: 'home' },
    { value: 'builder_floor_apartment', label: 'Builder Floor / High-Rise Apartment', icon: 'city' },
    { value: 'villa', label: 'Villa', icon: 'landmark' }
];

// ===== Element catalogs for plan markers (category-specific) =====
// 'entrance' is always first and is REQUIRED. Values match VastuEngine rule keys.
const RESIDENTIAL_ELEMENTS = [
    { value: 'entrance', label: 'Main Entrance', icon: 'door-open', required: true },
    { value: 'living_room', label: 'Living Room', icon: 'couch' },
    { value: 'kitchen', label: 'Kitchen', icon: 'utensils' },
    { value: 'master_bedroom', label: 'Master Bedroom', icon: 'bed' },
    { value: 'bedroom', label: 'Bedroom', icon: 'bed' },
    { value: 'pooja_room', label: 'Pooja Room', icon: 'om' },
    { value: 'toilet', label: 'Toilet / Washroom', icon: 'toilet' },
    { value: 'dining', label: 'Dining', icon: 'utensils' },
    { value: 'staircase', label: 'Staircase', icon: 'stairs' },
    { value: 'balcony', label: 'Balcony', icon: 'border-all' },
    { value: 'store_room', label: 'Store Room', icon: 'box' }
];

// Commercial elements are filtered per sub-type below.
const COMMERCIAL_ELEMENTS_BASE = [
    { value: 'entrance', label: 'Main Entrance', icon: 'door-open', required: true, all: true },
    { value: 'reception', label: 'Reception', icon: 'bell-concierge', all: true },
    { value: 'owner_cabin', label: 'Owner / Director Cabin', icon: 'user-tie', all: true },
    { value: 'manager_cabin', label: 'Manager Cabin', icon: 'user', types: ['office_space','factory'] },
    { value: 'staff_area', label: 'Staff / Workstations', icon: 'users', types: ['office_space','factory','warehouse'] },
    { value: 'accounts', label: 'Accounts / Cash', icon: 'calculator', all: true },
    { value: 'cash_locker', label: 'Cash / Locker', icon: 'vault', types: ['retail_showroom','office_space'] },
    { value: 'meeting_room', label: 'Conference / Meeting Room', icon: 'people-group', types: ['office_space'] },
    { value: 'pantry', label: 'Pantry', icon: 'mug-hot', types: ['office_space','factory'] },
    { value: 'display_area', label: 'Display Area', icon: 'store', types: ['retail_showroom'] },
    { value: 'billing_counter', label: 'Billing Counter', icon: 'cash-register', types: ['retail_showroom'] },
    { value: 'machinery', label: 'Machinery', icon: 'gears', types: ['factory'] },
    { value: 'production', label: 'Production Area', icon: 'industry', types: ['factory'] },
    { value: 'heavy_storage', label: 'Heavy Storage', icon: 'warehouse', types: ['factory','warehouse'] },
    { value: 'inventory', label: 'Inventory / Store', icon: 'boxes-stacked', types: ['retail_showroom','warehouse','factory'] },
    { value: 'loading_bay', label: 'Loading Bay', icon: 'truck-ramp-box', types: ['factory','warehouse'] },
    { value: 'toilet', label: 'Toilet / Washroom', icon: 'toilet', all: true }
];

function getElementsForSelection(category, subType) {
    if (category === 'residential') return RESIDENTIAL_ELEMENTS;
    return COMMERCIAL_ELEMENTS_BASE.filter(e => e.all || (e.types && e.types.includes(subType)));
}

const MAX_PROBLEM_AREAS = 2;

// ===== Step Navigation =====
function goToStep(stepNum) {
    document.querySelectorAll('.wizard-step').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.progress-step').forEach(p => {
        const stepNo = parseInt(p.dataset.step);
        p.classList.remove('active');
        if (stepNo < stepNum) p.classList.add('complete');
        else p.classList.remove('complete');
    });

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

// ===== STEP 1: Questionnaire =====

// Property Category Toggle
document.querySelectorAll('.type-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        wizardState.propertyCategory = btn.dataset.category;
        wizardState.propertySubType = null;
        showSubTypes(btn.dataset.category);
        updateStep1Button();
    });
});

function showSubTypes(category) {
    const group = document.getElementById('subTypeGroup');
    const container = document.getElementById('subTypeOptions');
    const sizeGroup = document.getElementById('sizeGroup');
    
    const subtypes = category === 'commercial' ? COMMERCIAL_SUBTYPES : RESIDENTIAL_SUBTYPES;
    
    container.innerHTML = subtypes.map(st => `
        <button type="button" class="subtype-btn" data-subtype="${st.value}">
            <i class="fas fa-${st.icon}"></i>
            <span>${st.label}</span>
        </button>
    `).join('');
    
    group.style.display = 'block';
    sizeGroup.style.display = 'block';
    
    // Attach listeners
    container.querySelectorAll('.subtype-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            container.querySelectorAll('.subtype-btn').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            wizardState.propertySubType = btn.dataset.subtype;
            updateStep1Button();
        });
    });
}

// Problem Area Pills (LinkedIn-style, max 2)
document.querySelectorAll('.pill-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const area = btn.dataset.area;
        
        if (btn.classList.contains('selected')) {
            // Deselect
            btn.classList.remove('selected');
            wizardState.problemAreas = wizardState.problemAreas.filter(a => a !== area);
            if (area === 'other') {
                document.getElementById('otherProblemGroup').style.display = 'none';
            }
        } else {
            // Check max
            if (wizardState.problemAreas.length >= MAX_PROBLEM_AREAS) {
                showToast(`You can select a maximum of ${MAX_PROBLEM_AREAS} problem areas.`, 'error');
                return;
            }
            btn.classList.add('selected');
            wizardState.problemAreas.push(area);
            if (area === 'other') {
                document.getElementById('otherProblemGroup').style.display = 'block';
            }
        }
        
        // Update disabled state of unselected pills when at max
        document.querySelectorAll('.pill-btn').forEach(p => {
            if (!p.classList.contains('selected')) {
                p.classList.toggle('disabled', wizardState.problemAreas.length >= MAX_PROBLEM_AREAS);
            }
        });
        
        updateStep1Button();
    });
});

// Other problem text counter
const otherTextInput = document.getElementById('otherProblemText');
if (otherTextInput) {
    otherTextInput.addEventListener('input', () => {
        const len = otherTextInput.value.length;
        document.getElementById('otherCharCount').textContent = `${len}/30`;
        wizardState.otherProblemText = otherTextInput.value;
    });
}

// Phone OTP Flow
const phoneInput = document.getElementById('qPhone');
const sendOtpBtn = document.getElementById('sendOtpBtn');
const verifyOtpBtn = document.getElementById('verifyOtpBtn');
const otpInput = document.getElementById('otpInput');
let otpTimerInterval = null;

phoneInput.addEventListener('input', () => {
    const val = phoneInput.value.replace(/\D/g, '');
    phoneInput.value = val;
    wizardState.phone = val;
    sendOtpBtn.disabled = val.length < 10;
    updateStep1Button();
});

sendOtpBtn.addEventListener('click', async () => {
    if (wizardState.phone.length < 10) return;
    
    sendOtpBtn.disabled = true;
    sendOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    try {
        const res = await fetch('../../backend/api/send_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: wizardState.phone })
        });
        const data = await res.json();
        
        if (data.success) {
            document.getElementById('otpSection').style.display = 'block';
            showToast('OTP sent to your WhatsApp!', 'success');
            startOtpTimer(60);
        } else {
            showToast(data.message || 'Failed to send OTP', 'error');
            sendOtpBtn.disabled = false;
        }
    } catch (e) {
        showToast('Failed to send OTP. Please try again.', 'error');
        sendOtpBtn.disabled = false;
    }
    
    sendOtpBtn.innerHTML = 'Resend';
});

otpInput.addEventListener('input', () => {
    const val = otpInput.value.replace(/\D/g, '');
    otpInput.value = val;
    verifyOtpBtn.disabled = val.length < 6;
});

verifyOtpBtn.addEventListener('click', async () => {
    verifyOtpBtn.disabled = true;
    verifyOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    try {
        const res = await fetch('../../backend/api/verify_otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                phone: wizardState.phone,
                otp: otpInput.value,
                name: (document.getElementById('qName')?.value || '').trim(),
                email: (document.getElementById('qEmail')?.value || '').trim()
            })
        });
        const data = await res.json();
        
        if (data.success) {
            wizardState.phoneVerified = true;
            // Persist the auto-created account so the customer can track their
            // reports & orders later using the same mobile number.
            if (data.account_token) {
                try {
                    localStorage.setItem('vk_account_token', data.account_token);
                    if (data.user_id) localStorage.setItem('vk_user_id', String(data.user_id));
                    localStorage.setItem('vk_phone', wizardState.phone);
                } catch (e) { /* storage may be blocked - non-fatal */ }
            }
            document.getElementById('otpSection').style.display = 'none';
            document.getElementById('phoneVerified').style.display = 'flex';
            phoneInput.disabled = true;
            sendOtpBtn.style.display = 'none';
            clearInterval(otpTimerInterval);
            showToast('Phone verified successfully!', 'success');
            updateStep1Button();
        } else {
            showToast(data.message || 'Invalid OTP. Try again.', 'error');
            verifyOtpBtn.disabled = false;
        }
    } catch (e) {
        showToast('Verification failed. Try again.', 'error');
        verifyOtpBtn.disabled = false;
    }
    
    verifyOtpBtn.innerHTML = 'Verify';
});

function startOtpTimer(seconds) {
    let remaining = seconds;
    const timerEl = document.getElementById('otpTimer');
    timerEl.textContent = `Resend in ${remaining}s`;
    sendOtpBtn.disabled = true;
    
    clearInterval(otpTimerInterval);
    otpTimerInterval = setInterval(() => {
        remaining--;
        if (remaining <= 0) {
            clearInterval(otpTimerInterval);
            timerEl.textContent = '';
            sendOtpBtn.disabled = false;
        } else {
            timerEl.textContent = `Resend in ${remaining}s`;
        }
    }, 1000);
}

function updateStep1Button() {
    const nameVal = document.getElementById('qName').value.trim();
    const canProceed = 
        wizardState.propertyCategory &&
        wizardState.propertySubType &&
        wizardState.problemAreas.length > 0 &&
        nameVal.length > 0 &&
        wizardState.phone.length === 10 &&
        wizardState.phoneVerified;
    
    document.getElementById('step1Next').disabled = !canProceed;
}

// Listen to name changes
document.getElementById('qName').addEventListener('input', updateStep1Button);

// Step 1 Next
document.getElementById('step1Next').addEventListener('click', () => {
    wizardState.name = document.getElementById('qName').value.trim();
    wizardState.email = document.getElementById('qEmail').value.trim();
    wizardState.sizeSqft = document.getElementById('sizeSqft').value || null;
    
    // Validate
    if (!wizardState.propertyCategory) { showToast('Please select property type', 'error'); return; }
    if (!wizardState.propertySubType) { showToast('Please select property sub-type', 'error'); return; }
    if (wizardState.problemAreas.length === 0) { showToast('Please select at least one problem area', 'error'); return; }
    if (!wizardState.name) { showToast('Name is required', 'error'); return; }
    if (!wizardState.phoneVerified) { showToast('Please verify your mobile number', 'error'); return; }
    
    // Capture lead
    captureLead();
    
    goToStep(2);
});

async function captureLead() {
    try {
        await fetch('../../backend/api/upload.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'capture_lead',
                name: wizardState.name,
                phone: wizardState.phone,
                email: wizardState.email,
                property_category: wizardState.propertyCategory,
                property_subtype: wizardState.propertySubType,
                problem_areas: wizardState.problemAreas,
                size_sqft: wizardState.sizeSqft
            })
        });
    } catch (e) {
        // Silent - don't block user flow
    }
}

// ===== STEP 2: Upload + Direction (Split Screen) =====
const uploadZone = document.getElementById('uploadZone');
const planFile = document.getElementById('planFile');
const uploadPreview = document.getElementById('uploadPreview');
const step2Next = document.getElementById('step2Next');

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

    const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!validTypes.includes(file.type)) {
        showValidationError('Please upload a JPG or PNG image file. PDFs are not supported for automated analysis.');
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        showValidationError('File size must be under 10MB.');
        return;
    }

    wizardState.uploadedFile = file;
    wizardState.planValidated = false;
    wizardState.planValidating = true;

    // Show preview
    const previewSrc = URL.createObjectURL(file);
    uploadPreview.style.display = 'block';
    uploadPreview.innerHTML = `
        <div class="upload-preview">
            <img src="${previewSrc}" alt="Floor plan preview">
            <div class="file-info">
                <strong>${file.name}</strong>
                <span>${(file.size / 1024 / 1024).toFixed(2)} MB</span>
            </div>
            <button class="file-remove" onclick="removeFile()"><i class="fas fa-times"></i></button>
        </div>
    `;

    // Show validating state
    showValidationStatus('validating', 'Analysing floor plan...');

    // Validate with backend
    validateFloorPlan(file);
    updateStep2Button();
}

async function validateFloorPlan(file) {
    try {
        const formData = new FormData();
        formData.append('plan', file);
        // Send selected category so backend can verify the plan matches
        if (wizardState.propertyCategory) formData.append('property_category', wizardState.propertyCategory);
        if (wizardState.propertySubType) formData.append('property_subtype', wizardState.propertySubType);

        const res = await fetch('../../backend/api/validate_plan.php', {
            method: 'POST',
            body: formData
        });
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch(e) {
            showValidationError('Server error during validation. Please try again.');
            wizardState.planValidating = false;
            return;
        }

        wizardState.planValidating = false;

        if (data.isValid) {
            wizardState.planValidated = true;
            showValidationStatus('success', 'Valid floor plan detected');
            showToast('Floor plan validated successfully!', 'success');
            initMarkerSection(file);
        } else {
            wizardState.planValidated = false;
            showValidationError(data.errorMessage || 'Floor plan validation failed.');
            hideMarkerSection();
        }
    } catch (e) {
        wizardState.planValidating = false;
        wizardState.planValidated = false;
        showValidationError('Validation failed. Please try again or connect with our support team.');
    }
    updateStep2Button();
}

function showValidationStatus(type, message) {
    const el = document.getElementById('validationStatus');
    el.style.display = 'block';
    
    if (type === 'validating') {
        el.className = 'validation-status validating';
        el.innerHTML = `<i class="fas fa-spinner fa-spin"></i> <span>${message}</span>`;
    } else if (type === 'success') {
        el.className = 'validation-status success';
        el.innerHTML = `<i class="fas fa-check-circle"></i> <span>${message}</span>`;
    } else if (type === 'error') {
        el.className = 'validation-status error';
        el.innerHTML = `
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Cannot Generate Report</strong>
                <p>${message}</p>
                <a href="https://wa.me/919876543210" target="_blank" class="support-link">
                    <i class="fab fa-whatsapp"></i> Connect with our support team
                </a>
            </div>
        `;
    }
}

function showValidationError(message) {
    showValidationStatus('error', message);
    wizardState.planValidated = false;
    wizardState.planValidating = false;
    updateStep2Button();
}

window.removeFile = function() {
    wizardState.uploadedFile = null;
    wizardState.planValidated = false;
    wizardState.planValidating = false;
    wizardState.markers = [];
    wizardState.activeMarkerType = null;
    uploadPreview.style.display = 'none';
    uploadPreview.innerHTML = '';
    planFile.value = '';
    document.getElementById('validationStatus').style.display = 'none';
    hideMarkerSection();
    updateStep2Button();
};

// ===== Interactive Plan Markers =====
function hideMarkerSection() {
    const sec = document.getElementById('markerSection');
    if (sec) sec.style.display = 'none';
}

function initMarkerSection(file) {
    const sec = document.getElementById('markerSection');
    const img = document.getElementById('markerPlanImg');
    if (!sec || !img) return;

    wizardState.markers = [];
    wizardState.activeMarkerType = null;

    // Show the uploaded plan in the marker stage
    if (wizardState._markerObjUrl) URL.revokeObjectURL(wizardState._markerObjUrl);
    wizardState._markerObjUrl = URL.createObjectURL(file);
    img.src = wizardState._markerObjUrl;

    buildMarkerPalette();
    renderMarkers();
    sec.style.display = 'block';
}

function buildMarkerPalette() {
    const palette = document.getElementById('markerPalette');
    if (!palette) return;
    const elements = getElementsForSelection(wizardState.propertyCategory, wizardState.propertySubType);
    palette.innerHTML = elements.map(el => `
        <button type="button" class="marker-chip${el.required ? ' required' : ''}"
                data-type="${el.value}" data-label="${el.label}">
            <i class="fas fa-${el.icon || 'location-dot'}"></i>
            <span>${el.label}</span>
            ${el.required ? '<em class="req-tag">required</em>' : ''}
        </button>
    `).join('');

    palette.querySelectorAll('.marker-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            const wasActive = chip.classList.contains('active');
            palette.querySelectorAll('.marker-chip').forEach(c => c.classList.remove('active'));
            if (wasActive) {
                wizardState.activeMarkerType = null;
            } else {
                chip.classList.add('active');
                wizardState.activeMarkerType = { type: chip.dataset.type, label: chip.dataset.label };
                setMarkerHint(`Tap on the plan to place <strong>${chip.dataset.label}</strong>.`);
            }
        });
    });
}

function setMarkerHint(html) {
    const h = document.getElementById('markerHint');
    if (h) h.innerHTML = `<i class="fas fa-hand-pointer"></i> ${html}`;
}

// Place a marker when the plan is clicked/tapped
(function attachStageHandler() {
    const stage = document.getElementById('markerStage');
    if (!stage) return;
    stage.addEventListener('click', (e) => {
        const active = wizardState.activeMarkerType;
        if (!active) {
            setMarkerHint('Select a label above first, then tap the plan.');
            return;
        }
        const rect = stage.getBoundingClientRect();
        const nx = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
        const ny = Math.min(1, Math.max(0, (e.clientY - rect.top) / rect.height));

        // Entrance is unique; replace if it already exists. Others can repeat.
        if (active.type === 'entrance') {
            wizardState.markers = wizardState.markers.filter(m => m.type !== 'entrance');
        }
        wizardState.markers.push({ type: active.type, label: active.label, nx, ny });
        renderMarkers();
        updateStep2Button();
    });
})();

function renderMarkers() {
    const stage = document.getElementById('markerStage');
    const list = document.getElementById('markerList');
    if (!stage) return;

    // Remove old dots
    stage.querySelectorAll('.marker-dot').forEach(d => d.remove());

    wizardState.markers.forEach((m, i) => {
        const dot = document.createElement('div');
        dot.className = 'marker-dot' + (m.type === 'entrance' ? ' entry' : '');
        dot.style.left = (m.nx * 100) + '%';
        dot.style.top = (m.ny * 100) + '%';
        dot.title = m.label;
        dot.innerHTML = `<span class="marker-dot-no">${i + 1}</span>`;
        stage.appendChild(dot);
    });

    if (list) {
        if (!wizardState.markers.length) {
            list.innerHTML = '<p class="marker-empty">No areas marked yet. The Main Entrance is required to continue.</p>';
        } else {
            list.innerHTML = wizardState.markers.map((m, i) => `
                <span class="marker-tag${m.type === 'entrance' ? ' entry' : ''}">
                    <b>${i + 1}.</b> ${m.label}
                    <button type="button" class="marker-tag-x" data-idx="${i}" aria-label="Remove">&times;</button>
                </span>
            `).join('');
            list.querySelectorAll('.marker-tag-x').forEach(btn => {
                btn.addEventListener('click', () => {
                    wizardState.markers.splice(parseInt(btn.dataset.idx), 1);
                    renderMarkers();
                    updateStep2Button();
                });
            });
        }
    }
}

function hasEntranceMarker() {
    return wizardState.markers.some(m => m.type === 'entrance');
}

// Direction Selection
document.querySelectorAll('.direction-cell[data-dir]').forEach(cell => {
    cell.addEventListener('click', () => {
        document.querySelectorAll('.direction-cell').forEach(c => c.classList.remove('selected'));
        cell.classList.add('selected');
        wizardState.direction = cell.dataset.dir;
        
        const dirMap = { N:'North', S:'South', E:'East', W:'West', NE:'North-East', NW:'North-West', SE:'South-East', SW:'South-West' };
        document.getElementById('dirLabel').textContent = dirMap[cell.dataset.dir] || cell.dataset.dir;
        document.getElementById('selectedDirection').style.display = 'flex';
        
        updateStep2Button();
    });
});

function updateStep2Button() {
    // STRICT: validated plan AND direction AND entrance marker required
    const canProceed = wizardState.planValidated && wizardState.direction
        && !wizardState.planValidating && hasEntranceMarker();
    step2Next.disabled = !canProceed;

    // Update button text based on state
    if (wizardState.planValidating) {
        step2Next.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validating...';
    } else if (!wizardState.planValidated && wizardState.uploadedFile) {
        step2Next.innerHTML = '<i class="fas fa-times-circle"></i> Floor Plan Invalid';
    } else if (wizardState.planValidated && !hasEntranceMarker()) {
        step2Next.innerHTML = 'Mark the Main Entrance to continue';
    } else {
        step2Next.innerHTML = 'Proceed to Payment <i class="fas fa-arrow-right"></i>';
    }
}

step2Next.addEventListener('click', () => {
    // STRICT GATE: Cannot proceed without validated plan
    if (!wizardState.planValidated) {
        showToast('Please upload a valid floor plan first.', 'error');
        return;
    }
    if (!wizardState.direction) {
        showToast('Please select facing direction.', 'error');
        return;
    }
    if (!hasEntranceMarker()) {
        showToast('Please mark the Main Entrance on your plan.', 'error');
        return;
    }
    goToStep(3);
});

// ===== STEP 3: Payment =====
document.getElementById('payNow').addEventListener('click', initiatePayment);

async function initiatePayment() {
    const btn = document.getElementById('payNow');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    try {
        // Step A: Upload file to server
        showToast('Uploading your plan...', 'info');
        const formData = new FormData();
        formData.append('plan', wizardState.uploadedFile);
        formData.append('name', wizardState.name);
        formData.append('email', wizardState.email);
        formData.append('phone', wizardState.phone);
        formData.append('direction', wizardState.direction);
        formData.append('property_category', wizardState.propertyCategory);
        formData.append('property_subtype', wizardState.propertySubType);
        formData.append('size_sqft', wizardState.sizeSqft || '');
        formData.append('problem_areas', JSON.stringify(wizardState.problemAreas));
        formData.append('other_problem_text', wizardState.otherProblemText || '');
        formData.append('markers', JSON.stringify(wizardState.markers || []));
        formData.append('concerns', wizardState.problemAreas.join(', ') + (wizardState.otherProblemText ? ': ' + wizardState.otherProblemText : ''));

        const uploadRes = await fetch('../../backend/api/upload.php', {
            method: 'POST',
            body: formData
        });
        const uploadText = await uploadRes.text();
        let uploadData;
        try { uploadData = JSON.parse(uploadText); } catch (e) {
            console.error('Upload response:', uploadText);
            throw new Error('Server error during upload.');
        }

        if (!uploadData.success) throw new Error(uploadData.message || 'Upload failed');

        wizardState.uploadedFileUrl = uploadData.file_url;
        wizardState.reportId = uploadData.report_id;

        // Step B: Create Razorpay order
        const orderRes = await fetch('../../backend/api/payment_create_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                amount: 99,
                report_id: wizardState.reportId,
                customer: { name: wizardState.name, email: wizardState.email, contact: wizardState.phone }
            })
        });
        const orderText = await orderRes.text();
        let orderData;
        try { orderData = JSON.parse(orderText); } catch (e) {
            throw new Error('Server error creating payment order.');
        }

        if (!orderData.success) throw new Error(orderData.message || 'Failed to create order');

        wizardState.orderId = orderData.order_id;

        // Step C: Open Razorpay checkout
        const options = {
            key: orderData.razorpay_key,
            amount: orderData.amount,
            currency: 'INR',
            name: 'VastuKundali AI',
            description: 'AI Vastu Home Kundali Report',
            order_id: orderData.order_id,
            handler: function(response) { verifyPayment(response); },
            prefill: { name: wizardState.name, email: wizardState.email, contact: wizardState.phone },
            notes: { report_id: wizardState.reportId },
            theme: { color: '#D4AF37' },
            modal: {
                ondismiss: function() {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-lock"></i> Pay ₹99 Securely';
                    showToast('Payment cancelled.', 'info');
                }
            }
        };

        if (orderData.razorpay_key && orderData.razorpay_key !== 'DEMO_MODE') {
            const rzp = new Razorpay(options);
            rzp.open();
        } else {
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
        showToast(err.message || 'Something went wrong.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock"></i> Pay ₹99 Securely';
    }
}

async function verifyPayment(response) {
    showToast('Payment successful! Generating report...', 'success');
    goToStep(4);

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
        try { verifyData = JSON.parse(verifyText); } catch (e) { throw new Error('Payment verification error'); }
        if (!verifyData.success) throw new Error(verifyData.message || 'Verification failed');

        await animateLoadingSteps();

        // Trigger report generation
        const genRes = await fetch('../../backend/api/generate_report.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ report_id: wizardState.reportId })
        });
        const genText = await genRes.text();
        let genData;
        try { genData = JSON.parse(genText); } catch (e) { throw new Error('Report generation error'); }

        if (genData.success) {
            setTimeout(() => { window.location.href = `report.html?id=${wizardState.reportId}`; }, 1500);
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
    for (let i = 1; i < steps.length; i++) {
        await new Promise(resolve => setTimeout(resolve, 1000 + Math.random() * 600));
        steps[i - 1].classList.remove('active');
        steps[i - 1].classList.add('done');
        steps[i - 1].querySelector('i').className = 'fas fa-check-circle';
        steps[i].classList.add('active');
        steps[i].querySelector('i').className = 'fas fa-spinner fa-spin';
    }
    await new Promise(resolve => setTimeout(resolve, 800));
    const last = steps[steps.length - 1];
    last.classList.remove('active');
    last.classList.add('done');
    last.querySelector('i').className = 'fas fa-check-circle';
}
