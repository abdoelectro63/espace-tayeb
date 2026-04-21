/**
 * AJAX add-to-cart + fly animation to basket icon.
 */

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

/**
 * Convert absolute app URLs to same-origin relative paths.
 * This avoids cross-origin failures when shared tunnel host changes.
 *
 * @param {string} rawUrl
 * @returns {string}
 */
function sameOriginPath(rawUrl) {
    if (!rawUrl) {
        return '/';
    }

    try {
        const parsed = new URL(rawUrl, window.location.origin);
        return `${parsed.pathname}${parsed.search}${parsed.hash}`;
    } catch {
        return rawUrl;
    }
}

/**
 * @param {Event} e
 * @returns {Element | null}
 */
function eventTargetElement(e) {
    const t = e.target;
    if (t instanceof Element) {
        return t;
    }
    if (t && t.parentNode instanceof Element) {
        return t.parentNode;
    }

    return null;
}

/**
 * @param {HTMLElement} sourceElement
 * @param {string} imageUrl
 * @returns {Promise<void>}
 */
function flyToCart(sourceElement, imageUrl) {
    const target = document.querySelector('[data-cart-fly-target]');
    if (!target || !imageUrl) {
        return Promise.resolve();
    }

    const targetRect = target.getBoundingClientRect();
    const sourceRect = sourceElement.getBoundingClientRect();
    const size = 56;
    const startX = sourceRect.left + sourceRect.width / 2 - size / 2;
    const startY = sourceRect.top + sourceRect.height / 2 - size / 2;

    const ghost = document.createElement('img');
    ghost.src = imageUrl;
    ghost.decoding = 'async';
    ghost.alt = '';
    ghost.style.cssText = [
        'position:fixed',
        `left:${startX}px`,
        `top:${startY}px`,
        `width:${size}px`,
        `height:${size}px`,
        'object-fit:cover',
        'border-radius:12px',
        'z-index:9999',
        'pointer-events:none',
        'box-shadow:0 8px 28px rgba(0,0,0,.14)',
    ].join(';');

    document.body.appendChild(ghost);

    const endX = targetRect.left + targetRect.width / 2 - size / 2;
    const endY = targetRect.top + targetRect.height / 2 - size / 2;
    const dx = endX - startX;
    const dy = endY - startY;

    const animation = ghost.animate(
        [
            { transform: 'translate(0,0) scale(1)', opacity: 1 },
            { transform: `translate(${dx}px, ${dy}px) scale(0.32)`, opacity: 0.9 },
        ],
        { duration: 720, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' },
    );

    return animation.finished.then(() => {
        ghost.remove();
    });
}

function bumpCartIcon() {
    const el = document.querySelector('[data-cart-fly-target]');
    if (!el) {
        return;
    }
    el.classList.add('scale-110', 'ring-2', 'ring-orange-400/80');
    window.setTimeout(() => {
        el.classList.remove('scale-110', 'ring-2', 'ring-orange-400/80');
    }, 420);
}

/**
 * @param {number|string} count
 */
function updateCartBadge(count) {
    const badge = document.getElementById('store-cart-badge');
    const val = document.getElementById('store-cart-badge-value');
    if (!badge || !val) {
        return;
    }
    const n = Math.max(0, Number.parseInt(String(count), 10) || 0);
    val.textContent = n > 99 ? '99+' : String(n);
    badge.classList.toggle('hidden', n === 0);
}

/**
 * @param {string} message
 * @param {boolean} isError
 */
function showCartToast(message, isError = false) {
    const t = document.getElementById('cart-toast');
    if (!t) {
        return;
    }
    t.textContent = message;
    t.classList.remove('hidden');
    t.classList.toggle('bg-rose-600', isError);
    t.classList.toggle('bg-zinc-900', !isError);
    t.classList.remove('opacity-0', 'translate-y-2');
    t.classList.add('opacity-100', 'translate-y-0');

    window.clearTimeout(showCartToast._timer);
    showCartToast._timer = window.setTimeout(() => {
        t.classList.add('opacity-0', 'translate-y-2');
        window.setTimeout(() => t.classList.add('hidden'), 320);
    }, 2800);
}

/**
 * @param {import('axios').AxiosError} err
 */
function extractErrorMessage(err) {
    const data = err.response?.data;
    if (data?.errors) {
        const first = Object.values(data.errors).flat()[0];
        if (typeof first === 'string') {
            return first;
        }
    }
    if (typeof data?.message === 'string') {
        return data.message;
    }
    const cartErr = data?.errors?.cart;
    if (Array.isArray(cartErr) && cartErr[0]) {
        return cartErr[0];
    }
    return 'تعذر إضافة المنتج. حاول مرة أخرى.';
}

/**
 * @param {number} productId
 * @param {number} next
 * @param {number|null|undefined} productVariationId
 */
function patchCartLineQuantity(productId, next, productVariationId) {
    const token = getCsrfToken();

    const body = { quantity: next };
    if (productVariationId !== undefined && productVariationId !== null && productVariationId !== '') {
        body.product_variation_id = productVariationId;
    }

    return window.axios.patch(
        `/cart/${productId}`,
        body,
        {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            },
        },
    );
}

