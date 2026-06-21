/* ==========================================================
   Report View - Render AI-Generated Vastu Report
   ========================================================== */

(async function loadReport() {
    const params = new URLSearchParams(window.location.search);
    const reportId = params.get('id');

    if (!reportId) {
        window.location.href = 'upload.html';
        return;
    }

    try {
        // Build URL with auth info
        let url = `../../backend/api/get_report.php?id=${encodeURIComponent(reportId)}`;
        const user = vastuAuth.getUser();
        if (user && user.email) url += `&email=${encodeURIComponent(user.email)}`;
        if (user && user.phone) url += `&phone=${encodeURIComponent(user.phone)}`;

        const headers = {};
        const token = localStorage.getItem('vastu_token');
        if (token) headers['Authorization'] = `Bearer ${token}`;

        const res = await fetch(url, { headers });
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch(e) {
            console.error('Report response:', text);
            showToast('Server error loading report', 'error');
            return;
        }

        if (!data.success) {
            if (data.require_auth) {
                showToast('Please login to view this report', 'error');
                setTimeout(() => window.location.href = `login.html?redirect=report.html?id=${reportId}`, 1500);
            } else {
                showToast(data.message || 'Report not found', 'error');
                setTimeout(() => window.location.href = 'upload.html', 2000);
            }
            return;
        }

        renderReport(data.report);

    } catch (err) {
        console.error(err);
        showToast('Failed to load report. Please try again.', 'error');
    }
})();

function renderReport(report) {
    document.getElementById('reportLoadingState').style.display = 'none';
    document.getElementById('reportView').style.display = 'block';

    // Cover meta
    document.getElementById('displayReportId').textContent = report.id;
    document.getElementById('metaReportId').textContent = report.id;
    document.getElementById('metaCustomer').textContent = report.customer_name || 'Valued Customer';
    document.getElementById('metaDirection').textContent = formatDirection(report.direction);
    document.getElementById('metaDate').textContent = formatDate(report.created_at);

    // Score circle animation
    const score = parseInt(report.overall_score) || 0;
    const circle = document.getElementById('scoreCircle');
    const totalLength = 283;
    const offset = (score / 100) * totalLength;
    setTimeout(() => {
        circle.style.transition = 'stroke-dasharray 1.5s ease-out';
        circle.setAttribute('stroke-dasharray', `${offset} ${totalLength}`);
    }, 100);

    // Animate score number
    animateNumber('overallScore', 0, score, 1500);

    document.getElementById('scoreRating').textContent = getRating(score);

    // Summary
    document.getElementById('reportSummary').textContent = report.summary || 'Your home has been analyzed using AI-powered Vastu Shastra principles.';

    // Heatmap (16 zones)
    renderHeatmap(report.heatmap || generateDefaultHeatmap());

    // Findings
    renderFindings('positivesList', report.positives || [], 'check-circle');
    renderFindings('negativesList', report.negatives || [], 'times-circle');

    // Rooms
    renderRooms(report.rooms || []);

    // Remedies
    renderRemedies(report.remedies || []);

    // Impact scores
    const impacts = report.impacts || {};
    document.getElementById('healthScore').textContent = (impacts.health?.score || score - 5) + '/100';
    document.getElementById('healthImpact').textContent = impacts.health?.note || 'Generally favourable';
    document.getElementById('wealthScore').textContent = (impacts.wealth?.score || score - 8) + '/100';
    document.getElementById('wealthImpact').textContent = impacts.wealth?.note || 'Stable financial flow';
    document.getElementById('relationsScore').textContent = (impacts.relations?.score || score + 2) + '/100';
    document.getElementById('relationsImpact').textContent = impacts.relations?.note || 'Harmonious bonds';
    document.getElementById('careerScore').textContent = (impacts.career?.score || score - 3) + '/100';
    document.getElementById('careerImpact').textContent = impacts.career?.note || 'Steady growth';

    // Recommended products
    renderProducts(report.recommended_products || []);

    // Final verdict
    document.getElementById('finalVerdict').textContent = report.final_verdict || generateDefaultVerdict(score);

    // Chakra Overlay Image
    const overlayUrl = report.overlay_url || (report.report_json_parsed && report.report_json_parsed.overlay_url);
    const imageUrl = report.image_url;
    const direction = report.direction;
    
    if (overlayUrl) {
        // Server-generated overlay available
        document.getElementById('overlaySection').style.display = 'block';
        document.getElementById('overlayImage').src = overlayUrl;
    } else if (imageUrl && direction) {
        // Fallback: Generate overlay client-side using Canvas
        document.getElementById('overlaySection').style.display = 'block';
        generateOverlayClientSide(imageUrl, direction);
    }

    // PDF download links
    const pdfUrl = report.pdf_url || `../../backend/api/download_pdf.php?id=${report.id}`;
    document.getElementById('downloadPdfBtn').href = pdfUrl;
    document.getElementById('downloadPdfBtn2').href = pdfUrl;

    // Share actions
    document.getElementById('shareWhatsApp').addEventListener('click', () => {
        const msg = `My Vastu Home Kundali report is ready! Vastu Score: ${score}/100. View report: ${window.location.href}`;
        window.open(`https://wa.me/?text=${encodeURIComponent(msg)}`, '_blank');
    });
    document.getElementById('emailReport').addEventListener('click', async () => {
        showToast('Sending report to your email...', 'info');
        try {
            const res = await fetch('../../backend/api/email_report.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ report_id: report.id })
            });
            const data = await res.json();
            if (data.success) showToast('Report sent to your email!', 'success');
            else showToast(data.message || 'Failed to send', 'error');
        } catch (e) {
            showToast('Failed to send email', 'error');
        }
    });
}

