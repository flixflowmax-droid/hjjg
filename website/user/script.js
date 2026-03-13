// user/script.js

// Premium Modal UI (LuxeUI)
const LuxeUI = {
    _injectOverlay: () => {
        if (document.getElementById('luxeModalOverlay')) return;
        const overlay = document.createElement('div');
        overlay.id = 'luxeModalOverlay';
        overlay.className = 'luxe-modal-overlay';
        overlay.innerHTML = `
            <div class="luxe-modal-card">
                <div id="luxeModalIcon" class="luxe-modal-icon"></div>
                <h3 id="luxeModalTitle" class="luxe-modal-title"></h3>
                <p id="luxeModalBody" class="luxe-modal-body"></p>
                <div id="luxeModalActions" class="luxe-modal-actions"></div>
            </div>
        `;
        document.body.appendChild(overlay);
    },
    alert: (msg, title = 'Notification', icon = 'fa-circle-info', color = 'var(--primary-color)') => {
        LuxeUI._injectOverlay();
        return new Promise(resolve => {
            const overlay = document.getElementById('luxeModalOverlay');
            const iconBox = document.getElementById('luxeModalIcon');
            const titleEl = document.getElementById('luxeModalTitle');
            const bodyEl = document.getElementById('luxeModalBody');
            const actionsEl = document.getElementById('luxeModalActions');

            iconBox.innerHTML = `<i class="fa-solid ${icon}"></i>`;
            if (color.startsWith('var')) {
                iconBox.style.background = 'var(--primary-color)';
                iconBox.style.color = 'var(--primary-contrast)';
            } else {
                iconBox.style.background = `${color}15`;
                iconBox.style.color = color;
            }
            titleEl.innerText = title;
            bodyEl.innerText = msg;
            actionsEl.innerHTML = `<button class="luxe-modal-btn luxe-modal-btn-primary">OK</button>`;

            overlay.classList.add('active');
            actionsEl.querySelector('button').onclick = () => {
                overlay.classList.remove('active');
                resolve();
            };
        });
    },
    confirm: (msg, title = 'Confirmation', icon = 'fa-circle-question', color = '#f59e0b') => {
        LuxeUI._injectOverlay();
        return new Promise(resolve => {
            const overlay = document.getElementById('luxeModalOverlay');
            const iconBox = document.getElementById('luxeModalIcon');
            const titleEl = document.getElementById('luxeModalTitle');
            const bodyEl = document.getElementById('luxeModalBody');
            const actionsEl = document.getElementById('luxeModalActions');

            iconBox.innerHTML = `<i class="fa-solid ${icon}"></i>`;
            iconBox.style.background = `${color}15`;
            iconBox.style.color = color;
            titleEl.innerText = title;
            bodyEl.innerText = msg;
            actionsEl.innerHTML = `
                <button class="luxe-modal-btn luxe-modal-btn-secondary">Cancel</button>
                <button class="luxe-modal-btn luxe-modal-btn-primary" style="background:${color}; color:white;">Confirm</button>
            `;

            overlay.classList.add('active');
            actionsEl.querySelectorAll('button')[0].onclick = () => {
                overlay.classList.remove('active');
                resolve(false);
            };
            actionsEl.querySelectorAll('button')[1].onclick = () => {
                overlay.classList.remove('active');
                resolve(true);
            };
        });
    }
};

let products = [];
let cart = JSON.parse(localStorage.getItem('luxury_cart')) || [];

async function initApp() {
    try {
        // Load settings first
        await loadSiteBranding();
        
        const timestamp = Date.now();
        const response = await fetch(`products.json?t=${timestamp}`);
        if (!response.ok) throw new Error("Failed to load products.json");
        products = (await response.json()).filter(p => p.isVisible !== false);
        window.isProductsLoaded = true;
        document.dispatchEvent(new Event('productsLoaded'));
    } catch (error) {
        console.error("Error loading products:", error);
    }
    updateCartUI();
}