/**
 * @param {object} data
 */
function syncCartPageFromApi(data) {
    const container = document.getElementById('cart-page-lines');
    const subtotalEl = document.getElementById('cart-page-subtotal');
    if (!container || !data.items) {
        return;
    }

    if (data.cart_count !== undefined) {
        updateCartBadge(data.cart_count);
    }

    if (subtotalEl && data.subtotal !== undefined) {
        subtotalEl.innerHTML = `${Number(data.subtotal).toFixed(2)} <span class="text-base font-semibold text-zinc-500">MAD</span>`;
    }

    const shipEl = document.getElementById('cart-page-shipping');
    const grandEl = document.getElementById('cart-page-grand-total');
    const madSpan = '<span class="text-base font-semibold text-zinc-500">MAD</span>';
    if (shipEl) {
        if (data.requires_paid_shipping === false) {
            shipEl.innerHTML = `0.00 ${madSpan}`;
        } else if (data.shipping_fee != null && data.zone_selected !== false) {
            shipEl.innerHTML = `${Number(data.shipping_fee).toFixed(2)} ${madSpan}`;
        } else if (data.requires_paid_shipping === true) {
            shipEl.textContent = '—';
        }
    }
    if (grandEl && data.grand_total != null && Number.isFinite(Number(data.grand_total))) {
        grandEl.innerHTML = `${Number(data.grand_total).toFixed(2)} ${madSpan}`;
    }

    if (data.items.length === 0) {
        window.location.reload();

        return;
    }

    const incomingKeys = new Set(
        data.items.map((i) => `${i.id}|${i.product_variation_id ?? 0}`),
    );
    container.querySelectorAll('[data-cart-page-line]').forEach((li) => {
        const key = li.getAttribute('data-cart-line-key');
        if (key && !incomingKeys.has(key)) {
            li.remove();
        }
    });

    data.items.forEach((item) => {
        const key = `${item.id}|${item.product_variation_id ?? 0}`;
        const li = container.querySelector(`[data-cart-page-line][data-cart-line-key="${key}"]`);
        if (!li) {
            window.location.reload();

            return;
        }
        li.setAttribute('data-qty', String(item.quantity));
        li.setAttribute('data-stock', String(item.stock));
        const qtySpan = li.querySelector('[data-cart-line-qty]');
        if (qtySpan) {
            qtySpan.textContent = String(item.quantity);
        }
        const lineTotal = li.querySelector('[data-cart-line-total]');
        if (lineTotal) {
            lineTotal.innerHTML = `${Number(item.line_total).toFixed(2)} <span class="text-sm font-semibold text-zinc-500">MAD</span>`;
        }
        const plus = li.querySelector('[data-qty-delta="1"]');
        if (plus) {
            plus.disabled = item.track_stock !== false && item.quantity >= item.stock;
        }
    });
}

