#!/usr/bin/env python3
import os
import subprocess
import sys

# Paths
CONFIG = "/etc/apache2/sites-available/safepump.conf"
FRONTEND = "/var/www/html/warp_core/index.html"

# The 5 magic lines — safe to add anywhere inside <VirtualHost *:443>
proxy_block = """
    # CRABSWAP DARK API — HTTPS PROXY (SAFE-PUMP)
    ProxyPreserveHost On
    ProxyPass        /api http://127.0.0.1:3000/
    ProxyPassReverse /api http://127.0.0.1:3000/
"""

print("CRABSWAP DARK API — FINAL BULLETPROOF SETUP")
print("="*55)

# 1. Add proxy block ONLY if not already there
if "ProxyPass /api" not in open(CONFIG).read():
    print("Injecting proxy into safepump.conf (safe & reversible)...")
    subprocess.run(["sudo", "bash", "-c", f"echo '{proxy_block}' >> {CONFIG}"])
    print("Proxy injected")
else:
    print("Proxy already exists — good")

# 2. Enable proxy modules
print("Enabling proxy modules...")
subprocess.run(["sudo", "a2enmod", "proxy", "proxy_http"], check=False)

# 3. Test + reload Apache
print("Testing Apache config...")
test = subprocess.run(["sudo", "apache2ctl", "configtest"], capture_output=True, text=True)
if "Syntax OK" in test.stdout:
    print("Config OK → reloading Apache...")
    subprocess.run(["sudo", "systemctl", "reload", "apache2"], check=True)
else:
    print("ERROR:", test.stdout or test.stderr)
    sys.exit(1)

# 4. Update frontend
print("Pointing frontend to https://safe-pump.fun/api/submit ...")
os.system(f"sudo sed -i 's|https\\?://[^/]\+:\\?[0-9]*/submit|https://safe-pump.fun/api/submit|g' {FRONTEND}")

print("\n" + "="*60)
print("THE DARK POOL IS LIVE — 100% HTTPS")
print("Frontend : https://safe-pump.fun/warp_core/")
print("Backend  : https://safe-pump.fun/api/submit")
print("Starting CRABSWAP backend...")
print("="*60 + "\n")

# 5. Launch backend
os.chdir("/var/www/html/crabswap-api")
os.execvp("node", ["node", "server.js"])
