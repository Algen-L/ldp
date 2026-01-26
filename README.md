# SDO L&D Passbook System (LDP)

## 📋 Overview
The **SDO L&D Passbook System** is a premium, high-end digital record-keeping platform designed for the Schools Division Office (SDO). It digitizes the traditional Leraning & Development (L&D) passbook, providing a structured and secure way for employees to log training activities, personal reflections, and evidence of workplace application.

The system features a multi-stage electronic approval workflow, ensuring that all recorded activities are vetted and authorized by the appropriate administrative levels.

---

## 🚀 Key Features
- **Digital Activity Logging**: Replace physical passbooks with a clean, intuitive interface for recording L&D history.
- **Electronic Signatures**: Direct canvas drawing for Organizer and SDS signatures.
- **Evidence Management**: Upload and manage multiple attachments, certificates, and workplace application evidence.
- **Multi-Stage Workflow**: Automated tracking through Submission -> Review -> Recommendation -> Approval.
- **Printable Records**: Generate professional, Division-branded PDF/Print views of the L&D Passbook.
- **Administrative Dashboard**: Comprehensive oversight for Admins and SDS to manage division-wide training data.

---

## 🛠 System Process & Workflow

### 1. User Submission
Employees log their L&D details, including:
- **Basic Info**: Title, Date(s) of Attendance, Venue, Competencies Addressed.
- **Organizer Attestation**: The conducting organization provides a digital signature directly on the form.
- **Evidence**: The user uploads certificates and evidence of how the training was applied in the workplace.
- **Reflection**: A personal summary of learning outcomes.

### 2. Multi-Stage Approval
Every submission follows a strict hierarchical path:
1. **Reviewed**: The Immediate Head/Supervisor reviews the record for accuracy.
2. **Recommending**: The Assistant Schools Division Superintendent (ASDS) recommends the record for division-wide recognition.
3. **Approved**: The Schools Division Superintendent (SDS) provides final approval, appending their digital signature.

### 3. Record Archiving
Once approved, the activity is locked. It serves as an official record for performance ratings, promotion requirements, and division-wide HR management.

---

## 📂 Project Structure

```text
ldp/
├── admin/              # Management dashboard and submission oversight
├── pages/              # Core user pages (Log Activity, Progress Track, Home)
├── includes/           # Backend logic & core architecture
│   ├── repositories/   # PDO-based Data Access layer (Repositories)
│   ├── functions/      # Reusable helpers (Loggers, signature handlers, etc.)
│   └── db.php          # Database connection and schema setup
├── assets/             # Branding (Logos, Icons)
├── css/                # Custom premium styling (User & Admin UI)
├── js/                 # Interactive logic (Signature pads, Dynamic forms)
├── uploads/            # Centralized media & document storage
└── README.md           # Project Documentation
```

---

## 💻 Technology Stack
- **Backend**: Vanilla PHP 8.x
- **Database**: MySQL (PDO Extension)
- **Frontend**: Custom CSS (Vanilla), Modern JavaScript
- **Libraries**:
    - **Bootstrap Icons**: Premium iconography.
    - **Flatpickr**: Advanced date selection.
    - **Tom Select**: Enhanced multi-competency selection.
    - **Inter & Plus Jakarta Sans**: Custom typography.

---

*Developed for SDO Learning & Development Management.*
