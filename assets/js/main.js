const emojiGrid = document.getElementById('emoji-grid');
const searchInput = document.getElementById('search-input');
const categoryFilters = document.getElementById('category-filters');
const toast = document.getElementById('toast');
const recentContainer = document.getElementById('recently-copied-container');
const recentList = document.getElementById('recently-copied-list');
const themeToggle = document.getElementById('theme-toggle');
const dock = document.getElementById('clipboard-dock');
const dockPreview = document.getElementById('dock-preview');
const dockCopyBtn = document.getElementById('dock-copy-btn');
const dockClearBtn = document.getElementById('dock-clear-btn');
const toneButtons = document.querySelectorAll('.tone-btn');
const contactForm = document.getElementById('contact-form');

let allData = [];
let allEmojis = [];
// Storage key for persistence
const RECENT_STORAGE_KEY = 'recentlyCopied';
let recentlyCopied = JSON.parse(localStorage.getItem(RECENT_STORAGE_KEY)) || [];
let dockStack = [];
let currentSkinTone = "";
let currentRequestId; 

// Mobile Check
const isMobile = window.innerWidth < 768;

const skinTones = {
    "default": "", "light": "\u{1F3FB}", "medium-light": "\u{1F3FC}",
    "medium": "\u{1F3FD}", "medium-dark": "\u{1F3FE}", "dark": "\u{1F3FF}"
};
const categoryIcons = {
    "Smileys & Emotion": "😀", "People & Body": "👋", "Animals & Nature": "🐻",
    "Food & Drink": "🍔", "Travel & Places": "🚗", "Activities": "⚽",
    "Objects": "💡", "Symbols": "🔣", "Flags": "🏳️", "Component": "🧱"
};

document.addEventListener('DOMContentLoaded', () => {
    // 🌓 Automatic System Theme Detection
    const localTheme = localStorage.getItem('theme');
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (localTheme === 'dark' || (!localTheme && systemDark)) {
        document.body.classList.add('dark');
    }

    if(themeToggle) {
        themeToggle.onclick = () => {
            document.body.classList.toggle('dark');
            localStorage.setItem('theme', document.body.classList.contains('dark')?'dark':'light');
        };
    }

    // ⌨️ KEYBOARD SHORTCUTS
    document.addEventListener('keydown', (e) => {
        // 1. Press '/' to focus search
        if (e.key === '/' && document.activeElement !== searchInput) {
            e.preventDefault();
            searchInput.focus();
        }

        // 2. Ctrl+C or Cmd+C to copy the stack (NO SPACE)
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'c') {
            if (dockStack.length > 0 && window.getSelection().toString() === "") {
                e.preventDefault();
                navigator.clipboard.writeText(dockStack.join('')); 
                showToast('Stack Copied! 📋');
            }
        }

        // 3. Press 'Esc' to clear search and dock
        if (e.key === 'Escape') {
            if (searchInput) {
                searchInput.value = '';
                renderEmojis(allEmojis);
                searchInput.blur();
            }
            clearDock();
        }
    });

    const yearSpan = document.getElementById('year');
    if(yearSpan) yearSpan.textContent = new Date().getFullYear();

    if(emojiGrid) loadEmojiData();
    if(contactForm) initContactForm();

    window.addEventListener("load", () => {
        setTimeout(greetUserSimple, 4000);
    });
});

