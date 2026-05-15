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
        const res = await fetch(`../../backend/api/get_report.php?id=${encodeURIComponent(reportId)}`);
        const data = await res.json();

        if (!data.success) {
            showToast(data.message || 'Report not found', 'error');
            setTimeout(() => window.location.href = 'upload.html', 2000);
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
    if (!rooms.length) {
        grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:var(--gray-400);">No room data available.</p>';
        return;
    }
    grid.innerHTML = rooms.map(r => {
        const cls = r.score >= 75 ? 'high' : r.score >= 50 ? 'med' : 'low';
        return `
            <div class="room-card">
                <div class="room-card-header">
                    <h4>${r.name}</h4>
                    <span class="room-score-badge ${cls}">${r.score}/100</span>
                </div>
                <div class="direction"><i class="fas fa-compass"></i> ${formatDirection(r.direction)}</div>
                <p class="issue">${r.analysis || r.issue || 'Standard placement.'}</p>
                ${r.remedy ? `<p class="remedy"><i class="fas fa-tools"></i> ${r.remedy}</p>` : ''}
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
