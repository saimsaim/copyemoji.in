const kaomojiGrid = document.getElementById('kaomoji-grid');
const searchInput = document.getElementById('kaomoji-search');
const categoryFilters = document.getElementById('category-filters');
const recentList = document.getElementById('recently-used-list');
const themeToggle = document.getElementById('theme-toggle');
const toast = document.getElementById('toast');

const dock = document.getElementById('clipboard-dock');
const dockPreview = document.getElementById('dock-preview');
const dockCopyBtn = document.getElementById('dock-copy-btn');
const dockClearBtn = document.getElementById('dock-clear-btn');

let groupedData = {};
let dockStack = [];
const RECENT_KEY = "kaomoji_recent_v3";
let recentlyUsed = JSON.parse(localStorage.getItem(RECENT_KEY)) || [];

const isMobile = window.innerWidth < 768;

/* ================= INIT ================= */

document.addEventListener('DOMContentLoaded', () => {

    // Theme sync (same as index)
    const localTheme = localStorage.getItem('theme');
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (localTheme === 'dark' || (!localTheme && systemDark)) {
        document.body.classList.add('dark');
    }

    themeToggle.onclick = () => {
        document.body.classList.toggle('dark');
        localStorage.setItem(
            'theme',
            document.body.classList.contains('dark') ? 'dark' : 'light'
        );
    };

    fetch('assets/data/kaomoji.json')
        .then(r => r.json())
        .then(data => {
            groupedData = data.kaomoji.reduce((acc, obj) => {
                const cat = obj.category || "Other";
                if (!acc[cat]) acc[cat] = [];
                acc[cat].push(obj.value);
                return acc;
            }, {});
            renderFilters();
            renderAll();
            renderRecent();
        })
        .catch(err => {
            console.error('Failed to load kaomoji data:', err);
            kaomojiGrid.innerHTML = '<p style="text-align:center;margin:40px;color:gray;">Failed to load kaomojis. Please refresh the page.</p>';
        });

    initKeyboard();
});

/* ================= SEARCH ================= */

let debounce;
searchInput.addEventListener('input', (e) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        const term = e.target.value.toLowerCase().trim();

        if (!term) {
            renderAll();
            return;
        }

        const filtered = {};
        Object.keys(groupedData).forEach(cat => {
            const matches = groupedData[cat].filter(k =>
                k.toLowerCase().includes(term) ||
                cat.toLowerCase().includes(term)
            );
            if (matches.length) filtered[cat] = matches;
        });

        renderGrid(filtered);
    }, 300);
});

/* ================= FILTERS ================= */

function renderFilters() {
    let html = `<button class="category-btn active" role="tab" aria-selected="true" aria-label="Show all kaomojis"
        onclick="renderAll();setActive(this)">⚡ All</button>`;

    Object.keys(groupedData).sort().forEach(cat => {
        html += `<button class="category-btn" role="tab" aria-selected="false" aria-label="Show ${cat} kaomojis"
            onclick="filterCategory('${cat}',this)">${cat}</button>`;
    });

    categoryFilters.innerHTML = html;
}

function setActive(btn) {
    document.querySelectorAll('.category-btn')
        .forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-selected', 'false');
        });
    btn.classList.add('active');
    btn.setAttribute('aria-selected', 'true');
}

function filterCategory(cat, btn) {
    setActive(btn);
    searchInput.value = '';
    renderGrid({ [cat]: groupedData[cat] });
}

function renderAll() {
    renderGrid(groupedData);
}

/* ================= GRID ================= */

function renderGrid(obj) {

    if (!Object.keys(obj).length) {
        kaomojiGrid.innerHTML =
            '<p style="text-align:center;margin:40px;color:gray;" role="status">No results found 😕</p>';
        return;
    }

    kaomojiGrid.innerHTML = '';

    Object.keys(obj).forEach(cat => {

        const section = document.createElement('div');

        section.innerHTML = `
            <h3 class="kaomoji-cat-title">
                ${cat}
                <span style="font-size:0.6em;opacity:0.6;">
                    (${obj[cat].length})
                </span>
            </h3>
            <div class="kaomoji-items-grid" role="list" aria-label="${cat} kaomojis"></div>
        `;

        const grid = section.querySelector('.kaomoji-items-grid');

        obj[cat].forEach(k => {
            const div = document.createElement('div');
            div.className = 'emoji-item kaomoji-card';
            div.setAttribute('role', 'listitem');
            div.setAttribute('tabindex', '0');
            div.setAttribute('aria-label', `Copy kaomoji: ${k}`);
            div.innerHTML = `<span class="emoji-char">${k}</span>`;
            
            // Click handler
            div.onclick = () => handleClick(k, div);
            
            // Keyboard handler
            div.onkeydown = (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handleClick(k, div);
                }
            };
            
            grid.appendChild(div);
        });

        kaomojiGrid.appendChild(section);
    });
}

/* ================= CLICK ================= */

