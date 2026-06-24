/* ============================================
   SN Financial - Main JavaScript
   Interactive Charts, Animations & Functionality
   ============================================ */

// Wait for DOM to load
document.addEventListener('DOMContentLoaded', () => {
    initNavbar();
    initHeroChart();
    initDashboardChart();
    initFeatureChart();
    initWatchlist();
    initCalculator();
    initCounters();
    initScrollReveal();
    initMarquee();
    initMobileMenu();
});

/* ============================================
   NAVBAR
   ============================================ */
function initNavbar() {
    const navbar = document.getElementById('navbar');
    let lastScroll = 0;

    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }

        lastScroll = currentScroll;
    });
}

/* ============================================
   MOBILE MENU
   ============================================ */
function initMobileMenu() {
    const hamburger = document.getElementById('hamburger');
    const navLinks = document.getElementById('navLinks');

    if (hamburger) {
        hamburger.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            hamburger.classList.toggle('active');
        });

        // Close menu on link click
        const links = navLinks.querySelectorAll('a');
        links.forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('active');
                hamburger.classList.remove('active');
            });
        });
    }
}

/* ============================================
   HERO CHART
   ============================================ */
function initHeroChart() {
    const ctx = document.getElementById('mainChart');
    if (!ctx) return;

    const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(0, 208, 156, 0.3)');
    gradient.addColorStop(1, 'rgba(0, 208, 156, 0.0)');

    // Generate realistic stock data
    const dataPoints = generateStockData(50, 22000, 23000);
    const labels = generateTimeLabels(50);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                data: dataPoints,
                borderColor: '#00d09c',
                borderWidth: 2,
                backgroundColor: gradient,
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: '#00d09c',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a2332',
                    titleColor: '#fff',
                    bodyColor: '#00d09c',
                    borderColor: '#1e293b',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        title: (items) => `Time: ${items[0].label}`,
                        label: (item) => `NIFTY 50: ₹${item.raw.toLocaleString('en-IN', { minimumFractionDigits: 2 })}`
                    }
                }
            },
            scales: {
                x: { display: false },
                y: { display: false }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    // Animate the chart data periodically
    setInterval(() => {
        updateHeroTickers();
    }, 3000);
}

/* ============================================
   DASHBOARD CHART
   ============================================ */
let dashboardChartInstance = null;

function initDashboardChart() {
    const ctx = document.getElementById('dashboardChart');
    if (!ctx) return;

    createDashboardChart('1D', 'line');

    // Tab click handlers
    const tabs = document.querySelectorAll('.chart-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const period = tab.dataset.period;
            const activeType = document.querySelector('.type-btn.active').dataset.type;
            createDashboardChart(period, activeType);
        });
    });

    // Chart type handlers
    const typeBtns = document.querySelectorAll('.type-btn');
    typeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            typeBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const type = btn.dataset.type;
            const activePeriod = document.querySelector('.chart-tab.active').dataset.period;
            createDashboardChart(activePeriod, type);
        });
    });
}