function initAddToCartForms() {
    if (typeof window.axios === 'undefined') {
        return;
    }

    const token = getCsrfToken();
    if (token) {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    }

    document.querySelectorAll('form[data-add-to-cart]').forEach((form) => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitter = e.submitter;
            const btn =
                submitter &&
                ((submitter instanceof HTMLButtonElement && submitter.type === 'submit') ||
                    (submitter instanceof HTMLInputElement && submitter.type === 'submit'))
                    ? submitter
                    : form.querySelector('button[type="submit"]');
            const imageUrl = form.getAttribute('data-fly-image') || '';
            const fd = new FormData(form);

            if (btn) {
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
                btn.classList.add('opacity-60', 'pointer-events-none');
            }

            try {
                const res = await window.axios.post(form.action, fd, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const data = res.data ?? {};
                const flySelector = form.getAttribute('data-fly-source-selector');
                const sourceEl =
                    (flySelector && document.querySelector(flySelector)) || btn || form;

                await flyToCart(sourceEl, imageUrl);
                bumpCartIcon();

                if (data.cart_count !== undefined) {
                    updateCartBadge(data.cart_count);
                }
                if (typeof window.openStoreCartDrawer === 'function') {
                    await window.openStoreCartDrawer();
                } else if (data.message) {
                    showCartToast(data.message, false);
                }
            } catch (err) {
                showCartToast(extractErrorMessage(err), true);
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.removeAttribute('aria-busy');
                    btn.classList.remove('opacity-60', 'pointer-events-none');
                }
            }
        });
    });
}

/**
 * Mini-cart drawer (left panel): load lines + open/close.
 */
function formatMoneyMad(value) {
    const n = Number(value);
    if (Number.isNaN(n)) {
        return '0.00 MAD';
    }
    return `${n.toFixed(2)} MAD`;
}

/**
 * @param {object} data
 */
function updateCartDrawerTotalsFromData(data) {
    const subEl = document.getElementById('cart-drawer-subtotal');
    const shipEl = document.getElementById('cart-drawer-shipping');
    const grandEl = document.getElementById('cart-drawer-grand-total');
    const hintEl = document.getElementById('cart-drawer-shipping-hint');
    if (!subEl) {
        return;
    }
    const sub = Number(data.subtotal ?? 0);
    subEl.textContent = formatMoneyMad(sub);
    if (!shipEl || !grandEl) {
        return;
    }

    if (data.requires_paid_shipping === false) {
        shipEl.textContent = formatMoneyMad(0);
        grandEl.textContent = formatMoneyMad(sub);
        if (hintEl) {
            hintEl.textContent = 'جميع المنتجات الحالية بتوصيل مجاني.';
            hintEl.classList.remove('hidden');
        }

        return;
    }

    if (data.zone_selected === false || data.shipping_fee === null || data.shipping_fee === undefined) {
        shipEl.textContent = '—';
        grandEl.textContent = '—';
        if (hintEl) {
            hintEl.textContent = 'اختر المدينة من صفحة السلة لاحتساب التوصيل.';
            hintEl.classList.remove('hidden');
        }

        return;
    }

    shipEl.textContent = formatMoneyMad(data.shipping_fee);
    const grand =
        data.grand_total != null && Number.isFinite(Number(data.grand_total))
            ? Number(data.grand_total)
            : sub + Number(data.shipping_fee);
    grandEl.textContent = formatMoneyMad(grand);
    if (hintEl) {
        hintEl.classList.add('hidden');
    }
}

/**
 * @param {{ stock: number; track_stock?: boolean }} item
 */
function stockAvailabilityHint(item) {
    const stock = Number(item.stock) || 0;
    if (item.track_stock === false) {
        return 'متوفر';
    }
    return `متوفر: ${stock}`;
}

/**
 * @param {object} data
 * @param {{ id: number; name: string; slug: string; image: string; quantity: number; stock: number; track_stock?: boolean; line_total: number; product_url: string }[]} data.items
 */