function handleClick(char, element) {
    // Copy to clipboard
    navigator.clipboard.writeText(char).catch(err => {
        console.error('Failed to copy:', err);
        showToast('Failed to copy ❌');
        return;
    });

    // Animation (desktop only)
    if (!isMobile && element) runFlyingAnimation(char, element);

    // Update recent and dock
    addRecent(char);
    addToDock(char);

    showToast(`Copied: ${char}`);
}

/* ================= RECENT ================= */

function addRecent(char) {
    recentlyUsed = [char, ...recentlyUsed.filter(x => x !== char)].slice(0, 10);
    localStorage.setItem(RECENT_KEY, JSON.stringify(recentlyUsed));
    renderRecent();
}

function renderRecent() {
    if (!recentList) return;

    if (!recentlyUsed.length) {
        recentList.innerHTML =
            '<span class="placeholder-text">Recently used kaomojis will appear here...</span>';
        return;
    }

    recentList.innerHTML = '';
    recentlyUsed.forEach(char => {
        const div = document.createElement('div');
        div.className = 'emoji-item kaomoji-card';
        div.setAttribute('role', 'listitem');
        div.setAttribute('tabindex', '0');
        div.setAttribute('aria-label', `Copy kaomoji: ${char}`);
        div.innerHTML = `<span class="emoji-char">${char}</span>`;
        
        // Click handler
        div.onclick = () => handleClick(char, div);
        
        // Keyboard handler
        div.onkeydown = (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handleClick(char, div);
            }
        };
        
        recentList.appendChild(div);
    });
}

/* ================= DOCK ================= */

function addToDock(char) {
    // Prevent duplicates in dock
    if (dockStack.includes(char)) return;
    
    dockStack.push(char);
    updateDock();
}

function updateDock() {

    dockPreview.innerHTML = '';

    if (!dockStack.length) {
        dock.classList.remove('visible');
        return;
    }

    dock.classList.add('visible');

    dockStack.forEach((k, i) => {
        const chip = document.createElement('div');
        chip.className = 'dock-item';
        chip.setAttribute('role', 'listitem');
        chip.setAttribute('aria-label', `Remove ${k} from dock`);
        chip.innerHTML = `
            ${k}
            <span class="dock-remove" aria-label="Remove ${k}"
                onclick="event.stopPropagation();removeFromDock(${i})">×</span>
        `;
        dockPreview.appendChild(chip);
    });
}

function removeFromDock(i) {
    dockStack.splice(i, 1);
    updateDock();
}

dockCopyBtn.onclick = () => {
    if (dockStack.length) {
        navigator.clipboard.writeText(dockStack.join(' ')).then(() => {
            showToast("Stack Copied! 📋");
        }).catch(err => {
            console.error('Failed to copy stack:', err);
            showToast("Failed to copy ❌");
        });
    }
};

dockClearBtn.onclick = () => {
    dockStack = [];
    updateDock();
};

/* ================= KEYBOARD ================= */

function initKeyboard() {
    document.addEventListener('keydown', (e) => {

        // Focus search on '/'
        if (e.key === '/' && document.activeElement !== searchInput) {
            e.preventDefault();
            searchInput.focus();
        }

        // Copy dock on Ctrl+C / Cmd+C
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'c') {
            if (dockStack.length && !window.getSelection().toString()) {
                e.preventDefault();
                navigator.clipboard.writeText(dockStack.join(' ')).then(() => {
                    showToast("Stack Copied! 📋");
                });
            }
        }

        // Clear on Escape
        if (e.key === 'Escape') {
            searchInput.value = '';
            renderAll();
            dockStack = [];
            updateDock();
            searchInput.blur();
        }
    });
}

/* ================= ANIMATION ================= */

function runFlyingAnimation(char, element) {
    const start = element.getBoundingClientRect();
    const end = dock.getBoundingClientRect();

    const fly = document.createElement('div');
    fly.className = 'spiral-emoji';
    fly.innerText = char;
    fly.setAttribute('aria-hidden', 'true');

    Object.assign(fly.style, {
        left: start.left + 'px',
        top: start.top + 'px',
        position: 'fixed'
    });

    document.body.appendChild(fly);

    setTimeout(() => {
        fly.style.transition = 'all .8s ease';
        fly.style.left = end.left + 'px';
        fly.style.top = end.top + 'px';
        fly.style.opacity = '0';
    }, 50);

    setTimeout(() => fly.remove(), 900);
}

/* ================= TOAST ================= */

function showToast(msg) {
    toast.textContent = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2000);
}

/* ================= 3D TILT EFFECT (Desktop only) ================= */

if (!isMobile) {
    document.addEventListener('mousemove', (e) => {
        const card = e.target.closest('.kaomoji-card');
        if (!card) return;
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left; 
        const y = e.clientY - rect.top;
        const rotateX = ((y - rect.height / 2) / (rect.height / 2)) * -10;
        const rotateY = ((x - rect.width / 2) / (rect.width / 2)) * 10;
        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.02)`;
    });
    
    document.addEventListener('mouseout', (e) => {
        const card = e.target.closest('.kaomoji-card');
        if (card) card.style.transform = 'none';
    });
}