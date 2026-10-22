# ISLAMIYYA SCHOOL MANAGEMENT SYSTEM (ISMS)

An enterprise-grade, responsive, secure, and user-friendly web-based management system tailored specifically for Islamiyya Schools, Madrasahs, and Quranic Tahfeez Academies in Nigeria.

Designed with an authentic Islamic-inspired interface (Emerald Green `#0d5c46`, Royal Gold `#c59b27`, and Pearl White), the system features full Nigerian Naira (₦) fee accounting, dual role-based dashboards (Administrator & Parent Portal), dynamic multi-child enrollment, payment verification, and printable official receipts.

---

## 🕌 Key Features & Capabilities

### 1. Administrative Dashboard & Operations
- **Dashboard Overview:** Real-time statistics on enrolled students, registered parents, new admission applications, total fee revenue collected (₦), outstanding debts, recent transactions, and class-by-class enrolment charts.
- **Parent Management:** Search and filter parents by name, phone, or status; activate, suspend, or deactivate parent accounts; reset passwords securely; view all linked children and fee payment history.
- **Student Roster & Profiles:** Full student lifecycle management (Active, Graduated, Withdrawn, Inactive); assign unique Student Identification Numbers (`ISMS/YYYY/XXX`); record Quranic & Arabic backgrounds; promote students to higher Islamiyya levels.
- **Registration Approvals:** Review parent-submitted child applications; assign approved Islamiyya class; auto-generate initial term fee invoices; issue in-app approval/rejection notices.
- **Academic Calendar:** Manage academic sessions (e.g., `2025/2026`) and terms (First, Second, Third Term); set the active period driving fee assessments.
- **Islamiyya Classes & Levels:** Preconfigured with streams including:
  - *Rawdah* (Early Years Islamic Nursery)
  - *Tahfeez Al-Quran* (Levels 1 & 2)
  - *Ibtida'iyyah* (Primary 1 to 6)
  - *Mutawassitah* (Junior Islamic Studies 1 to 3)
  - *Thanawiyyah* (Senior Islamic Studies 1 to 3)
- **Bursary & Fee Management:** Configure tuition, examination, learning materials/books, uniform, and registration charges by class and term; set payment deadlines; generate term invoices in bulk.
- **Payment Verification Module:** Review manual bank transfer claims; inspect uploaded proof slips / tellers; confirm or reject with reasons; auto-update student balances and generate stamped receipts.
- **Record Cash / POS Payments:** Bursary desk tool to record walk-in cash and POS debit payments with instant receipt generation.
- **Announcements & Notifications:** Broadcast messages to all parents, specific parent profiles, or parents of students in a particular class.
- **Automated Alerts & Debtors Reminders:** 1-click dispatch of fee reminder notices to all parents with outstanding balances.
- **Reports & Exporting:** Enrolment reports by gender and class, revenue breakdown by payment channel, fee debtor lists, and 1-click CSV exports.
- **Audit Logs:** Immutable audit trail recording administrative actions, logins, payments, and IP addresses.
- **School Settings:** Configure school logo, name, motto, campus address, phone, email, Jaiz Bank account details, Paystack & Flutterwave credentials, and receipt footer notes.

### 2. Parent Portal Experience
- **Account Registration & Login:** Parents register with phone, address, and emergency contact (next of kin). Secure login via username, phone, or email.
- **Dynamic Multi-Child Registration:** Select 1, 2, 3, 4, or more children to dynamically generate distinct registration forms for each child in a single submission.
- **My Family Roster:** View all registered children with real-time admission status badges (*Pending Review*, *Approved & Enrolled*, *Rejected with Reason*).
- **Child Academic Profiles:** Detailed profile with assigned class, age, admission date, fee statement, and individual payment history.
- **Pay School Fees:**
  - Select child and term invoice.
  - Pay in instalments or full balance.
  - Choose between **Instant Online Gateway (Paystack / Flutterwave)** or **Direct Bank Transfer (Jaiz Bank)**.
  - Upload payment evidence/teller slip for bank transfer verification.
- **Official Payment Receipts:** Branded, printable receipts featuring school header, Bismillah, watermark, official bursary stamp, ₦ breakdown, and bursar signature area.
- **In-App Notification Centre:** Read school announcements, term dates, and fee reminder notifications with unread badges.
- **Profile & Password Management:** Update residential address, phone, emergency contact, or change account password.

---

## 🛠️ Technology Stack

- **Backend:** PHP 8.3 (PDO, Prepared Statements, Secure Sessions, BCrypt Hashing)
- **Database:** MySQL 8.0+ (InnoDB, UTF-8 MB4, Normalized Schema with Foreign Keys & Cascades)
- **Frontend:** HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3.3, Bootstrap Icons
- **Typography:** Outfit & Amiri Arabic Fonts

---

## 📂 Project Directory Structure