function renderCartDrawerItems(data) {
    const container = document.getElementById('cart-drawer-items');
    if (!container) {
        return;
    }

    container.innerHTML = '';

    if (!data.items || data.items.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'py-12 text-center text-sm text-zinc-500';
        empty.textContent = 'السلة فارغة.';
        container.appendChild(empty);
        updateCartDrawerTotalsFromData({
            subtotal: 0,
            requires_paid_shipping: false,
            zone_selected: true,
            shipping_fee: 0,
            grand_total: 0,
        });

        return;
    }

    const list = document.createElement('ul');
    list.className = 'flex flex-col gap-4';

    data.items.forEach((item) => {
        const stock = Number(item.stock) || 0;
        const qty = Number(item.quantity) || 0;

        const li = document.createElement('li');
        li.className = 'flex gap-3 rounded-xl border border-zinc-100 bg-white p-3 shadow-sm';
        li.setAttribute('data-cart-line', '1');
        li.setAttribute('data-product-id', String(item.id));
        li.setAttribute('data-product-variation-id', item.product_variation_id != null ? String(item.product_variation_id) : '');
        li.setAttribute('data-cart-line-key', `${item.id}|${item.product_variation_id ?? 0}`);
        li.setAttribute('data-qty', String(qty));
        li.setAttribute('data-stock', String(stock));

        const imgWrap = document.createElement('a');
        imgWrap.href = item.product_url || '#';
        imgWrap.className = 'relative h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-zinc-100';
        const img = document.createElement('img');
        img.src = item.image;
        img.alt = '';
        img.className = 'h-full w-full object-cover';
        img.loading = 'eager';
        img.decoding = 'async';
        imgWrap.appendChild(img);

        const body = document.createElement('div');
        body.className = 'min-w-0 flex-1';

        const title = document.createElement('a');
        title.href = item.product_url || '#';
        title.className = 'line-clamp-2 text-sm font-semibold text-zinc-900 hover:text-emerald-800';
        title.textContent = item.name;
        body.appendChild(title);

        if (item.free_shipping) {
            const free = document.createElement('p');
            free.className = 'mt-1 text-xs font-medium text-emerald-700';
            free.textContent = 'التوصيل مجاني لهذا المنتج';
            body.appendChild(free);
        }

        const qtyRow = document.createElement('div');
        qtyRow.className = 'mt-2 flex flex-wrap items-center gap-2';

        const minusBtn = document.createElement('button');
        minusBtn.type = 'button';
        minusBtn.className =
            'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-white text-lg font-semibold text-zinc-700 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40';
        minusBtn.textContent = '−';
        minusBtn.setAttribute('data-qty-delta', '-1');
        minusBtn.setAttribute('aria-label', 'تقليل الكمية');

        const qtyVal = document.createElement('span');
        qtyVal.className = 'min-w-[2rem] text-center text-sm font-bold text-zinc-900';
        qtyVal.textContent = String(qty);

        const plusBtn = document.createElement('button');
        plusBtn.type = 'button';
        plusBtn.className =
            'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-white text-lg font-semibold text-zinc-700 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40';
        plusBtn.textContent = '+';
        plusBtn.setAttribute('data-qty-delta', '1');
        plusBtn.setAttribute('aria-label', 'زيادة الكمية');
        plusBtn.disabled = item.track_stock !== false && qty >= stock;

        const stockHint = document.createElement('span');
        stockHint.className = 'text-xs text-zinc-400';
        stockHint.textContent = stockAvailabilityHint(item);

        qtyRow.appendChild(minusBtn);
        qtyRow.appendChild(qtyVal);
        qtyRow.appendChild(plusBtn);
        qtyRow.appendChild(stockHint);

        const price = document.createElement('p');
        price.className = 'mt-2 text-sm font-bold text-zinc-900';
        price.textContent = formatMoneyMad(item.line_total);

        body.appendChild(qtyRow);
        body.appendChild(price);

        li.appendChild(imgWrap);
        li.appendChild(body);
        list.appendChild(li);
    });

    container.appendChild(list);

    updateCartDrawerTotalsFromData(data);
}