function animateNumber(elId, from, to, duration) {
    const el = document.getElementById(elId);
    const start = performance.now();
    function tick(now) {
        const progress = Math.min((now - start) / duration, 1);
        el.textContent = Math.floor(from + (to - from) * progress);
        if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
}

function renderHeatmap(zones) {
    const grid = document.getElementById('heatmapGrid');
    grid.innerHTML = zones.map(z => `
        <div class="heatmap-cell ${z.level}">
            <strong>${z.name}</strong>
            <span>${z.score}</span>
        </div>
    `).join('');
}

function generateDefaultHeatmap() {
    const directions = [
        'NW1', 'N1', 'N2', 'NE1',
        'W2', 'C1', 'C2', 'E1',
        'W1', 'C3', 'C4', 'E2',
        'SW1', 'S1', 'S2', 'SE1'
    ];
    const levels = ['excellent', 'good', 'average', 'poor', 'bad'];
    return directions.map((d, i) => ({
        name: d,
        score: 50 + Math.floor(Math.random() * 50),
        level: levels[Math.floor(Math.random() * levels.length)]
    }));
}

function renderFindings(elId, items, icon) {
    const el = document.getElementById(elId);
    if (!items.length) {
        el.innerHTML = '<p style="color:var(--gray-400);font-size:14px;">No items in this category.</p>';
        return;
    }
    el.innerHTML = items.map(item => `
        <div class="finding-item">
            <i class="fas fa-${icon}"></i>
            <p>${typeof item === 'string' ? item : item.text || item.description}</p>
        </div>
    `).join('');
}

function renderRooms(rooms) {
    const grid = document.getElementById('roomGrid');
    if (!rooms || !rooms.length) {
        grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:var(--gray-400);">No room data available.</p>';
        return;
    }
    grid.innerHTML = rooms.map(r => {
        // Handle various AI response formats for score
        const score = parseInt(r.score) || parseInt(r.vastu_score) || parseInt(r.compliance_score) || 0;
        const cls = score >= 75 ? 'high' : score >= 50 ? 'med' : 'low';
        // Handle various name formats
        const name = r.name || r.room_name || r.room || r.title || 'Room';
        // Handle various direction formats
        const dir = r.direction || r.zone || r.placement || r.location || '';
        // Handle various analysis/description formats
        const analysis = r.analysis || r.description || r.issue || r.comment || r.observation || r.suitability || 'Standard placement.';
        // Handle remedy
        const remedy = r.remedy || r.recommendation || r.suggestion || r.fix || '';
        return `
            <div class="room-card">
                <div class="room-card-header">
                    <h4>${name}</h4>
                    <span class="room-score-badge ${cls}">${score}/100</span>
                </div>
                <div class="direction"><i class="fas fa-compass"></i> ${formatDirection(dir)}</div>
                <p class="issue">${analysis}</p>
                ${remedy ? `<p class="remedy"><i class="fas fa-tools"></i> ${remedy}</p>` : ''}
            </div>
        `;
    }).join('');
}

function renderRemedies(remedies) {
    const list = document.getElementById('remedyList');
    if (!remedies.length) {
        list.innerHTML = '<p style="text-align:center;color:var(--gray-400);">No remedies needed - your home is well-balanced!</p>';
        return;
    }
    list.innerHTML = remedies.map((r, i) => {
        const priority = r.priority || (i < 2 ? 'high' : i < 5 ? 'medium' : 'low');
        return `
            <div class="remedy-item">
                <div class="remedy-icon"><i class="fas fa-${r.icon || 'gem'}"></i></div>
                <div class="remedy-content">
                    <h4>${r.title || r.name || 'Remedy'}</h4>
                    <p>${r.description || r.text || ''}</p>
                    <span class="remedy-priority ${priority}">${priority} priority</span>
                </div>
            </div>
        `;
    }).join('');
}

function renderProducts(products) {
    const grid = document.getElementById('recommendedProducts');
    if (!products.length) {
        grid.innerHTML = '';
        return;
    }
    grid.innerHTML = products.map(p => `
        <div class="product-card">
            <div class="product-image"><i class="fas fa-${p.icon || 'gem'}"></i></div>
            <div class="product-info">
                <h4>${p.name}</h4>
                <p class="product-desc">${p.description || ''}</p>
                <div class="product-price">₹${p.price}${p.original_price ? ` <span>₹${p.original_price}</span>` : ''}</div>
                <a href="store.html?product=${p.id || ''}" class="btn btn-sm">View Details</a>
            </div>
        </div>
    `).join('');
}

function formatDirection(d) {
    const map = { N: 'North', S: 'South', E: 'East', W: 'West', NE: 'North-East', NW: 'North-West', SE: 'South-East', SW: 'South-West' };
    return map[d] || d || '-';
}

function formatDate(dateStr) {
    if (!dateStr) return new Date().toLocaleDateString('en-IN');
    return new Date(dateStr).toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' });
}

function getRating(score) {
    if (score >= 85) return '★ Excellent';
    if (score >= 70) return '★ Very Good';
    if (score >= 55) return '★ Good';
    if (score >= 40) return '★ Average';
    return '★ Needs Attention';
}

function generateDefaultVerdict(score) {
    if (score >= 75) return 'Your home demonstrates strong Vastu alignment with significant positive energy flow. Following the suggested remedies will further amplify the prosperity, peace, and health that already grace your dwelling.';
    if (score >= 55) return 'Your home has moderate Vastu balance with several positive aspects. Implementing the recommended remedies systematically will significantly enhance the energy flow and bring greater harmony to your living space.';
    return 'Your home shows several Vastu defects that may be affecting the energy flow. We strongly recommend implementing the high-priority remedies first, followed by structural corrections where possible. Consider booking a personalized consultation for deeper guidance.';
}



/**
 * Client-side Chakra Overlay Generation (Fallback)
 * 
 * If the server didn't generate an overlay (missing GD, columns, etc.),
 * this creates it in the browser using Canvas API.
 * 
 * The Chakra PNG always has NORTH on top.
 * Rotation formula: (360 - facingDegrees) % 360
 * 
 * Example: East facing (90°) → rotate 270° CW → North points LEFT ✓
 */
function generateOverlayClientSide(imageUrl, direction) {
    const DIRECTION_DEGREES = {
        'N': 0, 'NE': 45, 'E': 90, 'SE': 135,
        'S': 180, 'SW': 225, 'W': 270, 'NW': 315
    };
    
    const DIRECTION_LABELS = {
        'N': 'North', 'NE': 'North-East', 'E': 'East', 'SE': 'South-East',
        'S': 'South', 'SW': 'South-West', 'W': 'West', 'NW': 'North-West'
    };

    const chakraUrl = '../../backend/uploads/chakra-overlay.png';
    const floorPlanImg = new Image();
    const chakraImg = new Image();
    
    let floorLoaded = false;
    let chakraLoaded = false;

    const tryCompose = () => {
        if (!floorLoaded || !chakraLoaded) return;

        try {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            canvas.width = floorPlanImg.width;
            canvas.height = floorPlanImg.height;

            // Draw floor plan as base
            ctx.drawImage(floorPlanImg, 0, 0);

            // Calculate chakra size (85% of smallest dimension)
            const chakraSize = Math.min(canvas.width, canvas.height) * 0.85;
            const centerX = canvas.width / 2;
            const centerY = canvas.height / 2;

            // Calculate rotation: (360 - facingDegrees) % 360
            const facingDeg = DIRECTION_DEGREES[direction.toUpperCase()] || 0;
            const rotationDeg = (360 - facingDeg) % 360;
            const rotationRad = (rotationDeg * Math.PI) / 180;

            // Draw rotated chakra with transparency
            ctx.save();
            ctx.globalAlpha = 0.55;
            ctx.translate(centerX, centerY);
            ctx.rotate(rotationRad);
            ctx.drawImage(chakraImg, -chakraSize / 2, -chakraSize / 2, chakraSize, chakraSize);
            ctx.restore();

            // Add direction label at top
            const label = (DIRECTION_LABELS[direction.toUpperCase()] || direction) + ' Facing ↑';
            ctx.save();
            ctx.font = 'bold 16px Inter, Arial, sans-serif';
            ctx.textAlign = 'center';
            const labelWidth = ctx.measureText(label).width;
            // Background bar
            ctx.fillStyle = 'rgba(220, 40, 40, 0.9)';
            ctx.fillRect(centerX - labelWidth / 2 - 12, 6, labelWidth + 24, 26);
            // Text
            ctx.fillStyle = '#FFFFFF';
            ctx.fillText(label, centerX, 24);
            ctx.restore();

            // Set the image source
            const overlayDataUrl = canvas.toDataURL('image/png');
            document.getElementById('overlayImage').src = overlayDataUrl;
            
        } catch (err) {
            console.error('Client-side overlay generation failed:', err);
            // Hide overlay section if generation fails
            document.getElementById('overlaySection').style.display = 'none';
        }
    };

    floorPlanImg.crossOrigin = 'anonymous';
    chakraImg.crossOrigin = 'anonymous';

    floorPlanImg.onload = () => { floorLoaded = true; tryCompose(); };
    chakraImg.onload = () => { chakraLoaded = true; tryCompose(); };
    
    floorPlanImg.onerror = () => {
        console.error('Failed to load floor plan image:', imageUrl);
        document.getElementById('overlaySection').style.display = 'none';
    };
    chakraImg.onerror = () => {
        console.error('Failed to load chakra image');
        document.getElementById('overlaySection').style.display = 'none';
    };

    floorPlanImg.src = imageUrl;
    chakraImg.src = chakraUrl;
}
