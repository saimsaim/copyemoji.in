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
let recentlyCopied = JSON.parse(localStorage.getItem('recentlyCopied')) || [];
let dockStack = [];
let currentSkinTone = "";

const skinTones = {
    "default": "", "light": "\u{1F3FB}", "medium-light": "\u{1F3FC}",
    "medium": "\u{1F3FD}", "medium-dark": "\u{1F3FE}", "dark": "\u{1F3FF}"
};

const categoryIcons = {
    "Smileys & Emotion": "😀", "People & Body": "👋", "Animals & Nature": "🐻",
    "Food & Drink": "🍔", "Travel & Places": "🚗", "Activities": "⚽",
    "Objects": "💡", "Symbols": "🔣", "Flags": "🏳️", "Component": "🧱"
};

/* 🚀 INIT */
document.addEventListener('DOMContentLoaded', () => {
    if(localStorage.getItem('theme')==='dark') document.body.classList.add('dark');
    if(themeToggle) {
        themeToggle.onclick = () => {
            document.body.classList.toggle('dark');
            localStorage.setItem('theme', document.body.classList.contains('dark')?'dark':'light');
        };
    }

    const yearSpan = document.getElementById('year');
    if(yearSpan) yearSpan.textContent = new Date().getFullYear();

    if(emojiGrid) loadEmojiData();
    if(contactForm) initContactForm();

    // ✨ GREETING START (Fire Border + HI)
    window.addEventListener("load", () => {
    setTimeout(greetUserSimple, 1500);
});

});



function greetUserSimple() {
    const today = new Date().toDateString();
    const lastVisit = localStorage.getItem("lastVisitDate");

    if (lastVisit === today) return;

    localStorage.setItem("lastVisitDate", today);

    const toast = document.createElement("div");
    toast.textContent = "👋 Welcome back!";
    toast.style.position = "fixed";
    toast.style.bottom = "20px";
    toast.style.left = "50%";
    toast.style.transform = "translateX(-50%)";
    toast.style.background = "#222";
    toast.style.color = "#fff";
    toast.style.padding = "10px 16px";
    toast.style.borderRadius = "20px";
    toast.style.fontSize = "14px";
    toast.style.boxShadow = "0 4px 10px rgba(0,0,0,0.2)";
    toast.style.zIndex = "9999";
    toast.style.opacity = "0";
    toast.style.transition = "opacity 0.4s ease";

    document.body.appendChild(toast);

    // Fade in
    setTimeout(() => {
        toast.style.opacity = "1";
    }, 200);

    // Auto remove
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
        allEmojis = [];
        data.forEach(cat => { if(cat.emojis) allEmojis = allEmojis.concat(cat.emojis); });
        renderCategoryButtons();
        renderEmojis(allEmojis);
        renderRecent();
    })
    .catch(err => console.error("JSON Error:", err));
}

function renderCategoryButtons() {
    if(!categoryFilters) return;
    categoryFilters.innerHTML = '';
    const allBtn = document.createElement('button');
    allBtn.className = 'category-btn active';
    allBtn.innerHTML = `<span class="cat-icon">⚡</span> All`; 
    allBtn.onclick = () => {
        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
        allBtn.classList.add('active');
        searchInput.value = ''; renderEmojis(allEmojis);
    };
    categoryFilters.appendChild(allBtn);

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
        categoryFilters.appendChild(btn);
    });
}

let currentRequestId;
function renderEmojis(list) {
    if (currentRequestId) cancelAnimationFrame(currentRequestId);
    if (!emojiGrid) return;
    emojiGrid.innerHTML = '';
    
    if(list.length === 0) {
        emojiGrid.innerHTML = '<p style="text-align:center; width:100%; color:gray; margin-top:50px;">No emojis found 😕</p>';
        return;
    }
    
    let index = 0;
    const chunkSize = 200;

    function loadNextChunk() {
        if (index >= list.length) return;
        const chunk = list.slice(index, index + chunkSize);
        const fragment = document.createDocumentFragment();

        chunk.forEach(item => {
            let char = item.emoji;
            if (currentSkinTone && isSkinToneSupported(char)) {
                char = char.replace(/\uFE0F/g, '') + currentSkinTone;
            }

            const div = document.createElement('div');
            div.className = 'emoji-item';
            div.title = item.name;
            div.innerHTML = `<span class="emoji-char">${char}</span><button class="download-btn">⬇ PNG</button>`;
            div.onclick = () => handleEmojiClick(char, div);
            div.querySelector('.download-btn').onclick = (e) => {
                e.stopPropagation();
                downloadEmoji(char, item.name);
            };
            fragment.appendChild(div);
        });

        emojiGrid.appendChild(fragment);
        index += chunkSize;
        currentRequestId = requestAnimationFrame(loadNextChunk);
    }
    loadNextChunk();
}