function initCartDrawer() {
    const root = document.getElementById('cart-drawer-root');
    const trigger = document.getElementById('store-cart-trigger');
    const backdrop = document.getElementById('cart-drawer-backdrop');
    const panel = document.getElementById('cart-drawer-panel');
    const closeBtn = document.getElementById('cart-drawer-close');
    const continueBtn = document.getElementById('cart-drawer-continue');
    const itemsContainer = document.getElementById('cart-drawer-items');

    if (typeof window.axios === 'undefined' || !root || !trigger || !backdrop || !panel) {
        return;
    }

    const drawerUrl = sameOriginPath(trigger.getAttribute('data-cart-drawer-url') || '/cart/drawer');

    /** Inline styles so pointer-events always win over Tailwind / stacking quirks. */
    function setDrawerPointerEvents(open) {
        const v = open ? 'auto' : 'none';
        root.style.pointerEvents = v;
        backdrop.style.pointerEvents = v;
        panel.style.pointerEvents = v;
    }

    async function loadCartDrawerData() {
        const container = document.getElementById('cart-drawer-items');
        if (!container) {
            return;
        }
        container.innerHTML =
            '<p class="py-10 text-center text-sm text-zinc-500">جاري التحميل...</p>';

        try {
            const { data } = await window.axios.get(drawerUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            renderCartDrawerItems(data);
            if (data.count !== undefined) {
                updateCartBadge(data.count);
            }
        } catch {
            container.innerHTML =
                '<p class="py-10 text-center text-sm text-rose-600">تعذر تحميل السلة.</p>';
        }
    }

    function revealDrawerPanel() {
        root.classList.remove('invisible');
        setDrawerPointerEvents(true);
        root.setAttribute('data-open', '1');
        root.setAttribute('aria-hidden', 'false');
        backdrop.classList.add('opacity-100');
        panel.classList.remove('-translate-x-full');
        panel.classList.add('translate-x-0');
        document.documentElement.classList.add('overflow-hidden');
        document.body.classList.add('overflow-hidden');
        trigger.setAttribute('aria-expanded', 'true');
    }

    async function openDrawer() {
        revealDrawerPanel();
        await loadCartDrawerData();
    }

    function closeDrawer() {
        document.documentElement.classList.remove('overflow-hidden');
        document.body.classList.remove('overflow-hidden');
        setDrawerPointerEvents(false);
        backdrop.classList.remove('opacity-100');
        panel.classList.add('-translate-x-full');
        panel.classList.remove('translate-x-0');
        trigger.setAttribute('aria-expanded', 'false');
        root.setAttribute('data-open', '0');
        root.setAttribute('aria-hidden', 'true');
        window.setTimeout(() => {
            root.classList.add('invisible');
        }, 300);
    }

    /**
     * Opens the mini-cart (if closed) and loads lines — used after add-to-cart.
     */
    window.openStoreCartDrawer = async function openStoreCartDrawer() {
        if (root.getAttribute('data-open') !== '1') {
            revealDrawerPanel();
        }
        await loadCartDrawerData();
    };

    /**
     * @param {object} [apiData] If provided (e.g. PATCH response), re-render without loading flash.
     */
    window.refreshCartDrawerIfOpen = function refreshCartDrawerIfOpen(apiData) {
        if (root.getAttribute('data-open') !== '1') {
            return;
        }
        if (apiData && Array.isArray(apiData.items)) {
            renderCartDrawerItems(apiData);

            return;
        }
        void loadCartDrawerData();
    };

    trigger.addEventListener('click', (e) => {
        e.preventDefault();
        if (root.getAttribute('data-open') === '1') {
            closeDrawer();

            return;
        }
        void openDrawer();
    });

    function onBackdropPointerDismiss(e) {
        if (root.getAttribute('data-open') !== '1') {
            return;
        }
        e.preventDefault();
        closeDrawer();
    }

    backdrop.addEventListener('click', onBackdropPointerDismiss);

    root.addEventListener(
        'click',
        (e) => {
            if (root.getAttribute('data-open') !== '1') {
                return;
            }
            if (e.target === backdrop) {
                e.preventDefault();
                closeDrawer();
            }
        },
        true,
    );
    closeBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        closeDrawer();
    });
    continueBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        closeDrawer();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && root.getAttribute('data-open') === '1') {
            closeDrawer();
        }
    });

    itemsContainer?.addEventListener('click', async (e) => {
        const targetEl = eventTargetElement(e);
        const btn = targetEl?.closest('[data-qty-delta]');
        if (!btn || !btn.matches('button') || btn.disabled) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        const line = btn.closest('[data-cart-line]');
        if (!line) {
            return;
        }

        const productId = Number.parseInt(line.getAttribute('data-product-id') || '0', 10);
        const vidRaw = line.getAttribute('data-product-variation-id');
        const vid = vidRaw === '' || vidRaw === null ? null : Number.parseInt(vidRaw, 10);
        const current = Number.parseInt(line.getAttribute('data-qty') || '0', 10);
        const stock = Number.parseInt(line.getAttribute('data-stock') || '0', 10);
        const delta = Number.parseInt(btn.getAttribute('data-qty-delta') || '0', 10);

        if (!productId || Number.isNaN(delta)) {
            return;
        }

        let next = current + delta;
        if (next < 0) {
            return;
        }
        if (stock > 0 && next > stock) {
            next = stock;
        }
        if (next === current) {
            return;
        }

        const buttons = line.querySelectorAll('[data-qty-delta]');
        buttons.forEach((b) => {
            b.disabled = true;
        });

        try {
            const { data } = await patchCartLineQuantity(productId, next, vid);

            if (data.cart_count !== undefined) {
                updateCartBadge(data.cart_count);
            }
            renderCartDrawerItems(data);
            if (document.getElementById('cart-page-lines')) {
                syncCartPageFromApi(data);
            }
        } catch (err) {
            showCartToast(extractErrorMessage(err), true);
            buttons.forEach((b) => {
                b.disabled = false;
            });
        }
    });
}

