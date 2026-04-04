import os
import zipfile
import ftplib
import hmac
import hashlib
import requests
from dotenv import load_dotenv

# --- INITIALIZATION ---
load_dotenv()

# Configuration from Environment
FTP_HOST = os.getenv('AGENT_FTP_HOST')
FTP_USER = os.getenv('AGENT_FTP_USER')
FTP_PASS = os.getenv('AGENT_FTP_PASS')
FTP_PATH = os.getenv('AGENT_FTP_PATH', 'public_html')

HOOK_URL = os.getenv('AGENT_HOOK_URL')  # e.g. https://yourdomain.com/core/deploy_hook.php
WEBHOOK_SECRET = os.getenv('AGENT_WEBHOOK_SECRET')

ZIP_NAME = os.getenv('AGENT_ZIP_NAME', 'deploy_package.zip')
# comma separated list of files/folders to include
INCLUDE_PATHS = os.getenv('AGENT_INCLUDE_PATHS', 'index.php,src,public').split(',')

def create_package():
    print(f"📦 Packaging files into {ZIP_NAME}...")
    root_dir = os.getcwd()
    zip_path = os.path.join(root_dir, ZIP_NAME)
    
    if os.path.exists(zip_path):
        os.remove(zip_path)
        
    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for path in INCLUDE_PATHS:
            path = path.strip()
            full_path = os.path.join(root_dir, path)
            if not os.path.exists(full_path):
                print(f"⚠️ Warning: Path {path} not found. Skipping.")
                continue
                
            if os.path.isdir(full_path):
                for r, d, files in os.walk(full_path):
                    for f in files:
                        fp = os.path.join(r, f)
                        rel = os.path.relpath(fp, root_dir)
                        zipf.write(fp, rel)
            else:
                zipf.write(full_path, path)
    return zip_path

def upload_ftp(file_path):
    print(f"📡 Connecting to {FTP_HOST}...")
    ftp = ftplib.FTP(FTP_HOST)
    ftp.login(FTP_USER, FTP_PASS)
    
    try:
        ftp.cwd(FTP_PATH)
        print(f"📂 Entered directory: {FTP_PATH}")
    except Exception as e:
        print(f"❌ Error entering {FTP_PATH}: {e}")
        
    filename = os.path.basename(file_path)
    with open(file_path, 'rb') as f:
        print(f"📤 Uploading {filename}...")
        ftp.storbinary(f'STOR {filename}', f)
        
    ftp.quit()
    print("✅ Upload complete.")

def trigger_webhook(file_path):
    if not HOOK_URL or not WEBHOOK_SECRET:
        print("⏭️ Skipping Webhook trigger (HOOK_URL or SECRET not set).")
        return

    print(f"🚀 Triggering Deployment Hook at {HOOK_URL}...")
    
    # Read payload to sign (if we were sending the file content, but here we just sign the action)
    # The hook expects HMAC of the payload or just the secret as GET param
    
    # Let's perform a 'deploy' action
    params = {'action': 'deploy', 'token': WEBHOOK_SECRET}
    
    try:
        response = requests.post(HOOK_URL, params=params, timeout=30)
        print(f"📥 Response [{response.status_code}]: {response.text}")
    except Exception as e:
        print(f"❌ Error triggering hook: {e}")

if __name__ == "__main__":
    try:
        zip_file = create_package()
        upload_ftp(zip_file)
        trigger_webhook(zip_file)
        # Cleanup local zip
        # os.remove(zip_file)
        print("\n🏁 Pipeline Finished Successfully.")
    except Exception as e:
        print(f"\n💥 Pipeline Failed: {e}")