async function loadSiteBranding() {
    try {
        const timestamp = Date.now();
        const res = await fetch(`api/global_settings.php?t=${timestamp}`);
        const d = await res.json();
        if (d.success) {
            const s = d.data;
            // Update Site Name
            if (s.site_name) {
                // Update <title>
                if (document.title.includes('LUXE FASHION')) {
                    document.title = document.title.replace('LUXE FASHION', s.site_name);
                }
                // Update <span> LUXE FASHION
                document.querySelectorAll('a span, h1 span, footer span').forEach(el => {
                    if (el.innerText === 'LUXE FASHION') el.innerText = s.site_name;
                });
            }
            // Update Site Logo
            if (s.site_logo) {
                document.querySelectorAll('.site-logo').forEach(img => {
                    img.src = s.site_logo;
                });
            }
            // Update Hero Image (only on home)
            const heroImg = document.querySelector('img[alt="Fashion Hero"]');
            if (heroImg && s.hero_image) {
                heroImg.src = s.hero_image;
            }
        }
    } catch (e) { console.warn("Branding sync failed", e); }
}

function addToCart(id, quantity = 1, size = 'M', color = '#000000') {
    const product = products.find(p => p.id === id);
    if (!product) return;

    const existingItem = cart.find(item => item.id === id && item.size === size && item.color === color);
    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            price: product.price,
            image1: product.image1 || (product.images && product.images[0]) || 'assets/placeholder.jpg',
            size: size,
            color: color,
            quantity: quantity
        });
    }
    saveCart();
    updateCartUI();
    openCart();
}

function removeFromCart(index) {
    cart.splice(index, 1);
    saveCart();
    updateCartUI();
}

function saveCart() {
    localStorage.setItem('luxury_cart', JSON.stringify(cart));
}

function updateCartUI() {
    const badges = document.querySelectorAll('.cart-badge');
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    badges.forEach(badge => badge.innerText = totalItems);

    const cartItemsContainer = document.getElementById('cart-items');
    const cartSubtotal = document.getElementById('cart-subtotal');
    
    if (cartItemsContainer && cartSubtotal) {
        if (cart.length === 0) {
            cartItemsContainer.innerHTML = '<p class="text-gray-500 text-center py-8">Your bag is empty.</p>';
            cartSubtotal.innerText = '৳0.00';
            return;
        }

        let subtotal = 0;
        cartItemsContainer.innerHTML = cart.map((item, index) => {
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;
            return `
                <div class="flex gap-4 items-center border-b pb-4">
                    <img src="${item.image1}" alt="${item.name}" class="w-16 h-20 object-cover rounded-md bg-gray-100 shrink-0">
                    <div class="flex-1">
                        <h4 class="text-sm font-bold truncate pr-2">${item.name}</h4>
                        <p class="text-xs text-gray-500">Size: ${item.size} | Qty: ${item.quantity}</p>
                        <p class="text-sm font-bold mt-1 text-[--primary-color]">৳${itemTotal.toFixed(2)}</p>
                    </div>
                    <button onclick="removeFromCart(${index})" class="text-gray-400 hover:text-red-500 transition px-2">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            `;
        }).join('');
        cartSubtotal.innerText = `৳${subtotal.toFixed(2)}`;
    }
}