function initCartPageQuantity() {
    const container = document.getElementById('cart-page-lines');
    if (!container || typeof window.axios === 'undefined') {
        return;
    }

    container.addEventListener('click', async (e) => {
        const targetEl = eventTargetElement(e);
        const btn = targetEl?.closest('[data-qty-delta]');
        if (!btn || !btn.matches('button') || btn.disabled) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        const line = btn.closest('[data-cart-page-line]');
        if (!line) {
            return;
        }

        const productId = Number.parseInt(line.getAttribute('data-product-id') || '0', 10);
        const vidRaw = line.getAttribute('data-product-variation-id');
        const vid = vidRaw === '' || vidRaw === null ? null : Number.parseInt(vidRaw, 10);
        const current = Number.parseInt(line.getAttribute('data-qty') || '0', 10);
        const stock = Number.parseInt(line.getAttribute('data-stock') || '0', 10);
        const delta = Number.parseInt(btn.getAttribute('data-qty-delta') || '0', 10);

        if (!productId || Number.isNaN(delta)) {
            return;
        }

        let next = current + delta;
        if (next < 0) {
            return;
        }
        if (stock > 0 && next > stock) {
            next = stock;
        }
        if (next === current) {
            return;
        }

        const buttons = line.querySelectorAll('[data-qty-delta]');
        buttons.forEach((b) => {
            b.disabled = true;
        });

        try {
            const { data } = await patchCartLineQuantity(productId, next, vid);

            if (data.cart_count !== undefined) {
                updateCartBadge(data.cart_count);
            }
            syncCartPageFromApi(data);
            if (typeof window.refreshCartDrawerIfOpen === 'function') {
                window.refreshCartDrawerIfOpen(data);
            }
        } catch (err) {
            showCartToast(extractErrorMessage(err), true);
            buttons.forEach((b) => {
                b.disabled = false;
            });
        }
    });
}