function greetUserSimple() {
    const today = new Date().toDateString();
    const lastVisit = localStorage.getItem("lastVisitDate");
    if (lastVisit === today) return;
    localStorage.setItem("lastVisitDate", today);

    const toast = document.createElement("div");
    toast.textContent = "👋 Welcome back!";
    Object.assign(toast.style, {
        position: "fixed", bottom: "20px", left: "50%", transform: "translateX(-50%)",
        background: "#222", color: "#fff", padding: "10px 16px", borderRadius: "20px",
        fontSize: "14px", boxShadow: "0 4px 10px rgba(0,0,0,0.2)", zIndex: "9999",
        opacity: "0", transition: "opacity 0.4s ease"
    });

    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.style.opacity = "1");
    setTimeout(() => {
        toast.style.opacity = "0";
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}

function loadEmojiData() {
    fetch('assets/data/emoji.json')
    .then(r => r.json())
    .then(data => {
        allData = data; 
        allEmojis = data.flatMap(cat => cat.emojis || []);
        renderCategoryButtons();
        renderEmojis(allEmojis);
        renderRecent();
        createEmojiSidebar(); 
    })
    .catch(err => console.error("JSON Error:", err));
}

function renderCategoryButtons() {
    if(!categoryFilters) return;
    const fragment = document.createDocumentFragment();
    
    const allBtn = document.createElement('button');
    allBtn.className = 'category-btn active';
    allBtn.innerHTML = `<span class="cat-icon">⚡</span> All`; 
    allBtn.onclick = () => {
        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
        allBtn.classList.add('active');
        searchInput.value = ''; renderEmojis(allEmojis);
    };
    fragment.appendChild(allBtn);

    allData.forEach(cat => {
        if (!cat.emojis || cat.emojis.length === 0) return;
        const btn = document.createElement('button');
        btn.className = 'category-btn';
        btn.innerHTML = `<span class="cat-icon">${categoryIcons[cat.name] || "📂"}</span> ${cat.name}`;
        btn.onclick = () => {
            document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            searchInput.value = ''; renderEmojis(cat.emojis);
        };
        fragment.appendChild(btn);
    });
    
    categoryFilters.innerHTML = '';
    categoryFilters.appendChild(fragment);
}

function renderEmojis(list) {
    if (currentRequestId) cancelAnimationFrame(currentRequestId);
    if (!emojiGrid) return;

    // 🔥 OLD CODE: emojiGrid.innerHTML = ''; (Yeh grid ko collapse karta tha)
    
    // ✅ NEW FIX: Sirf tab clear karo jab naya content ready ho
    // Pehle chunk ko prepare karo
    const initialChunkSize = 50; 
    const firstChunk = list.slice(0, initialChunkSize);
    
    // Agar list empty hai
    if(list.length === 0) {
        emojiGrid.innerHTML = '<p style="text-align:center;width:100%;color:gray;margin-top:50px;">No emojis found 😕</p>';
        return;
    }

    // Pehla chunk render karne se pehle clear karo (Minimizes blank time)
    emojiGrid.innerHTML = ''; 
    appendChunk(firstChunk);

    let index = initialChunkSize;
    const subsequentChunkSize = isMobile ? 40 : 150; 

    function loadNextChunk() {
        if (index >= list.length) return;
        
        // 🚀 Performance: Use RequestIdleCallback if available for background work
        if (window.requestIdleCallback) {
            requestIdleCallback(() => {
                const chunk = list.slice(index, index + subsequentChunkSize);
                appendChunk(chunk);
                index += subsequentChunkSize;
                loadNextChunk(); // Chain the next call
            });
        } else {
            // Fallback for older browsers
            const chunk = list.slice(index, index + subsequentChunkSize);
            appendChunk(chunk);
            index += subsequentChunkSize;
            currentRequestId = requestAnimationFrame(loadNextChunk);
        }
    }

    // Thoda delay taaki main thread block na ho
    setTimeout(loadNextChunk, 100);
}

function appendChunk(chunk) {
    const fragment = document.createDocumentFragment();
    chunk.forEach(item => {
        let char = item.emoji;
        if (currentSkinTone && isSkinToneSupported(char)) {
            char = char.replace(/\uFE0F/g, '') + currentSkinTone;
        }

        // Slug banaya single page pe link karne ke liye
        let slugRaw = item.slug ? item.slug.replace(/_/g, '-') : 'emoji';

        const div = document.createElement('div');
        div.className = 'emoji-item';
        div.title = item.name;
        
        // ✨ NAYA LOGIC: 'i' button add kiya top-right me ✨
        div.innerHTML = `
            <a href="/emoji/${slugRaw}" class="info-btn" title="View Details" target="_blank" onclick="event.stopPropagation()">ℹ️</a>
            <span class="emoji-char">${char}</span>
            <button class="download-btn" aria-label="Download PNG">⬇ PNG</button>
        `;
        
        div.onclick = () => handleEmojiClick(char, div);
        
        div.querySelector('.download-btn').onclick = (e) => {
            e.stopPropagation();
            downloadEmoji(char, item.name);
        };
        
        fragment.appendChild(div);
    });
    emojiGrid.appendChild(fragment);
}

let debounceTimer;
if(searchInput) {
    searchInput.addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
            const term = e.target.value.toLowerCase();
            const filtered = allEmojis.filter(item => item.name.toLowerCase().includes(term));
            renderEmojis(filtered);
        }, 300);
    });
}

