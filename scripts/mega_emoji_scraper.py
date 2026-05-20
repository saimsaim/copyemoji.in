import mysql.connector
import requests
import re

# ─── CONFIGURATION ──────────────────────────────────────────
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'copyemoji'
}

# Live Official Unicode Emoji Database URL
UNICODE_URL = "https://www.unicode.org/Public/emoji/latest/emoji-test.txt"

def get_existing_slugs():
    """DB se saare existing slugs nikalo taaki duplicates check ho sakein"""
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor()
    cursor.execute("SELECT slug FROM emoji_content")
    slugs = {row[0] for row in cursor.fetchall()}
    cursor.close()
    conn.close()
    return slugs

def clean_category_name(raw_cat):
    """Category ko aapke existing DB style (Smileys & Emotion) me badlo"""
    clean = raw_cat.replace("-", " ").title()
    clean = clean.replace(" And ", " & ")
    return clean

def download_and_parse_unicode():
    """Internet se live official list download karke parse karo"""
    print("🌐 Internet se Live Official Emoji Database fetch kar raha hoon...")
    try:
        response = requests.get(UNICODE_URL, timeout=30)
        if response.status_code != 200:
            print("❌ Unicode server se connect nahi ho paya!")
            return []
    except Exception as e:
        print(f"❌ Connection error: {e}")
        return []
        
    lines = response.text.split('\n')
    parsed_emojis = []
    current_category = "General"
    
    print("⏳ Data processing chal rahi hai...")
    for line in lines:
        line = line.strip()
        
        # Category update karo line se
        if line.startswith("# group:"):
            raw_cat = line.replace("# group:", "").strip()
            current_category = clean_category_name(raw_cat)
            continue
            
        # Sirf fully-qualified (complete/real) emojis ko pakdo
        if "; fully-qualified" in line:
            parts = line.split('#')
            if len(parts) < 2:
                continue
                
            meta_part = parts[1].strip() # Example: "😀 E1.0 grinning face"
            
            # Regex se Emoji character, Version aur Name alag karo
            match = re.search(r'^(\S+)\s+E(\d+\.\d+)\s+(.+)$', meta_part)
            if match:
                emoji_char = match.group(1)
                emoji_ver = match.group(2)
                raw_name = match.group(3).strip()
                
                # Slug banao (lowercase aur hyphen separated)
                slug = raw_name.lower().replace(" ", "-").replace(":", "").replace(",", "")
                slug = re.sub(r'[^a-z0-9\-]', '', slug) # Clean strings
                
                parsed_emojis.append({
                    'emoji': emoji_char,
                    'slug': slug,
                    'name': raw_name.title(),
                    'category': current_category,
                    'version': emoji_ver
                })
                
    return parsed_emojis

def merge_to_database(internet_emojis, existing_slugs):
    """Naye emojis ko purane data se compare karke DB mein thoko"""
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor()
    
    query = """
        INSERT IGNORE INTO emoji_content 
        (slug, emoji_char, name, category, keywords, unicode_ver, emoji_ver, status)
        VALUES (%s, %s, %s, %s, %s, %s, %s, 'pending')
    """
    
    added_count = 0
    for e in internet_emojis:
        if e['slug'] not in existing_slugs:
            keywords = f"{e['name']}, {e['category']}, emoji, copy paste, meaning"
            
            try:
                cursor.execute(query, (
                    e['slug'], e['emoji'], e['name'], e['category'], 
                    keywords, e['version'], e['version']
                ))
                if cursor.rowcount > 0:
                    print(f"✨ New Found on Internet: {e['emoji']} [{e['slug']}]")
                    added_count += 1
            except Exception as err:
                continue
                
    conn.commit()
    cursor.close()
    conn.close()
    return added_count

# ─── RUN ENGINE ─────────────────────────────────────────────
if __name__ == "__main__":
    print("🚀 --- MEGA EMOJI BHANDAR AGENT START --- 🚀")
    
    # 1. Purana maal check karo
    existing = get_existing_slugs()
    print(f"ℹ️ DB mein abhi ke hisaab se {len(existing)} emojis pehle se hain.")
    
    # 2. Internet se saare official emojis uthao
    all_internet_emojis = download_and_parse_unicode()
    print(f"📋 Internet par total {len(all_internet_emojis)} official emojis mile.")
    
    # 3. Compare aur Merge karo
    new_adds = merge_to_database(all_internet_emojis, existing)
    
    print("\n==============================================")
    print(f"🎯 BHAWAAAL! Total {new_adds} NAYE EMOJIS internet se khinch kar DB mein daal diye gaye hain!")
    print("==============================================")
    print("👉 Ab chupchaap apni PHP Phase 1 chalao taaki inka content generate ho sake!")