function openCart() {
    document.getElementById('cart-drawer')?.classList.add('open');
    document.getElementById('cart-overlay')?.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeCart() {
    document.getElementById('cart-drawer')?.classList.remove('open');
    document.getElementById('cart-overlay')?.classList.remove('open');
    document.body.style.overflow = 'auto';
}

function toggleMobileSearch() {
    const searchBar = document.getElementById('mobile-search-bar');
    if (searchBar) {
        if (searchBar.classList.contains('-translate-y-full')) {
            searchBar.classList.remove('-translate-y-full');
            document.getElementById('mob-search')?.focus();
        } else {
            searchBar.classList.add('-translate-y-full');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Cart Listeners — use event delegation so it survives CMS innerHTML rewrites
    document.addEventListener('click', (e) => {
        const toggle = e.target.closest('.cart-toggle');
        if (toggle) {
            e.preventDefault();
            openCart();
        }
    });

    document.getElementById('close-cart')?.addEventListener('click', closeCart);

    // Search functionality
    const searchInputs = document.querySelectorAll('.search-input');
    searchInputs.forEach(input => {
        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = e.target.value.trim();
                if (query) {
                    window.location.href = `shop.html?search=${encodeURIComponent(query)}`;
                }
            }
        });
    });

    // 🟢 Mobile Menu Toggle Logic
    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const closeMenuBtn = document.getElementById('close-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const overlay = document.getElementById('cart-overlay');

    const openMobileMenu = () => {
        if (!mobileMenu) return;
        mobileMenu.classList.add('open');
        overlay?.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    const closeMobileMenu = () => {
        if (!mobileMenu) return;
        mobileMenu.classList.remove('open');
        // only remove overlay if cart drawer is not open
        if (!document.getElementById('cart-drawer')?.classList.contains('open')) {
            overlay?.classList.remove('open');
            document.body.style.overflow = 'auto';
        }
    };

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', openMobileMenu);
    }

    if (closeMenuBtn) {
        closeMenuBtn.addEventListener('click', closeMobileMenu);
    }

    // Overlay click: intelligently close whichever panel is open
    if (overlay) {
        overlay.addEventListener('click', () => {
            const cartOpen = document.getElementById('cart-drawer')?.classList.contains('open');
            const menuOpen = mobileMenu?.classList.contains('open');
            if (cartOpen) {
                closeCart();
            } else if (menuOpen) {
                closeMobileMenu();
            }
        });
    }

    // Scroll Header effect
    
    // 🟢 Global Reveal Animations Logic
    const reveals = document.querySelectorAll('.reveal');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
            }
        });
    }, {
        root: null,
        rootMargin: '0px',
        threshold: 0.15
    });

    reveals.forEach(el => observer.observe(el));
});

