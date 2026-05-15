/* ==========================================================
   Store - Product Listing, Cart, Checkout
   ========================================================== */

const storeState = {
    allProducts: [],
    filteredProducts: [],
    cart: JSON.parse(localStorage.getItem('vastu_cart') || '[]'),
    filters: { categories: [], priceRanges: [], ratings: [] },
    sort: 'featured',
    search: ''
};

// Sample fallback products (used if backend not available)
const FALLBACK_PRODUCTS = [
    { id: 1, name: 'Crystal Vastu Pyramid', category: 'pyramid', price: 899, original_price: 1499, rating: 4.7, reviews: 234, icon: 'gem', description: 'Energize your Brahmasthan zone with this premium 9-pyramid set. Made of natural crystal, ideal for the central area of any home or office.', badge: 'Bestseller' },
    { id: 2, name: 'Brass Vastu Tortoise', category: 'brass', price: 599, original_price: 999, rating: 4.6, reviews: 187, icon: 'dharmachakra', description: 'Symbol of stability and longevity. Place in north for career growth and family wellness.' },
    { id: 3, name: 'Himalayan Salt Lamp', category: 'lamp', price: 1299, original_price: 2499, rating: 4.8, reviews: 412, icon: 'lightbulb', description: 'Hand-carved natural pink salt from Himalayas. Purifies negative energy and improves air quality.', badge: 'New' },
    { id: 4, name: 'Copper Vastu Strip Set', category: 'copper', price: 499, original_price: 899, rating: 4.5, reviews: 156, icon: 'minus', description: 'Set of 4 pure copper strips to block negative energy flow at thresholds and corners.' },
    { id: 5, name: 'Sphatik Shree Yantra', category: 'yantra', price: 1799, original_price: 2999, rating: 4.9, reviews: 567, icon: 'star-of-life', description: 'Sacred geometry crystal yantra for wealth, prosperity, and abundance. Energized by Vedic priests.', badge: 'Premium' },
    { id: 6, name: '5 Mukhi Rudraksha Mala', category: 'rudraksha', price: 1199, original_price: 1999, rating: 4.7, reviews: 298, icon: 'circle-notch', description: 'Authentic 108-bead Rudraksha mala for meditation and spiritual protection.' },
    { id: 7, name: 'Money Plant (Pothos)', category: 'plant', price: 299, original_price: 499, rating: 4.4, reviews: 134, icon: 'seedling', description: 'Auspicious Vastu plant for prosperity. Place in southeast direction for wealth.' },
    { id: 8, name: 'Amethyst Crystal Cluster', category: 'crystal', price: 2499, original_price: 4499, rating: 4.8, reviews: 198, icon: 'gem', description: 'Natural Brazilian amethyst cluster. Ideal for bedroom for peaceful sleep and calm mind.' },
    { id: 9, name: 'Brass Laughing Buddha', category: 'brass', price: 799, original_price: 1299, rating: 4.6, reviews: 245, icon: 'smile', description: 'Symbol of happiness and abundance. Place at entrance facing the front door.' },
    { id: 10, name: 'Black Tourmaline Bracelet', category: 'crystal', price: 699, original_price: 1199, rating: 4.5, reviews: 167, icon: 'circle', description: 'Powerful protection stone. Wards off negative energies and electromagnetic radiation.' },
    { id: 11, name: 'Kuber Yantra (Gold Plated)', category: 'yantra', price: 1499, original_price: 2499, rating: 4.7, reviews: 389, icon: 'coins', description: 'Lord Kuber yantra for wealth and financial stability. Best placed in north zone.' },
    { id: 12, name: 'Bamboo Plant (8 Stalks)', category: 'plant', price: 449, original_price: 799, rating: 4.5, reviews: 178, icon: 'tree', description: 'Lucky bamboo for wealth and good fortune. 8 stalks symbolize abundance.', badge: 'Lucky' }
];