toneButtons.forEach(btn => {
    btn.addEventListener('click', () => {
        toneButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentSkinTone = skinTones[btn.getAttribute('data-tone')] || "";
        const activeBtn = document.querySelector('.category-btn.active');
        if(activeBtn) activeBtn.click(); else renderEmojis(allEmojis);
    });
});

function isSkinToneSupported(emoji) {
    const clean = emoji.replace(/\uFE0F/g, ''); 
    const isBase = /\p{Emoji_Modifier_Base}/u.test(clean);
    return isBase && !clean.includes('\u200D');
}

function handleEmojiClick(char, element) {
    // 1. Direct copy
    navigator.clipboard.writeText(char);

    // 2. 🔥 FIX: Animation starts BEFORE re-rendering recent list
    if (element && dock && !isMobile) {
        runFlyingAnimation(char, element);
    }

    // 3. Storage and UI update
    addRecent(char); 
    addToDock(char);
    showToast(`Copied: ${char}`);
}

function runFlyingAnimation(char, element) {
    const startRect = element.getBoundingClientRect();
    const dockRect = dock.getBoundingClientRect();
    
    const floater = document.createElement('div');
    floater.innerText = char;
    floater.className = 'spiral-emoji';
    Object.assign(floater.style, {
        position: 'fixed', left: `${startRect.left + startRect.width/2}px`,
        top: `${startRect.top + startRect.height/2}px`, zIndex: '9999', pointerEvents: 'none'
    });
    
    document.body.appendChild(floater);
    
    setTimeout(() => {
        floater.style.transition = 'all 0.8s ease-in-out';
        floater.style.left = `${dockRect.left + dockRect.width/2}px`;
        floater.style.top = `${dockRect.top + dockRect.height/2}px`;
        floater.style.transform = 'scale(0.1)';
        floater.style.opacity = '0';
    }, 50);

    setTimeout(() => floater.remove(), 1000);
}

function renderRecent() {
    if (!recentContainer || !recentList) return;
    if (recentlyCopied.length === 0) { 
        recentList.innerHTML = '<span class="placeholder-text">Recently used emojis will appear here...</span>';
        return;
    }
    recentList.innerHTML = '';
    recentlyCopied.forEach(char => {
        const div = document.createElement('div');
        div.className = 'emoji-item';
        div.style.cssText = 'width:50px;height:50px;padding:0;justify-content:center;';
        div.innerHTML = `<span class="emoji-char" style="font-size: 24px;">${char}</span>`;
        div.onclick = () => handleEmojiClick(char, div);
        recentList.appendChild(div);
    });
}

function addRecent(char) {
    // 💾 Persist to localStorage
    recentlyCopied = [char, ...recentlyCopied.filter(x => x !== char)].slice(0, 10);
    localStorage.setItem(RECENT_STORAGE_KEY, JSON.stringify(recentlyCopied));
    renderRecent();
}

function addToDock(char) { dockStack.push(char); updateDockUI(); }
function removeFromDock(index) { dockStack.splice(index, 1); updateDockUI(); }

function updateDockUI() {
    if(!dockPreview || !dock) return;
    dockPreview.innerHTML = '';
    if (dockStack.length > 0) {
        dock.classList.add('visible');
        dockStack.forEach((emoji, index) => {
            const chip = document.createElement('div');
            chip.className = 'dock-item';
            chip.innerHTML = `${emoji} <span class="dock-remove" aria-label="Remove">×</span>`;
            chip.querySelector('.dock-remove').onclick = (e) => { e.stopPropagation(); removeFromDock(index); };
            dockPreview.appendChild(chip);
        });
    } else { dock.classList.remove('visible'); }
}

function clearDock() { dockStack = []; updateDockUI(); }

if(dockCopyBtn) dockCopyBtn.onclick = () => { 
    if(dockStack.length) {
        // 🔥 FIX: join('') to remove spaces
        navigator.clipboard.writeText(dockStack.join('')); 
        showToast('Stack Copied!'); 
    }
};
if(dockClearBtn) dockClearBtn.onclick = clearDock;

function showToast(msg) { 
    if(!toast) return;
    toast.textContent = msg; 
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2000); 
}

