# SDO L&D Passbook System (LDP)

## 📋 Overview
The **SDO L&D Passbook System** is a premium digital record-keeping platform designed for the Schools Division Office (SDO). It digitizes the traditional Leraning & Development (L&D) passbook, providing a structured and secure way for employees to log training activities, personal reflections, and evidence of workplace application.

The system features a **multi-role architectural framework** (Personnel, Approvers, HR, and Admins) with a rigorous e-approval workflow and a high-fidelity audit system.

---

## 🚀 Key Features

### 🛡️ Secure Roles & Hierarchy
- **Multi-Level Access**: Dedicated portals for **Personnel**, **Approvers**, **HR**, and **Head HR/Super Admins**.
- **Hierarchy Protection**: Safeguarded high-level accounts (Head HR & Super Admin) to prevent unauthorized modifications by HR roles.
- **Auto-Verification**: Streamlined registration for admin-created accounts with instant system activation.

### � Strategic Monitoring
- **Profile Log (Audit System)**: Real-time, high-fidelity activity feed tracking all system-wide profile adjustments (excluding Super Admin records).
- **Universal Account Management**: Self-service profile editing and **High-Fidelity Avatar Picker** for all user levels.
- **Monitoring Only Mode**: Specialized "User Status Monitor" for Head HR to oversee বিভাগ-wide metrics without accessing raw personnel files.

### 📂 L&D Operations
- **Digital Activity Logging**: Intuitive interface for recording comprehensive L&D history.
- **Electronic Signatures**: Direct canvas drawing for Organizer, ASDS, and SDS signatures.
- **Evidence Management**: Secure storage for certificates and workplace application documents.
- **Printable Records**: Professional, Division-branded PDF/Print generation for official passbooks.

---

## 🛠 System Process & Workflow

### 1. Submission & Verification
Admin-created users are automatically verified. Self-registered personnel enter a **Pending Requests** queue for HR/Admin approval before system access is granted.

### 2. Multi-Stage Approval Path
Submissions follow a strict Division hierarchy:
1. **Reviewed**: Immediate Head/Supervisor verification.
2. **Recommending**: ASDS recommendation for division-wide recognition.
3. **Approved**: SDS final approval with digital signature branding.

### 3. Profile Auditing
Every profile change (Name, Position, Office, or Photo) is logged in the **Profile Log** with user details, action badges, and timestamps for division-wide transparency.

---

## 📂 Project Structure

```text
ldp/
├── admin/              # Management dashboard & HR/Head HR audit portals
├── hr/                 # Dedicated Human Resources portal
├── user/               # Personnel-specific dashboard & activities
├── pages/              # Shared core pages (Logout, Verification errors)
├── includes/           # Backend logic & core architecture
│   ├── repositories/   # PDO-based Data Access layer
│   └── functions/      # Reusable helpers (Loggers, auth handlers)
├── uploads/            # Centralized media & document storage
└── README.md           # Project Documentation
```

---

## 💻 Technology Stack
- **Backend**: Vanilla PHP 8.x
- **Database**: MySQL (PDO Extension)
- **Frontend**: Custom CSS (Vanilla with Glassmorphism), Modern JavaScript
- **Libraries**:
    - **Bootstrap Icons**: Premium iconography suite.
    - **Flatpickr**: Advanced date selection.
    - **Tom Select**: Enhanced multi-competency search.
    - **Inter & Plus Jakarta Sans**: Custom typography for premium feel.

---

*Developed for SDO Learning & Development Management.*

