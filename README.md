# 🚀 cicd/Agent: The Shared-Hosting Deployment Bridge

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![DevOps: GitOps](https://img.shields.io/badge/DevOps-GitOps-blue.svg)](#)
[![Security: HMAC--SHA256](https://img.shields.io/badge/Security-HMAC--SHA256-green.svg)](#)

**cicd/Agent** is a professional-grade, standalone deployment engine designed to bring **Modern GitOps Workflows** to restricted **Shared Hosting Environments** (Neubox, cPanel, etc.). 

It solves the "FTP-Manual Antipattern" by creating a secure, atomic, and authenticated bridge between your local build environment (Hybrid Worker) and your production server.

---

## 🌟 Key Features

- **🔒 HMAC-SHA256 Security**: Every payload is signed and verified. No open FTP ports needed.
- **⚡ Atomic Deployments**: ZIP-based delivery minimizes downtime and bandwidth.
- **🔄 Zero-Downtime Strategy**: Automatic file flattening and directory cleaning.
- **📦 Reliable Backups**: Instant `backup_latest.zip` before every change.
- **🔧 Diagnostic Tooling**: Integrated probe utility for path-finding and permission auditing.
- **🧘 Configuration Agnostic**: Fully customizable via `deploy.json`.

---

## 📁 Project Structure

```text
cicd-agent/
├── core/                # 🛠️ The Engine
│   ├── deploy_hook.php  # The main gateway (Security + Logic)
│   └── utils/           # Utilities
│       └── test_path.php # Diagnostic tool
├── docs/                # 📚 Documentation
│   ├── ARCHITECTURE.md  # Detailed flow & Mermaid diagrams
│   └── PRODUCT_ANALYSIS.md # Why this tool exists (RCA)
├── template/            # 📝 Configuration Templates
│   └── deploy.example.json # Configuration example
└── workflows/           # 🤖 Automation (n8n/Python/GitHub)
```

---

## 🚀 Quick Start

### 1. Prepare the Server
Upload `core/deploy_hook.php` to your public directory (e.g., `public_html/api/`). 

### 2. Configure Security
Copy `template/deploy.example.json` to the same folder as `deploy_hook.php` and rename it to `deploy.json`. 
Set a strong `webhook_secret`.

### 3. Setup your Hybrid Worker
Configure your n8n instance or the Python-based orchestrator to:
1. Detect a push in your repository.
2. Package the files into a `.zip`.
3. Sign the payload using `hash_hmac('sha256', $payload, $secret)`.
4. `POST` the zip with the signature in the headers to your `deploy_hook.php`.

---

## 📚 Learn More
- [Detailed Architecture](docs/ARCHITECTURE.md)
- [Product Analysis & Business Case](docs/PRODUCT_ANALYSIS.md)

---

## 🛡️ Security Disclaimer
This tool provides a secure way to deploy files, but ensure that your `deploy.json` is not publicly accessible (use `.htaccess` to block access or place it above the public root if the script path allows).

---

Developed with ❤️ by **Henry Zapata (hzapata82)**