function downloadEmoji(emoji, name) {
    const canvas = document.createElement("canvas"); const ctx = canvas.getContext("2d");
    canvas.width = 512; canvas.height = 512;
    ctx.font = "400px 'Segoe UI Emoji', 'Apple Color Emoji', sans-serif";
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(emoji, canvas.width/2, canvas.height/2 + 40);
    const link = document.createElement("a");
    link.download = `emoji-${name ? name.replace(/\s+/g, '-').toLowerCase() : 'icon'}.png`;
    link.href = canvas.toDataURL("image/png"); link.click(); showToast('Downloading PNG... 📥');
}

function initContactForm() {
    const result = document.getElementById('form-result');
    const btn = document.getElementById('form-btn');
    if(!contactForm) return;
    contactForm.addEventListener('submit', function(e) {
        e.preventDefault();
        btn.innerHTML = "Sending... ⏳"; btn.disabled = true;
        const formData = new FormData(contactForm);
        const json = JSON.stringify(Object.fromEntries(formData));
        fetch('https://api.web3forms.com/submit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: json
        })
        .then(async (response) => {
            let json = await response.json();
            if (response.status == 200) {
                result.style.display = "block"; result.style.color = "green";
                result.innerHTML = "Message Sent Successfully! ✅"; 
                contactForm.reset();
            } else {
                result.style.display = "block"; result.style.color = "red";
                result.innerHTML = json.message;
            }
        })
        .catch(error => { 
            result.style.display = "block";
            result.style.color = "red"; result.innerHTML = "Something went wrong!"; 
        })
        .then(() => { 
            btn.innerHTML = "Send Message 🚀"; btn.disabled = false; 
            setTimeout(() => { result.style.display = "none"; }, 5000); 
        });
    });
}

if (!isMobile) {
    const trailEmojis = ['✨', '🔥', '🚀', '⭐', '⚡', '🌈'];
    let lastX = 0, lastY = 0;
    
    document.addEventListener('mousemove', (e) => {
        const distance = Math.hypot(e.clientX - lastX, e.clientY - lastY);
        if (distance > 30) { 
            createTrail(e.clientX, e.clientY);
            lastX = e.clientX;
            lastY = e.clientY;
        }
    });

    function createTrail(x, y) {
        const el = document.createElement('div');
        el.className = 'trail-emoji';
        el.innerText = trailEmojis[Math.floor(Math.random() * trailEmojis.length)];
        el.style.left = x + 'px';
        el.style.top = y + 'px';
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 800);
    }

    const grid = document.getElementById('emoji-grid');
    if (grid) {
        grid.addEventListener('mousemove', (e) => {
            const card = e.target.closest('.emoji-item');
            if (!card) return;
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left; 
            const y = e.clientY - rect.top;
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            const rotateX = ((y - centerY) / centerY) * -15; 
            const rotateY = ((x - centerX) / centerX) * 15;
            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.05)`;
        });

        grid.addEventListener('mouseleave', (e) => {
            const card = e.target.closest('.emoji-item');
            if (card) {
                card.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg) scale(1)`;
            }
        }, true);
    }
}

function createEmojiSidebar() {
    // Purana sidebar delete karo agar hai toh
    const oldSidebar = document.querySelector('.emoji-sidebar');
    if (oldSidebar) oldSidebar.remove();

    const sidebar = document.createElement('div');
    sidebar.className = 'emoji-sidebar';

    allData.forEach(cat => {
        if (!cat.emojis || cat.emojis.length === 0) return;
        
        const icon = document.createElement('span');
        icon.className = 'sidebar-icon';
        icon.innerHTML = categoryIcons[cat.name] || "📂";
        icon.title = cat.name;

        icon.onclick = () => {
            // Category sections ko search karega aur wahan scroll karega
            const section = document.getElementById(`cat-${cat.name.replace(/\s+/g, '-')}`);
            if (section) {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                // Agar sections nahi bane, toh filter trigger kar do (fallback)
                renderEmojis(cat.emojis);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        };
        sidebar.appendChild(icon);
    });

    document.body.appendChild(sidebar);
}

// ✅ Register Service Worker for PWA
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js')
      .then(registration => {
        console.log('Service Worker Registered! 🚀', registration.scope);
      })
      .catch(err => {
        console.log('Service Worker Failed: ', err);
      });
  });
}

window.addEventListener('appinstalled', (evt) => {
  // Custom install button ko hide kar do taaki baar-baar na dikhe
  document.getElementById('myCustomInstallBtn').style.display = 'none';
  console.log('Bhai ki App successfully install ho gayi! 🚀');
});