function createDashboardChart(period, type) {
    const ctx = document.getElementById('dashboardChart');
    if (!ctx) return;

    if (dashboardChartInstance) {
        dashboardChartInstance.destroy();
    }

    const periodConfig = {
        '1D': { points: 78, min: 22200, max: 22600, timeFormat: 'HH:mm' },
        '1W': { points: 35, min: 21800, max: 22800, timeFormat: 'ddd' },
        '1M': { points: 22, min: 21000, max: 23000, timeFormat: 'DD MMM' },
        '3M': { points: 60, min: 20000, max: 23500, timeFormat: 'DD MMM' },
        '1Y': { points: 52, min: 18000, max: 24000, timeFormat: 'MMM' },
        '5Y': { points: 60, min: 10000, max: 24000, timeFormat: 'YYYY' }
    };

    const config = periodConfig[period] || periodConfig['1D'];
    const dataPoints = generateStockData(config.points, config.min, config.max);
    const labels = generatePeriodLabels(period, config.points);

    const context = ctx.getContext('2d');
    const gradient = context.createLinearGradient(0, 0, 0, 350);
    gradient.addColorStop(0, 'rgba(0, 208, 156, 0.2)');
    gradient.addColorStop(1, 'rgba(0, 208, 156, 0.0)');

    let chartType = 'line';
    let dataset = {};

    if (type === 'candle') {
        // For candlestick simulation, we use bar chart
        chartType = 'bar';
        const candleData = generateCandleData(config.points, config.min, config.max);
        dataset = {
            data: candleData.map(c => c.close - c.open),
            backgroundColor: candleData.map(c => c.close > c.open ? 'rgba(0, 208, 156, 0.8)' : 'rgba(255, 82, 82, 0.8)'),
            borderColor: candleData.map(c => c.close > c.open ? '#00d09c' : '#ff5252'),
            borderWidth: 1,
            borderRadius: 2,
        };
    } else if (type === 'area') {
        dataset = {
            data: dataPoints,
            borderColor: '#5367ff',
            borderWidth: 2,
            backgroundColor: gradient,
            fill: true,
            tension: 0.3,
            pointRadius: 0,
            pointHoverRadius: 5,
            pointHoverBackgroundColor: '#5367ff',
        };
    } else {
        dataset = {
            data: dataPoints,
            borderColor: '#00d09c',
            borderWidth: 2,
            backgroundColor: 'transparent',
            fill: false,
            tension: 0.1,
            pointRadius: 0,
            pointHoverRadius: 5,
            pointHoverBackgroundColor: '#00d09c',
        };
    }

    dashboardChartInstance = new Chart(ctx, {
        type: chartType,
        data: {
            labels: labels,
            datasets: [dataset]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 800,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a2332',
                    titleColor: '#8892a4',
                    bodyColor: '#fff',
                    borderColor: '#1e293b',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false,
                    callbacks: {
                        label: (item) => {
                            if (type === 'candle') {
                                return `Change: ${item.raw > 0 ? '+' : ''}${item.raw.toFixed(2)}`;
                            }
                            return `₹${item.raw.toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(30, 41, 59, 0.5)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#5a6474',
                        font: { size: 10 },
                        maxTicksLimit: 8
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(30, 41, 59, 0.5)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#5a6474',
                        font: { size: 10 },
                        callback: (value) => {
                            if (type === 'candle') return value.toFixed(0);
                            return '₹' + (value / 1000).toFixed(1) + 'K';
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

/* ============================================
   FEATURE CHART
   ============================================ */
function initFeatureChart() {
    const ctx = document.getElementById('featureChart1');
    if (!ctx) return;

    const context = ctx.getContext('2d');
    const gradient1 = context.createLinearGradient(0, 0, 0, 250);
    gradient1.addColorStop(0, 'rgba(0, 208, 156, 0.3)');
    gradient1.addColorStop(1, 'rgba(0, 208, 156, 0.0)');

    const gradient2 = context.createLinearGradient(0, 0, 0, 250);
    gradient2.addColorStop(0, 'rgba(83, 103, 255, 0.3)');
    gradient2.addColorStop(1, 'rgba(83, 103, 255, 0.0)');

    const data1 = generateStockData(30, 100, 180);
    const data2 = generateStockData(30, 80, 160);
    const labels = Array.from({ length: 30 }, (_, i) => `${i + 1}`);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Portfolio',
                    data: data1,
                    borderColor: '#00d09c',
                    borderWidth: 2,
                    backgroundColor: gradient1,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                },
                {
                    label: 'Benchmark',
                    data: data2,
                    borderColor: '#5367ff',
                    borderWidth: 2,
                    backgroundColor: gradient2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        color: '#8892a4',
                        font: { size: 11 },
                        boxWidth: 12,
                        boxHeight: 2,
                        usePointStyle: true,
                        pointStyle: 'line'
                    }
                },
                tooltip: {
                    backgroundColor: '#1a2332',
                    titleColor: '#8892a4',
                    bodyColor: '#fff',
                    borderColor: '#1e293b',
                    borderWidth: 1,
                }
            },
            scales: {
                x: { display: false },
                y: { display: false }
            }
        }
    });
}

/* ============================================
   WATCHLIST
   ============================================ */
function initWatchlist() {
    const watchlistContainer = document.getElementById('watchlistItems');
    if (!watchlistContainer) return;

    const stocks = [
        { name: 'RELIANCE', price: 2856.40, change: 2.3 },
        { name: 'TCS', price: 3945.20, change: 1.1 },
        { name: 'HDFC BANK', price: 1678.90, change: -0.5 },
        { name: 'INFOSYS', price: 1523.75, change: 1.8 },
        { name: 'ITC', price: 456.30, change: 0.9 },
        { name: 'TATAMOTORS', price: 987.45, change: 2.5 },
        { name: 'SBIN', price: 756.20, change: -0.3 },
        { name: 'ADANIENT', price: 3123.80, change: 1.7 },
    ];

    watchlistContainer.innerHTML = stocks.map(stock => `
        <div class="watchlist-item">
            <span class="stock-name">${stock.name}</span>
            <div class="stock-price">
                <div class="price">₹${stock.price.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</div>
                <div class="change ${stock.change >= 0 ? 'positive' : 'negative'}">
                    ${stock.change >= 0 ? '+' : ''}${stock.change.toFixed(2)}%
                </div>
            </div>
        </div>
    `).join('');

    // Simulate real-time updates
    setInterval(() => {
        const items = watchlistContainer.querySelectorAll('.watchlist-item');
        items.forEach((item, idx) => {
            const changeEl = item.querySelector('.change');
            const priceEl = item.querySelector('.price');
            const currentPrice = stocks[idx].price;
            const variation = (Math.random() - 0.5) * 10;
            stocks[idx].price = currentPrice + variation;
            stocks[idx].change = ((variation / currentPrice) * 100) + stocks[idx].change * 0.9;
            
            priceEl.textContent = `₹${stocks[idx].price.toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
            changeEl.textContent = `${stocks[idx].change >= 0 ? '+' : ''}${stocks[idx].change.toFixed(2)}%`;
            changeEl.className = `change ${stocks[idx].change >= 0 ? 'positive' : 'negative'}`;
        });
    }, 5000);
}

/* ============================================
   BROKERAGE CALCULATOR
   ============================================ */
function initCalculator() {
    const tradeType = document.getElementById('tradeType');
    const buyPrice = document.getElementById('buyPrice');
    const sellPrice = document.getElementById('sellPrice');
    const quantity = document.getElementById('quantity');

    if (!tradeType || !buyPrice || !sellPrice || !quantity) return;

    const calculate = () => {
        const type = tradeType.value;
        const buy = parseFloat(buyPrice.value) || 0;
        const sell = parseFloat(sellPrice.value) || 0;
        const qty = parseInt(quantity.value) || 0;

        const buyTurnover = buy * qty;
        const sellTurnover = sell * qty;
        const totalTurnover = buyTurnover + sellTurnover;

        let brokerage = 0;
        let sttRate = 0;
        let exchangeRate = 0.0000345;

        switch (type) {
            case 'delivery':
                brokerage = 0;
                sttRate = 0.001; // 0.1% on both sides
                break;
            case 'intraday':
                brokerage = Math.min(20, buyTurnover * 0.0003) + Math.min(20, sellTurnover * 0.0003);
                sttRate = 0.00025; // 0.025% on sell side
                break;
            case 'futures':
                brokerage = 40; // ₹20 each side
                sttRate = 0.000125; // 0.0125% on sell side
                break;
            case 'options':
                brokerage = 40;
                sttRate = 0.000625; // 0.0625% on sell side (premium)
                break;
        }

        const stt = type === 'delivery' ? totalTurnover * sttRate : sellTurnover * sttRate;
        const exchangeCharges = totalTurnover * exchangeRate;
        const sebiCharges = totalTurnover * 0.000001;
        const stampDuty = buyTurnover * 0.00003;
        const gst = (brokerage + exchangeCharges + sebiCharges) * 0.18;

        const totalCharges = brokerage + stt + exchangeCharges + gst + sebiCharges + stampDuty;
        const grossPnl = (sell - buy) * qty;
        const netPnl = grossPnl - totalCharges;

        document.getElementById('turnover').textContent = `₹${totalTurnover.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        document.getElementById('brokerage').textContent = `₹${brokerage.toFixed(2)}`;
        document.getElementById('stt').textContent = `₹${stt.toFixed(2)}`;
        document.getElementById('exchangeCharges').textContent = `₹${exchangeCharges.toFixed(2)}`;
        document.getElementById('gst').textContent = `₹${gst.toFixed(2)}`;
        
        const netPnlEl = document.getElementById('netPnl');
        netPnlEl.textContent = `${netPnl >= 0 ? '+' : ''}₹${Math.abs(netPnl).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        netPnlEl.className = netPnl >= 0 ? 'positive' : 'negative';
    };

    [tradeType, buyPrice, sellPrice, quantity].forEach(el => {
        el.addEventListener('input', calculate);
        el.addEventListener('change', calculate);
    });

    calculate();
}

/* ============================================
   ANIMATED COUNTERS
   ============================================ */
function initCounters() {
    const counters = document.querySelectorAll('[data-target]');
    
    const observerOptions = {
        threshold: 0.5,
        rootMargin: '0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target;
                animateCounter(counter);
                observer.unobserve(counter);
            }
        });
    }, observerOptions);

    counters.forEach(counter => observer.observe(counter));
}