```
islamiyya/
├── admin/                      # School Administration Portal
│   ├── alerts.php              # Fee debtors & system alerts
│   ├── audit_logs.php          # Administrative audit trail
│   ├── classes.php             # Islamiyya classes & streams
│   ├── fees.php                # Fee categories & class fee structures
│   ├── index.php               # Admin overview dashboard
│   ├── invoices.php            # Bulk invoice generation & ledger
│   ├── notifications.php       # Announcement composer & history
│   ├── parent_view.php         # Individual parent profile & audit
│   ├── parents.php             # Parent accounts management
│   ├── payments.php            # Transaction history & cash/POS recording
│   ├── registrations.php       # Child registration approval workflow
│   ├── reports.php             # Analytical reports & CSV export
│   ├── sessions.php            # Academic calendars & terms
│   ├── settings.php            # School configuration & payment gateway keys
│   ├── student_view.php        # Student profile, promotion, & fee ledger
│   ├── students.php            # Student roster & manual registration
│   └── verify_payments.php     # Bank transfer verification module
├── assets/
│   ├── css/style.css           # Custom Islamic-inspired theme & receipt print CSS
│   ├── js/main.js              # Multi-child dynamic form generator & UI helpers
│   └── uploads/receipts/       # Bank transfer proof uploads
├── auth/
│   ├── forgot_password.php     # Self-service password recovery
│   ├── login.php               # Unified login (Username/Phone/Email)
│   ├── logout.php              # Secure session termination
│   └── register.php            # Parent account registration
├── config/
│   ├── app.php                 # Global config, session setup, URL auto-detection
│   └── database.php            # PDO singleton database connection
├── database/
│   └── schema.sql              # Normalized 17-table schema & initial seed data
├── includes/
│   ├── admin_footer.php        # Admin footer template
│   ├── admin_header.php        # Admin topbar & sidebar navigation
│   ├── auth.php                # Role verification & route protection
│   ├── footer.php              # Public site footer
│   ├── functions.php           # CSRF, currency, notifications, student IDs, audit
│   ├── header.php              # Public site navigation
│   ├── parent_footer.php       # Parent portal footer
│   └── parent_header.php       # Parent portal topbar & sidebar navigation
├── about.php                   # Public About the Madrasah
├── admissions.php              # Public Admissions guide & fee schedule
├── contact.php                 # Public Contact & inquiry form
├── index.php                   # Public Homepage
├── install.php                 # Web-based database installer & setup utility
└── README.md                   # System documentation
```

---

## 🚀 Installation & Local Setup

### Option 1: Running in WampServer / XAMPP
1. Place the `islamiyya` project folder inside your web server document root:
   - **WampServer:** `C:\wamp64\www\islamiyya\`
   - **XAMPP:** `C:\xampp\htdocs\islamiyya\`
2. Start Apache and MySQL services from your control panel.
3. Open your browser and navigate to:
   ```
   http://localhost/islamiyya/install.php
   ```
4. Click **"Initialize / Reset Database Now"**.
   The installer will automatically create `islamiyya_db` on MySQL port 3306 and populate all 17 tables with pre-configured classes, sessions, fee structures, and sample accounts.

### Option 2: Command-Line Database Import
If you prefer importing via MySQL CLI:
```bash
mysql -u root -p < database/schema.sql
```

---

## 🔑 Default Login Credentials

### 1. School Administrator
- **URL:** `http://localhost/islamiyya/auth/login.php`
- **Username:** `admin` (or phone `08031234567`)
- **Password:** `Admin@123`

### 2. Sample Parent Accounts
- **Parent 1 (Approved Children & Sample Payments):**
  - **Username:** `parent_ibrahim` (or phone `08023456789`)
  - **Password:** `Parent@123`
- **Parent 2 (Has a Pending Registration for Review):**
  - **Username:** `parent_fatima` (or phone `08123456780`)
  - **Password:** `Parent@123`

---

## 🔒 Security Best Practices Implemented

1. **Password Security:** All passwords hashed using `PASSWORD_BCRYPT` via `password_hash()` and verified via `password_verify()`.
2. **SQL Injection Prevention:** 100% of database queries use PDO prepared statements with parameter binding.
3. **Cross-Site Request Forgery (CSRF):** Cryptographic session tokens validated on all POST form submissions.
4. **XSS Protection:** Context-safe escaping using `htmlspecialchars()` via helper function `e()`.
5. **Role-Based Access Control:** Strict role segregation ensuring parents cannot access administrative pages or another parent's children/invoices.
6. **Payment Security:** No sensitive card numbers or CVVs stored on the server. Bank transfers default to `pending` until verified by an administrator.

---

## 📜 License
Developed for Islamiyya Schools and Quranic Academies in Nigeria.
All rights reserved © <?= date('Y') ?> Darul Huda Islamic Academy.