if(searchInput) {
    searchInput.addEventListener('input', (e) => {
        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
        const filtered = allEmojis.filter(item => item.name.toLowerCase().includes(e.target.value.toLowerCase()));
        renderEmojis(filtered);
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
    navigator.clipboard.writeText(char);
    addRecent(char);
    addToDock(char);
    showToast(`Copied: ${char}`);

    if (element && dock) {
        const startRect = element.getBoundingClientRect();
        const startX = startRect.left + startRect.width / 2;
        const startY = startRect.top + startRect.height / 2;

        const dockRect = dock.getBoundingClientRect();
        const endX = dockRect.left + dockRect.width / 2;
        const endY = dockRect.top + dockRect.height / 2;

        const floater = document.createElement('div');
        floater.innerText = char;
        floater.className = 'spiral-emoji';
        floater.style.position = 'fixed';
        floater.style.left = `${startX}px`;
        floater.style.top = `${startY}px`;
        floater.style.zIndex = '9999';
        floater.style.pointerEvents = 'none';
        document.body.appendChild(floater);

        const moves = ['sidha', 'aada-teda', 'spiral', 'extreme-spin'];
        const randomMove = moves[Math.floor(Math.random() * moves.length)];

        setTimeout(() => {
            floater.style.transition = 'all 1.2s cubic-bezier(0.42, 0, 0.58, 1)';
            if (randomMove === 'sidha') {
                floater.style.left = `${endX}px`;
                floater.style.top = `${endY}px`;
                floater.style.transform = 'scale(0.3)';
            } else if (randomMove === 'aada-teda') {
                floater.style.left = `${startX + (endX - startX) * 0.5 + 150}px`;
                floater.style.top = `${startY - 100}px`;
                floater.style.transform = 'rotate(-45deg) scale(1.5)';
                setTimeout(() => {
                    floater.style.left = `${endX}px`;
                    floater.style.top = `${endY}px`;
                    floater.style.transform = 'rotate(0deg) scale(0.2)';
                }, 500);
            } else if (randomMove === 'spiral') {
                floater.style.left = `${startX + (endX - startX) * 0.5}px`;
                floater.style.top = `${startY - 250}px`;
                floater.style.transform = 'rotate(360deg) scale(2)';
                setTimeout(() => {
                    floater.style.left = `${endX}px`;
                    floater.style.top = `${endY}px`;
                    floater.style.transform = 'rotate(720deg) scale(0.1)';
                }, 600);
            } else if (randomMove === 'extreme-spin') {
                floater.classList.add('super-spin');
                floater.style.left = `${endX}px`;
                floater.style.top = `${endY}px`;
                floater.style.transitionDuration = '1.5s';
                floater.style.transform = 'scale(0.1)';
            }
            floater.style.opacity = '0';
        }, 50);

        setTimeout(() => {
            floater.remove();
            dock.style.transform = "translateX(-50%) scale(1.2)";
            setTimeout(() => dock.style.transform = "translateX(-50%) scale(1)", 200);
        }, 1500);
    }
}

function renderRecent() {
    if (!recentContainer || !recentList) return;
    if (recentlyCopied.length === 0) { recentContainer.style.display = 'none'; return; }
    recentContainer.style.display = 'block';
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
    recentlyCopied = [char, ...recentlyCopied.filter(x => x !== char)].slice(0, 10);
    localStorage.setItem('recentlyCopied', JSON.stringify(recentlyCopied));
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
            chip.innerHTML = `${emoji} <span class="dock-remove">×</span>`;
            chip.querySelector('.dock-remove').onclick = (e) => { e.stopPropagation(); removeFromDock(index); };
            dockPreview.appendChild(chip);
        });
    } else { dock.classList.remove('visible'); }
}
function clearDock() { dockStack = []; updateDockUI(); }

if(dockCopyBtn) dockCopyBtn.onclick = () => { if(dockStack.length) navigator.clipboard.writeText(dockStack.join(' ')); showToast('Stack Copied!'); };
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
    ctx.textAlign = "center"; ctx.textBaseline = "middle";
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
                result.innerHTML = "Message Sent Successfully! ✅"; contactForm.reset();
            } else {
                result.style.display = "block"; result.style.color = "red";
                result.innerHTML = json.message;
            }
        })
        .catch(error => { result.style.display = "block"; result.style.color = "red"; result.innerHTML = "Something went wrong!"; })
        .then(() => { btn.innerHTML = "Send Message 🚀"; btn.disabled = false; setTimeout(() => { result.style.display = "none"; }, 5000); });
    });
}


const trailEmojis = ['✨', '🔥', '🚀', '⭐', '⚡', '🌈'];
let lastX = 0;
let lastY = 0;

// Sirf Desktop (Width > 1024px) pe chalega
if (window.matchMedia("(min-width: 1024px)").matches) {
    document.addEventListener('mousemove', (e) => {
        const distance = Math.hypot(e.clientX - lastX, e.clientY - lastY);
        
        // Thoda distance badha diya (30px) taaki aur smooth chale
        if (distance > 30) { 
            createTrail(e.clientX, e.clientY);
            lastX = e.clientX;
            lastY = e.clientY;
        }
    });
}

function createTrail(x, y) {
    const el = document.createElement('div');
    el.className = 'trail-emoji';
    el.innerText = trailEmojis[Math.floor(Math.random() * trailEmojis.length)];
    el.style.left = x + 'px';
    el.style.top = y + 'px';
    
    document.body.appendChild(el);
    
    // Animation ke baad saaf-safayi
    setTimeout(() => el.remove(), 800);
}



// Ye sirf Desktop pe chalega taaki Mobile pe battery bache
if (window.matchMedia("(min-width: 1024px)").matches) {
    
    // Event Delegation use kar rahe hain taaki performance top rahe
    document.getElementById('emoji-grid').addEventListener('mousemove', (e) => {
        const card = e.target.closest('.emoji-item');
        if (!card) return;

        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left; // Mouse X position card ke andar
        const y = e.clientY - rect.top;  // Mouse Y position card ke andar

        const centerX = rect.width / 2;
        const centerY = rect.height / 2;

        // Kitna tilt chahiye (Jyada mat rakhna varna ajeeb lagega)
        const rotateX = ((y - centerY) / centerY) * -15; 
        const rotateY = ((x - centerX) / centerX) * 15;

        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.05)`;
    });

    // Mouse hatne pe wapas normal position
    document.getElementById('emoji-grid').addEventListener('mouseleave', (e) => {
        const card = e.target.closest('.emoji-item');
        if (card) {
            card.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg) scale(1)`;
        }
    }, true);
}