function animateCounter(element) {
    const target = parseFloat(element.dataset.target);
    const duration = 2000;
    const start = performance.now();
    const startValue = 0;

    function update(currentTime) {
        const elapsed = currentTime - start;
        const progress = Math.min(elapsed / duration, 1);
        const eased = easeOutExpo(progress);
        const current = startValue + (target - startValue) * eased;

        if (target >= 100000) {
            element.textContent = Math.floor(current).toLocaleString('en-IN') + '+';
        } else if (target >= 1000) {
            element.textContent = Math.floor(current).toLocaleString('en-IN') + '+';
        } else if (target < 100 && target % 1 !== 0) {
            element.textContent = current.toFixed(1) + '%';
        } else {
            element.textContent = Math.floor(current) + '+';
        }

        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }

    requestAnimationFrame(update);
}

function easeOutExpo(t) {
    return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
}

/* ============================================
   SCROLL REVEAL
   ============================================ */
function initScrollReveal() {
    const revealElements = document.querySelectorAll('.trading-card, .feature-card, .pricing-card, .testimonial-card, .about-stat-card');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, index * 100);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

    revealElements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
}

/* ============================================
   MARQUEE / TICKER DUPLICATION
   ============================================ */
function initMarquee() {
    const track = document.getElementById('tickerTrack');
    if (!track) return;

    const content = track.querySelector('.ticker-content');
    if (!content) return;

    // Duplicate content for seamless loop
    const clone = content.cloneNode(true);
    track.appendChild(clone);
}