// ===== Initialize =====
document.addEventListener('DOMContentLoaded', async () => {
    await loadProducts();
    updateCartUI();

    // Cart icon
    document.getElementById('cartIcon').addEventListener('click', (e) => {
        e.preventDefault();
        openCart();
    });

    // Search
    document.getElementById('searchInput').addEventListener('input', (e) => {
        storeState.search = e.target.value.toLowerCase();
        applyFilters();
    });

    // Sort
    document.getElementById('sortBy').addEventListener('change', (e) => {
        storeState.sort = e.target.value;
        applyFilters();
    });

    // Filter checkboxes
    document.querySelectorAll('.store-sidebar input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', updateFilters);
    });

    // Clear filters
    document.getElementById('clearFilters').addEventListener('click', () => {
        document.querySelectorAll('.store-sidebar input[type="checkbox"]').forEach(cb => cb.checked = false);
        storeState.filters = { categories: [], priceRanges: [], ratings: [] };
        applyFilters();
    });

    // Checkout
    document.getElementById('checkoutBtn').addEventListener('click', initiateCheckout);
});

async function loadProducts() {
    try {
        const res = await fetch('../../backend/api/products.php');
        const data = await res.json();
        if (data.success && data.products && data.products.length) {
            storeState.allProducts = data.products;
        } else {
            storeState.allProducts = FALLBACK_PRODUCTS;
        }
    } catch (e) {
        console.warn('Using fallback products');
        storeState.allProducts = FALLBACK_PRODUCTS;
    }
    document.getElementById('loadingIndicator').style.display = 'none';
    storeState.filteredProducts = [...storeState.allProducts];
    renderProducts();
}

function updateFilters() {
    storeState.filters = { categories: [], priceRanges: [], ratings: [] };
    document.querySelectorAll('.store-sidebar input[type="checkbox"]:checked').forEach(cb => {
        const type = cb.dataset.filter;
        if (type === 'category') storeState.filters.categories.push(cb.value);
        if (type === 'price') storeState.filters.priceRanges.push(cb.value);
        if (type === 'rating') storeState.filters.ratings.push(parseFloat(cb.value));
    });
    applyFilters();
}

function applyFilters() {
    let filtered = [...storeState.allProducts];

    if (storeState.search) {
        filtered = filtered.filter(p =>
            p.name.toLowerCase().includes(storeState.search) ||
            (p.description || '').toLowerCase().includes(storeState.search) ||
            p.category.toLowerCase().includes(storeState.search)
        );
    }

    if (storeState.filters.categories.length) {
        filtered = filtered.filter(p => storeState.filters.categories.includes(p.category));
    }

    if (storeState.filters.priceRanges.length) {
        filtered = filtered.filter(p => {
            return storeState.filters.priceRanges.some(range => {
                if (range === '5000+') return p.price >= 5000;
                const [min, max] = range.split('-').map(Number);
                return p.price >= min && p.price <= max;
            });
        });
    }

    if (storeState.filters.ratings.length) {
        const minRating = Math.min(...storeState.filters.ratings);
        filtered = filtered.filter(p => p.rating >= minRating);
    }

    // Sort
    switch (storeState.sort) {
        case 'price_low': filtered.sort((a, b) => a.price - b.price); break;
        case 'price_high': filtered.sort((a, b) => b.price - a.price); break;
        case 'rating': filtered.sort((a, b) => b.rating - a.rating); break;
        case 'newest': filtered.reverse(); break;
    }

    storeState.filteredProducts = filtered;
    renderProducts();
}