// Phase 2 & 3: Frontend Dynamic Content Injector
async function loadCmsFrontend() {
    try {
        const res = await fetch('cms_data.json');
        if (!res.ok) return;
        const d = await res.json();
        
        // Inject Dynamic Texts via data-text-key
        if(d.texts) {
            d.texts.forEach(item => {
                const els = document.querySelectorAll(`[data-text-key="${item.text_key}"]`);
                if (item.visible === false) {
                    els.forEach(el => el.style.display = 'none');
                    return;
                }
                els.forEach(el => {
                    // Skip cart-toggle elements to avoid wiping the bag icon HTML
                    if (el.classList.contains('cart-toggle')) {
                        // Only update the title attribute if present
                        if (el.hasAttribute('title')) {
                            el.setAttribute('title', item.text_value);
                        }
                        return;
                    }

                    // Inject into Title attribute if it exists and element is an icon/button
                    if (el.hasAttribute('title')) {
                        el.setAttribute('title', item.text_value);
                    }

                    // Inject into Placeholder if it's an input
                    if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                        if (el.hasAttribute('placeholder')) {
                            el.setAttribute('placeholder', item.text_value);
                        }
                        el.value = item.text_value;
                    } else if(el.tagName === 'BUTTON' && el.querySelector('i')){
                         // If button has an icon, it might have nested text
                         // Best to check if we should override the whole thing or just text nodes
                         // For now, simpler to just set innerHTML if no complex nesting is intended
                         el.innerHTML = item.text_value;
                    } else {
                        el.innerHTML = item.text_value;
                    }
                });
            });
        }
        
        // Inject Dynamic Menus
        if(d.menus) {
            // Footer Col 1
            const footerCol1 = document.getElementById('cms-footer-col-1');
            if(footerCol1) {
                const navs = d.menus.filter(m => m.menu_location === 'footer_col_1' && m.visible !== false);
                if(navs.length > 0) {
                    footerCol1.innerHTML = navs.map(m => `<li><a href="${m.link_target}" class="hover:text-[--primary-color] transition uppercase hover:underline" ${m.link_type === 'url' ? 'target="_blank"' : ''}>${m.menu_title}</a></li>`).join('');
                }
            }

            // Footer Col 2
            const footerCol2 = document.getElementById('cms-footer-col-2');
            if(footerCol2) {
                const navs = d.menus.filter(m => m.menu_location === 'footer_col_2' && m.visible !== false);
                if(navs.length > 0) {
                    footerCol2.innerHTML = navs.map(m => `<li><a href="${m.link_target}" class="hover:text-[--primary-color] transition uppercase hover:underline" ${m.link_type === 'url' ? 'target="_blank"' : ''}>${m.menu_title}</a></li>`).join('');
                }
            }

            // Footer Bottom Links
            const footerBottom = document.getElementById('cms-footer-bottom-links');
            if(footerBottom) {
                const navs = d.menus.filter(m => m.menu_location === 'bottom_links' && m.visible !== false);
                if(navs.length > 0) {
                    footerBottom.innerHTML = navs.map(m => `<a href="${m.link_target}" class="hover:text-white transition hover:underline" ${m.link_type === 'url' ? 'target="_blank"' : ''}>${m.menu_title}</a>`).join('');
                }
            }
            
            // Header Nav Mapping
            const headerNav = document.getElementById('cms-header-nav');
            if(headerNav) {
                const headMenus = d.menus.filter(m => m.menu_location === 'header' && m.visible !== false);
                if(headMenus.length > 0) {
                    headerNav.innerHTML = headMenus.map((m, i) => {
                        const classes = i === headMenus.length - 1 ? 'text-primary hover:text-primary-hover transition' : 'hover:text-primary transition';
                        return `<a href="${m.link_target}" class="${classes}" ${m.link_type === 'url' ? 'target="_blank"' : ''}>${m.menu_title}</a>`;
                    }).join('');
                }
            }

            // Page Buttons Mapping (Special handling for migrated keys)
            d.menus.forEach(m => {
                if(m.visible === false) return;
                if(m.menu_location.startsWith('data-attr-')) {
                    const key = m.menu_location.replace('data-attr-', '');
                    const els = document.querySelectorAll(`[data-text-key="${key}"]`);
                    els.forEach(el => {
                        // Update text
                        if(el.querySelector('span')) {
                            el.querySelector('span').innerText = m.menu_title;
                        } else {
                            el.innerText = m.menu_title;
                        }
                        // Update link/action
                        if(el.tagName === 'A') {
                            el.setAttribute('href', m.link_target);
                        }
                    });
                }
            });

            // Mobile Menu Mapping Example
            const mobileNav = document.querySelector('#mobile-menu nav');
            if(mobileNav) {
                const mobMenus = d.menus.filter(m => m.menu_location === 'mobile_menu' && m.visible !== false);
                if(mobMenus.length > 0) {
                    // Mobile navigation logic here if wanted.
                    // Instead of appending we will clean the default static links first
                    // The first 7 tags are the default links. Let's just override everything if there's any.
                    // To keep things simple we just replace the innerHTML entirely.
                    let mobHtml = '<button id="close-menu-btn" class="absolute top-6 right-8 text-gray-400 hover:text-black transition" onclick="document.getElementById(\'mobile-menu\').classList.remove(\'open\'); document.getElementById(\'cart-overlay\').classList.remove(\'open\'); document.body.style.overflow = \'auto\';"><i class="fa-solid fa-xmark text-4xl"></i></button>';
                    mobMenus.forEach((m, i) => {
                        const classes = i === mobMenus.length - 1 ? 'text-primary !text-primary hover:!text-primary-hover' : '';
                        mobHtml += `<a href="${m.link_target}" class="${classes}" ${m.link_type === 'url' ? 'target="_blank"' : ''}>${m.menu_title}</a>`;
                    });
                    
                    // Keep the divider and bottom static links, maybe? Or just use what we have in mobile menus.
                    // For now, let's keep it simple and just set innerHTML to mobHtml (close btn is technically outside nav, so this is fine).
                    // Actually, the close btn is outside the nav in the HTML.
                    mobileNav.innerHTML = mobMenus.map((m, i) => {
                        const classes = i === mobMenus.length - 1 ? 'text-primary !text-primary hover:!text-primary-hover' : '';
                        return `<a href="${m.link_target}" class="${classes}" ${m.link_type === 'url' ? 'target="_blank"' : ''}>${m.menu_title}</a>`;
                    }).join('');
                }
            }
        }

        // Inject Social Media Links
        if (d.social_media) {
            const socialContainer = document.getElementById('cms-social-links');
            if (socialContainer) {
                const visibleSocials = d.social_media.filter(s => s.visible !== false);
                socialContainer.innerHTML = visibleSocials.map(s => `
                    <a href="${s.url}" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-[--primary-color] hover:text-white transition" target="_blank" title="${s.name}">
                        <i class="${s.icon}"></i>
                    </a>
                `).join('');
            }
        }
    } catch(e) { console.log('CMS skipped: no configuration yet.'); }
}

initApp();
loadCmsFrontend();