/* ============================================
   HELPER FUNCTIONS
   ============================================ */
function generateStockData(points, min, max) {
    const data = [];
    let current = min + (max - min) * 0.3;
    const volatility = (max - min) * 0.03;

    for (let i = 0; i < points; i++) {
        const trend = Math.sin(i / (points * 0.3)) * (max - min) * 0.2;
        const noise = (Math.random() - 0.5) * volatility * 2;
        current = current + noise + trend * 0.05;
        current = Math.max(min, Math.min(max, current));
        data.push(parseFloat(current.toFixed(2)));
    }

    return data;
}

function generateCandleData(points, min, max) {
    const data = [];
    let current = (min + max) / 2;

    for (let i = 0; i < points; i++) {
        const change = (Math.random() - 0.48) * (max - min) * 0.05;
        const open = current;
        const close = current + change;
        const high = Math.max(open, close) + Math.random() * Math.abs(change);
        const low = Math.min(open, close) - Math.random() * Math.abs(change);
        current = close;

        data.push({ open, close, high, low });
    }

    return data;
}

function generateTimeLabels(count) {
    const labels = [];
    const startHour = 9;
    const startMin = 15;

    for (let i = 0; i < count; i++) {
        const totalMins = startMin + i * 5;
        const hours = startHour + Math.floor(totalMins / 60);
        const mins = totalMins % 60;
        labels.push(`${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`);
    }

    return labels;
}

function generatePeriodLabels(period, count) {
    const labels = [];
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];

    switch (period) {
        case '1D':
            for (let i = 0; i < count; i++) {
                const totalMins = 15 + i * 5;
                const hours = 9 + Math.floor(totalMins / 60);
                const mins = totalMins % 60;
                labels.push(`${hours}:${mins.toString().padStart(2, '0')}`);
            }
            break;
        case '1W':
            for (let i = 0; i < count; i++) {
                labels.push(days[i % 5] + ' ' + (9 + Math.floor(i / 5)));
            }
            break;
        case '1M':
            for (let i = 0; i < count; i++) {
                labels.push(`${i + 1} Jun`);
            }
            break;
        case '3M':
            for (let i = 0; i < count; i++) {
                const monthIdx = Math.floor(i / 20) + 3;
                labels.push(`${(i % 20) + 1} ${months[monthIdx]}`);
            }
            break;
        case '1Y':
            for (let i = 0; i < count; i++) {
                labels.push(months[i % 12] + ' ' + (2023 + Math.floor(i / 12)));
            }
            break;
        case '5Y':
            for (let i = 0; i < count; i++) {
                labels.push(months[i % 12] + ' ' + (2020 + Math.floor(i / 12)));
            }
            break;
        default:
            for (let i = 0; i < count; i++) {
                labels.push(`${i + 1}`);
            }
    }

    return labels;
}

function updateHeroTickers() {
    const tickers = document.querySelectorAll('.stock-ticker .ticker-item');
    tickers.forEach(ticker => {
        const valueEl = ticker.querySelector('.ticker-value');
        const changeEl = ticker.querySelector('.ticker-change');
        
        if (valueEl && changeEl) {
            const currentValue = parseFloat(valueEl.textContent.replace(/,/g, ''));
            const variation = (Math.random() - 0.5) * currentValue * 0.002;
            const newValue = currentValue + variation;
            const changePercent = (variation / currentValue * 100);
            
            valueEl.textContent = newValue.toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            const totalChange = parseFloat(changeEl.textContent) + changePercent;
            changeEl.textContent = `${totalChange >= 0 ? '+' : ''}${totalChange.toFixed(2)}%`;
            
            ticker.className = `ticker-item ${totalChange >= 0 ? 'positive' : 'negative'}`;
        }
    });
}

/* ============================================
   SMOOTH SCROLLING FOR NAV LINKS
   ============================================ */
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href === '#') return;
        
        e.preventDefault();
        const target = document.querySelector(href);
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