function renderProducts() {
    const grid = document.getElementById('productsGrid');
    const emptyState = document.getElementById('emptyState');
    const count = document.getElementById('resultCount');

    count.textContent = storeState.filteredProducts.length;

    if (!storeState.filteredProducts.length) {
        grid.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    emptyState.style.display = 'none';

    grid.innerHTML = storeState.filteredProducts.map(p => `
        <div class="store-product">
            ${p.badge ? `<span class="product-badge">${p.badge}</span>` : ''}
            <div class="product-image" onclick="openProductModal(${p.id})" style="cursor:pointer;">
                <i class="fas fa-${p.icon || 'gem'}"></i>
            </div>
            <div class="product-info">
                <h4 onclick="openProductModal(${p.id})" style="cursor:pointer;">${p.name}</h4>
                <div class="product-rating">
                    <span class="stars-mini">${'★'.repeat(Math.floor(p.rating))}${'☆'.repeat(5 - Math.floor(p.rating))}</span>
                    <span>${p.rating} (${p.reviews || 0})</span>
                </div>
                <div class="product-price">₹${p.price}${p.original_price ? ` <span>₹${p.original_price}</span>` : ''}</div>
                <div class="product-actions">
                    <button class="btn btn-sm btn-outline" onclick="openProductModal(${p.id})">View</button>
                    <button class="btn btn-sm btn-primary" onclick="addToCart(${p.id})"><i class="fas fa-cart-plus"></i> Add</button>
                </div>
            </div>
        </div>
    `).join('');
}

window.openProductModal = function(id) {
    const p = storeState.allProducts.find(x => x.id === id);
    if (!p) return;
    const modal = document.getElementById('productModal');
    const content = document.getElementById('productModalContent');
    content.innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1.2fr;gap:32px;">
            <div style="background:linear-gradient(135deg,var(--cream) 0%,var(--off-white) 100%);height:300px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;font-size:120px;color:var(--gold);">
                <i class="fas fa-${p.icon || 'gem'}"></i>
            </div>
            <div>
                ${p.badge ? `<span style="display:inline-block;background:var(--gold-gradient);color:var(--dark);padding:4px 12px;border-radius:100px;font-size:12px;font-weight:700;margin-bottom:8px;">${p.badge}</span>` : ''}
                <h2 style="font-size:28px;margin-bottom:8px;">${p.name}</h2>
                <div style="margin-bottom:12px;display:flex;align-items:center;gap:8px;">
                    <span style="color:var(--gold);">${'★'.repeat(Math.floor(p.rating))}${'☆'.repeat(5 - Math.floor(p.rating))}</span>
                    <span style="color:var(--gray-600);font-size:14px;">${p.rating} (${p.reviews || 0} reviews)</span>
                </div>
                <p style="color:var(--gray-600);line-height:1.7;margin-bottom:20px;">${p.description || ''}</p>
                <div style="font-family:var(--font-display);font-size:36px;font-weight:700;color:var(--gold-dark);margin-bottom:20px;">
                    ₹${p.price}${p.original_price ? ` <span style="font-size:18px;color:var(--gray-400);text-decoration:line-through;">₹${p.original_price}</span>` : ''}
                </div>
                <div style="display:flex;gap:12px;margin-bottom:20px;">
                    <button class="btn btn-primary btn-lg" onclick="addToCart(${p.id});closeProductModal();" style="flex:1;">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                    <button class="btn btn-dark btn-lg" onclick="buyNow(${p.id})">Buy Now</button>
                </div>
                <div style="background:var(--off-white);padding:16px;border-radius:var(--radius-md);font-size:13px;color:var(--gray-600);">
                    <p><i class="fas fa-truck" style="color:var(--gold);margin-right:6px;"></i> Free shipping on orders above ₹999</p>
                    <p><i class="fas fa-shield-alt" style="color:var(--gold);margin-right:6px;"></i> 100% authentic, energized products</p>
                    <p><i class="fas fa-undo" style="color:var(--gold);margin-right:6px;"></i> 7-day return policy</p>
                </div>
            </div>
        </div>
    `;
    modal.style.display = 'flex';
};

window.closeProductModal = function() {
    document.getElementById('productModal').style.display = 'none';
};

// ===== Cart =====
window.addToCart = function(id) {
    const p = storeState.allProducts.find(x => x.id === id);
    if (!p) return;
    const existing = storeState.cart.find(i => i.id === id);
    if (existing) {
        existing.qty += 1;
    } else {
        storeState.cart.push({ id: p.id, name: p.name, price: p.price, icon: p.icon, qty: 1 });
    }
    saveCart();
    updateCartUI();
    showToast(`${p.name} added to cart!`, 'success');
};

window.removeFromCart = function(id) {
    storeState.cart = storeState.cart.filter(i => i.id !== id);
    saveCart();
    updateCartUI();
};

window.updateQty = function(id, delta) {
    const item = storeState.cart.find(i => i.id === id);
    if (!item) return;
    item.qty = Math.max(1, item.qty + delta);
    saveCart();
    updateCartUI();
};

window.buyNow = function(id) {
    addToCart(id);
    closeProductModal();
    openCart();
};

window.openCart = function() {
    document.getElementById('cartDrawer').style.right = '0';
    document.getElementById('cartBackdrop').style.display = 'block';
};
window.closeCart = function() {
    document.getElementById('cartDrawer').style.right = '-420px';
    document.getElementById('cartBackdrop').style.display = 'none';
};

function saveCart() {
    localStorage.setItem('vastu_cart', JSON.stringify(storeState.cart));
}

function updateCartUI() {
    const count = storeState.cart.reduce((sum, i) => sum + i.qty, 0);
    document.getElementById('cartCount').textContent = count;
    const items = document.getElementById('cartItems');
    const total = storeState.cart.reduce((sum, i) => sum + i.price * i.qty, 0);
    document.getElementById('cartTotal').textContent = '₹' + total;

    if (!storeState.cart.length) {
        items.innerHTML = `
            <div style="text-align:center;padding:40px 20px;color:var(--gray-600);">
                <i class="fas fa-shopping-cart" style="font-size:48px;color:var(--gray-400);margin-bottom:16px;"></i>
                <h3 style="margin-bottom:8px;">Your cart is empty</h3>
                <p style="font-size:14px;">Add some Vastu remedies to get started!</p>
            </div>
        `;
        document.getElementById('cartFooter').style.display = 'none';
        return;
    }

    document.getElementById('cartFooter').style.display = 'block';
    items.innerHTML = storeState.cart.map(i => `
        <div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid rgba(10,14,39,0.05);">
            <div style="width:60px;height:60px;background:linear-gradient(135deg,var(--cream) 0%,var(--off-white) 100%);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--gold);flex-shrink:0;">
                <i class="fas fa-${i.icon || 'gem'}"></i>
            </div>
            <div style="flex:1;">
                <h4 style="font-size:14px;font-family:var(--font-body);font-weight:600;">${i.name}</h4>
                <div style="font-size:13px;color:var(--gold-dark);font-weight:700;">₹${i.price}</div>
                <div style="display:flex;align-items:center;gap:8px;margin-top:6px;">
                    <button onclick="updateQty(${i.id}, -1)" style="width:24px;height:24px;border:1px solid var(--gray-200);border-radius:4px;background:var(--white);">−</button>
                    <span style="font-weight:600;">${i.qty}</span>
                    <button onclick="updateQty(${i.id}, 1)" style="width:24px;height:24px;border:1px solid var(--gray-200);border-radius:4px;background:var(--white);">+</button>
                    <button onclick="removeFromCart(${i.id})" style="margin-left:auto;color:var(--danger);font-size:16px;background:none;border:none;"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        </div>
    `).join('');
}

async function initiateCheckout() {
    if (!storeState.cart.length) return;

    const total = storeState.cart.reduce((sum, i) => sum + i.price * i.qty, 0);
    const btn = document.getElementById('checkoutBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

    try {
        const res = await fetch('../../backend/api/order_create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: storeState.cart, amount: total })
        });
        const data = await res.json();

        if (!data.success) throw new Error(data.message || 'Checkout failed');

        const options = {
            key: data.razorpay_key,
            amount: data.amount,
            currency: 'INR',
            name: 'VastuKundali Store',
            description: `${storeState.cart.length} item(s)`,
            order_id: data.order_id,
            handler: async function(response) {
                const verifyRes = await fetch('../../backend/api/order_verify.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_signature: response.razorpay_signature,
                        order_id: data.internal_order_id
                    })
                });
                const verify = await verifyRes.json();
                if (verify.success) {
                    showToast('Order placed successfully! Thank you.', 'success');
                    storeState.cart = [];
                    saveCart();
                    updateCartUI();
                    closeCart();
                    setTimeout(() => window.location.href = `dashboard.html?order=${data.internal_order_id}`, 2000);
                } else {
                    showToast('Payment verification failed', 'error');
                }
            },
            theme: { color: '#D4AF37' },
            modal: {
                ondismiss: () => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-lock"></i> Checkout Securely';
                }
            }
        };

        if (data.razorpay_key && data.razorpay_key !== 'DEMO_MODE') {
            const rzp = new Razorpay(options);
            rzp.open();
        } else {
            // Demo mode
            showToast('Demo mode: order placed!', 'success');
            storeState.cart = [];
            saveCart();
            updateCartUI();
            closeCart();
            setTimeout(() => btn.innerHTML = '<i class="fas fa-lock"></i> Checkout Securely', 100);
            btn.disabled = false;
        }
    } catch (err) {
        showToast(err.message || 'Checkout failed', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-lock"></i> Checkout Securely';
    }
}

// Click outside modal to close
document.getElementById('productModal').addEventListener('click', (e) => {
    if (e.target.id === 'productModal') closeProductModal();
});