function initPurchaseNowModal() {
    if (typeof window.axios === 'undefined') {
        return;
    }

    const trigger = document.querySelector('[data-purchase-now-trigger]');
    const modal = document.getElementById('purchase-now-modal');
    const form = document.getElementById('purchase-now-form');
    if (!trigger || !modal || !form) {
        return;
    }

    const nameEl = document.getElementById('purchase-now-product-name');
    const totalEl = document.getElementById('purchase-now-total');
    const zoneEl = document.getElementById('purchase-shipping-zone');
    const cityWrap = document.getElementById('purchase-city-wrap');
    const cityInput = document.getElementById('purchase-city');
    const sourceForm = document.getElementById('product-add-cart-form');

    function currentQuantity() {
        const qty = Number.parseInt(sourceForm?.querySelector('input[name="quantity"]')?.value || '1', 10);
        return qty > 0 ? qty : 1;
    }

    function currentVariationId() {
        const select = sourceForm?.querySelector('select[name="product_variation_id"]');
        return select ? select.value : null;
    }

    function currentUnitPrice() {
        const hasVariations = trigger.dataset.hasVariations === '1';
        if (!hasVariations) {
            return Number.parseFloat(trigger.dataset.basePrice || '0') || 0;
        }

        const prices = JSON.parse(trigger.dataset.variationPrices || '{}');
        const vid = currentVariationId();
        if (vid && prices[vid] !== undefined) {
            return Number.parseFloat(prices[vid]) || 0;
        }

        return Number.parseFloat(trigger.dataset.basePrice || '0') || 0;
    }

    function refreshModalSummary() {
        const qty = currentQuantity();
        const unit = currentUnitPrice();
        const total = unit * qty;
        if (nameEl) {
            nameEl.textContent = `${trigger.dataset.productName || ''} × ${qty}`;
        }
        if (totalEl) {
            totalEl.textContent = `${total.toFixed(2)} MAD`;
        }
    }

    function openModal() {
        refreshModalSummary();
        modal.classList.remove('hidden');
        document.documentElement.classList.add('overflow-hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
        document.documentElement.classList.remove('overflow-hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function syncCityVisibility() {
        const isOther = zoneEl?.value === 'other';
        cityWrap?.classList.toggle('hidden', !isOther);
        if (cityInput) {
            cityInput.required = Boolean(isOther);
            if (!isOther) {
                cityInput.value = '';
            }
        }
    }

    modal.querySelectorAll('[data-purchase-now-close]').forEach((el) => {
        el.addEventListener('click', closeModal);
    });
    trigger.addEventListener('click', openModal);
    zoneEl?.addEventListener('change', syncCityVisibility);
    syncCityVisibility();

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn instanceof HTMLButtonElement) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-60', 'pointer-events-none');
        }

        try {
            const addFd = new FormData();
            addFd.append('product_id', trigger.dataset.productId || '');
            addFd.append('quantity', String(currentQuantity()));
            const variationId = currentVariationId();
            if (variationId) {
                addFd.append('product_variation_id', variationId);
            }

            await window.axios.post('/cart', addFd, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            const checkoutUrl = trigger.dataset.checkoutUrl || '/checkout';
            const csrf = getCsrfToken();
            const quickForm = document.createElement('form');
            quickForm.method = 'POST';
            quickForm.action = checkoutUrl;
            quickForm.style.display = 'none';

            const values = new FormData(form);
            values.forEach((value, key) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = String(value);
                quickForm.appendChild(input);
            });

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrf;
            quickForm.appendChild(csrfInput);

            document.body.appendChild(quickForm);
            quickForm.submit();
        } catch (err) {
            showCartToast(extractErrorMessage(err), true);
            if (submitBtn instanceof HTMLButtonElement) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-60', 'pointer-events-none');
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initAddToCartForms();
    initCartDrawer();
    initCartPageQuantity();
    initPurchaseNowModal();
});
